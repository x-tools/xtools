<?php
/**
 * This file contains only the PageAssessments class.
 */

declare(strict_types = 1);

namespace AppBundle\Model;

/**
 * A PageAssessments is responsible for handling logic around
 * processing page assessments of a given set of Pages on a Project.
 * @see https://www.mediawiki.org/wiki/Extension:PageAssessments
 */
class PageAssessments extends Model
{
    /** Namespaces in which there may be page assessments. */
    public const SUPPORTED_NAMESPACES = [0, 4, 6, 10, 14, 100, 108, 118];

    /** @var array The assessments config. */
    protected $config;

    /**
     * Create a new PageAssessments.
     * @param Project $project
     */
    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    /**
     * Get page assessments configuration for the Project and cache in static variable.
     * @return string[][][]|false As defined in config/assessments.yml, or false if none exists.
     */
    public function getConfig()
    {
        if (!isset($this->config)) {
            return $this->config = $this->getRepository()->getConfig($this->project);
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
            $url = "";
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
     * @todo Add option to get ORES prediction.
     */
    public function getAssessment(Page $page)
    {
        if (!$this->isEnabled() || !$this->isSupportedNamespace($page->getNamespace())) {
            return false;
        }

        $data = $this->getRepository()->getAssessments($page, true);

        if (isset($data[0])) {
            return $this->getClassFromAssessment($data[0]);
        }

        // 'Unknown' class.
        return $this->getClassFromAssessment(['class' => '']);
    }

    /**
     * Get assessments for the given Page.
     * @param Page $page
     * @return string[]|false `false` if unsupported, or array in the format of:
     *         [
     *             'assessment' => 'C', // overall assessment
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
    public function getAssessments(Page $page)
    {
        if (!$this->isEnabled() || !$this->isSupportedNamespace($page->getNamespace())) {
            return false;
        }

        $config = $this->getConfig();
        $data = $this->getRepository()->getAssessments($page);

        // Set the default decorations for the overall quality assessment.
        // This will be replaced with the first valid class defined for any WikiProject.
        $overallQuality = $config['class']['Unknown'];
        $overallQuality['value'] = '???';
        $overallQuality['badge'] = $this->getBadgeURL($overallQuality['badge']);

        $decoratedAssessments = [];

        // Go through each raw assessment data from the database, and decorate them
        // with the colours and badges as retrieved from the XTools assessments config.
        foreach ($data as $assessment) {
            $assessment['class'] = $this->getClassFromAssessment($assessment);

            if ('???' === $overallQuality['value']) {
                $overallQuality = $assessment['class'];
            }

            $assessment['importance'] = $this->getImportanceFromAssessment($assessment);

            $decoratedAssessments[$assessment['wikiproject']] = $assessment;
        }

        // Don't shown 'Unknown' assessment outside of the mainspace.
        if (0 !== $page->getNamespace() && '???' === $overallQuality['value']) {
            return false;
        }

        return [
            'assessment' => $overallQuality,
            'wikiprojects' => $decoratedAssessments,
            'wikiproject_prefix' => $config['wikiproject_prefix'],
        ];
    }

    /**
     * Get the class attributes for the given class value,
     * as fetched from the config.
     * @param string $classValue Such as 'FA', 'GA', 'Start', etc.
     * @return string[] Attributes as fetched from the XTools assessments config.
     */
    private function getClassAttrs(string $classValue): array
    {
        return $this->getConfig()['class'][$classValue];
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
    private function getImportanceFromAssessment(array $assessment): ?array
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
