<?php

declare(strict_types = 1);

namespace App\Model;

use App\Repository\PageAssessmentsRepository;

/**
 * A PageAssessments is responsible for handling logic around
 * processing page assessments of a given set of Pages on a Project.
 * @see https://www.mediawiki.org/wiki/Extension:PageAssessments
 */
class PageAssessments extends Model
{
    /**
     * Namespaces in which there may be page assessments.
     * @var int[]
     * @todo Always JOIN on page_assessments and only display the data if it exists.
     */
    public const SUPPORTED_NAMESPACES = [
        // Core namespaces
        ...[0, 4, 6, 10, 12, 14],
        // Custom namespaces
        ...[
            100, // Portal
            102, // WikiProject (T360774)
            108, // Book
            118, // Draft
            828, // Module
        ],
    ];

    /** @var array|null The assessments config. */
    protected ?array $config;

    /**
     * Create a new PageAssessments.
     * @param PageAssessmentsRepository $repository
     * @param Project $project
     */
    public function __construct(PageAssessmentsRepository $repository, Project $project)
    {
        $this->repository = $repository;
        $this->project = $project;
    }

    /**
     * Get page assessments configuration for the Project and cache in static variable.
     * @return string[][][]|null As defined in config/assessments.yaml, or false if none exists.
     */
    public function getConfig(): ?array
    {
        if (!isset($this->config)) {
            return $this->config = $this->repository->getConfig($this->project);
        }

        return $this->config;
    }

    /**
     * Is the given namespace supported in Page Assessments?
     * @param  int $nsId Namespace ID.
     * @return bool
     */
    public function isSupportedNamespace(int $nsId): bool
    {
        return $this->isEnabled() && in_array($nsId, self::SUPPORTED_NAMESPACES);
    }

    /**
     * Does this project support page assessments?
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool)$this->getConfig();
    }

    /**
     * Does this project have importance ratings through Page Assessments?
     * @return bool
     */
    public function hasImportanceRatings(): bool
    {
        $config = $this->getConfig();
        return isset($config['importance']);
    }

    /**
     * Get the image URL of the badge for the given page assessment.
     * @param string|null $class Valid classification for project, such as 'Start', 'GA', etc. Null for unknown.
     * @param bool $filenameOnly Get only the filename, not the URL.
     * @return string URL to image.
     */
    public function getBadgeURL(?string $class, bool $filenameOnly = false): string
    {
        $config = $this->getConfig();

        if (isset($config['class'][$class])) {
            $url = 'https://upload.wikimedia.org/wikipedia/commons/'.$config['class'][$class]['badge'];
        } elseif (isset($config['class']['Unknown'])) {
            $url = 'https://upload.wikimedia.org/wikipedia/commons/'.$config['class']['Unknown']['badge'];
        } else {
            $url = '';
        }

        if ($filenameOnly) {
            $parts = explode('/', $url);
            return end($parts);
        }

        return $url;
    }

    /**
     * Get the single overall assessment of the given page.
     * @param Page $page
     * @return string[]|false With keys 'value' and 'badge', or false if assessments are unsupported.
     */
    public function getAssessment(Page $page)
    {
        if (!$this->isEnabled() || !$this->isSupportedNamespace($page->getNamespace())) {
            return false;
        }

        $data = $this->repository->getAssessments($page, true);

        if (isset($data[0])) {
            return $this->getClassFromAssessment($data[0]);
        }

        // 'Unknown' class.
        return $this->getClassFromAssessment(['class' => '']);
    }

    /**
     * Get assessments for the given Page.
     * @param Page $page
     * @return string[]|null null if unsupported, or array in the format of:
     *         [
     *             'assessment' => [
     *                 // overall assessment
     *                 'badge' => 'https://upload.wikimedia.org/wikipedia/commons/b/bc/Featured_article_star.svg',
     *                 'color' => '#9CBDFF',
     *                 'category' => 'Category:FA-Class articles',
     *                 'class' => 'FA',
     *             ]
     *             'wikiprojects' => [
     *                 'Biography' => [
     *                     'assessment' => 'C',
     *                     'badge' => 'url',
     *                 ],
     *                 ...
     *             ],
     *             'wikiproject_prefix' => 'Wikipedia:WikiProject_',
     *         ]
     * @todo Add option to get ORES prediction.
     */
    public function getAssessments(Page $page): ?array
    {
        if (!$this->isEnabled() || !$this->isSupportedNamespace($page->getNamespace())) {
            return null;
        }

        $config = $this->getConfig();
        $data = $this->repository->getAssessments($page);

        // Set the default decorations for the overall assessment.
        // This will be replaced with the first valid class defined for any WikiProject.
        $overallAssessment = array_merge(['class' => '???'], $config['class']['Unknown']);
        $overallAssessment['badge'] = $this->getBadgeURL($overallAssessment['badge']);

        $decoratedAssessments = [];

        // Go through each raw assessment data from the database, and decorate them
        // with the colours and badges as retrieved from the XTools assessments config.
        foreach ($data as $assessment) {
            $assessment['class'] = $this->getClassFromAssessment($assessment);

            // Replace the overall assessment with the first non-empty assessment.
            if ('???' === $overallAssessment['class'] && '???' !== $assessment['class']['value']) {
                $overallAssessment['class'] = $assessment['class']['value'];
                $overallAssessment['color'] = $assessment['class']['color'];
                $overallAssessment['category'] = $assessment['class']['category'];
                $overallAssessment['badge'] = $assessment['class']['badge'];
            }

            $assessment['importance'] = $this->getImportanceFromAssessment($assessment);

            $decoratedAssessments[$assessment['wikiproject']] = $assessment;
        }

        // Don't show 'Unknown' assessment outside of the mainspace.
        if (0 !== $page->getNamespace() && '???' === $overallAssessment['class']) {
            return [];
        }

        return [
            'assessment' => $overallAssessment,
            'wikiprojects' => $decoratedAssessments,
            'wikiproject_prefix' => $config['wikiproject_prefix'],
        ];
    }

    /**
     * Get the class attributes for the given class value, as fetched from the config.
     * @param string|null $classValue Such as 'FA', 'GA', 'Start', etc.
     * @return string[] Attributes as fetched from the XTools assessments config.
     */
    public function getClassAttrs(?string $classValue): array
    {
        $classValue = $classValue ?: 'Unknown';
        return $this->getConfig()['class'][$classValue] ?? $this->getConfig()['class']['Unknown'];
    }

    /**
     * Get the properties of the assessment class, including:
     *   'value' (class name in plain text),
     *   'color' (as hex RGB),
     *   'badge' (full URL to assessment badge),
     *   'category' (wiki path to related class category).
     * @param array $assessment
     * @return array Decorated class assessment.
     */
    private function getClassFromAssessment(array $assessment): array
    {
        $classValue = $assessment['class'];

        // Use ??? as the presented value when the class is unknown or is not defined in the config
        if ('Unknown' === $classValue || '' === $classValue || !isset($this->getConfig()['class'][$classValue])) {
            return array_merge($this->getClassAttrs('Unknown'), [
                'value' => '???',
                'badge' => $this->getBadgeURL('Unknown'),
            ]);
        }

        // Known class.
        $classAttrs = $this->getClassAttrs($classValue);
        $class = [
            'value' => $classValue,
            'color' => $classAttrs['color'],
            'category' => $classAttrs['category'],
        ];

        // add full URL to badge icon
        if ('' !== $classAttrs['badge']) {
            $class['badge'] = $this->getBadgeURL($classValue);
        }

        return $class;
    }

    /**
     * Get the properties of the assessment importance, including:
     *   'value' (importance in plain text),
     *   'color' (as hex RGB),
     *   'weight' (integer, 0 is lowest importance),
     *   'category' (wiki path to the related importance category).
     * @param  array $assessment
     * @return array|null Decorated importance assessment. Null if importance could not be determined.
     */
    public function getImportanceFromAssessment(array $assessment): ?array
    {
        $importanceValue = $assessment['importance'];

        if ('' == $importanceValue && !isset($this->getConfig()['importance'])) {
            return null;
        }

        // Known importance level.
        $importanceUnknown = 'Unknown' === $importanceValue || '' === $importanceValue;

        if ($importanceUnknown || !isset($this->getConfig()['importance'][$importanceValue])) {
            $importanceAttrs = $this->getConfig()['importance']['Unknown'];

            return array_merge($importanceAttrs, [
                'value' => '???',
                'category' => $importanceAttrs['category'],
            ]);
        } else {
            $importanceAttrs = $this->getConfig()['importance'][$importanceValue];
            return [
                'value' => $importanceValue,
                'color' => $importanceAttrs['color'],
                'weight' => $importanceAttrs['weight'], // numerical weight for sorting purposes
                'category' => $importanceAttrs['category'],
            ];
        }
    }
}
