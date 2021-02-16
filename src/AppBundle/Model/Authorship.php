<?php
declare(strict_types=1);

namespace AppBundle\Model;

use DateTime;
use GuzzleHttp\Exception\RequestException;

class Authorship extends Model
{
    /** @const string[] Domain names of wikis supported by WikiWho. */
    public const SUPPORTED_PROJECTS = [
        'de.wikipedia.org',
        'en.wikipedia.org',
        'es.wikipedia.org',
        'eu.wikipedia.org',
        'tr.wikipedia.org',
    ];

    /** @var int|null Target revision ID. Null for latest revision. */
    protected $target;

    /** @var array List of editors and the percentage of the current content that they authored. */
    protected $data;

    /** @var mixed[] Revision that the data pertains to, with keys 'id' and 'timestamp'. */
    protected $revision;

    /**
     * Authorship constructor.
     * @param Page $page The page to process.
     * @param string|null $target Either a revision ID or date in YYYY-MM-DD format. Null to use latest revision.
     * @param int|null $limit Max number of results.
     */
    public function __construct(Page $page, ?string $target = null, ?int $limit = null)
    {
        $this->page = $page;
        $this->limit = $limit;
        $this->target = $this->getTargetRevId($target);
    }

    private function getTargetRevId(?string $target): ?int
    {
        if (null === $target) {
            return null;
        }

        if (preg_match('/\d{4}-\d{2}-\d{2}/', $target)) {
            $date = DateTime::createFromFormat('Y-m-d', $target);
            return $this->page->getRevisionIdAtDate($date);
        }

        return (int)$target;
    }

    /**
     * Domains of supported wikis.
     * @return string[]
     */
    public function getSupportedWikis(): array
    {
        return self::SUPPORTED_PROJECTS;
    }

    /**
     * Get the target revision ID. Null for latest revision.
     * @return int|null
     */
    public function getTarget(): ?int
    {
        return $this->target;
    }

    /**
     * Authorship information for the top $this->limit authors.
     * @return array
     */
    public function getList(): array
    {
        return $this->data['list'] ?? [];
    }

    /**
     * Get error thrown when preparing the data, or null if no error occurred.
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->data['error'] ?? null;
    }

    /**
     * Get the total number of authors.
     * @return int
     */
    public function getTotalAuthors(): int
    {
        return $this->data['totalAuthors'];
    }

    /**
     * Get the total number of characters added.
     * @return int
     */
    public function getTotalCount(): int
    {
        return $this->data['totalCount'];
    }

    /**
     * Get summary data on the 'other' authors who are not in the top $this->limit.
     * @return array|null
     */
    public function getOthers(): ?array
    {
        return $this->data['others'] ?? null;
    }

    /**
     * Get the revision the authorship data pertains to, with keys 'id' and 'timestamp'.
     * @return array|null
     */
    public function getRevision(): ?array
    {
        return $this->revision;
    }

    /**
     * Is the given page supported by the Authorship tool?
     * @param Page $page
     * @return bool
     */
    public static function isSupportedPage(Page $page): bool
    {
        return in_array($page->getProject()->getDomain(), self::SUPPORTED_PROJECTS) &&
            0 === $page->getNamespace();
    }

    /**
     * Get the revision data from the WikiWho API and set $this->revision with basic info.
     * If there are errors, they are placed in $this->data['error'] and null will be returned.
     * @param bool $returnRevId Whether or not to include revision IDs in the response.
     * @return array|null null if there were errors.
     */
    protected function getRevisionData(bool $returnRevId = false): ?array
    {
        try {
            $ret = $this->getRepository()->getData($this->page, $this->target, $returnRevId);
        } catch (RequestException $e) {
            $this->data = [
                'error' => 'unknown',
            ];
            return null;
        }

        // If revision can't be found, return error message.
        if (!isset($ret['revisions'][0])) {
            $this->data = [
                'error' => $ret['Error'] ?? 'Unknown',
            ];
            return null;
        }

        $revId = array_keys($ret['revisions'][0])[0];
        $revisionData = $ret['revisions'][0][$revId];

        $this->revision = [
            'id' => $revId,
            'timestamp' => $revisionData['time'],
        ];

        return $revisionData;
    }

    /**
     * Get authorship attribution from the WikiWho API.
     * @see https://www.f-squared.org/wikiwho/
     */
    public function prepareData(): void
    {
        if (isset($this->data)) {
            return;
        }

        // Set revision data. self::setRevisionData() returns null if there are errors.
        $revisionData = $this->getRevisionData();
        if (null === $revisionData) {
            return;
        }

        [$counts, $totalCount, $userIds] = $this->countTokens($revisionData['tokens']);
        $usernameMap = $this->getUsernameMap($userIds);

        if (null !== $this->limit) {
            $countsToProcess = array_slice($counts, 0, $this->limit, true);
        } else {
            $countsToProcess = $counts;
        }

        $data = [];

        // Used to get the character count and percentage of the remaining N editors, after the top $this->limit.
        $percentageSum = 0;
        $countSum = 0;
        $numEditors = 0;

        // Loop through once more, creating an array with the user names (or IP addresses)
        // as the key, and the count and percentage as the value.
        foreach ($countsToProcess as $editor => $count) {
            if (isset($usernameMap[$editor])) {
                $index = $usernameMap[$editor];
            } else {
                $index = $editor;
            }

            $percentage = round(100 * ($count / $totalCount), 1);

            // If we are showing > 10 editors in the table, we still only want the top 10 for the chart.
            if ($numEditors < 10) {
                $percentageSum += $percentage;
                $countSum += $count;
                $numEditors++;
            }

            $data[$index] = [
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        $this->data = [
            'list' => $data,
            'totalAuthors' => count($counts),
            'totalCount' => $totalCount,
        ];

        // Record character count and percentage for the remaining editors.
        if ($percentageSum < 100) {
            $this->data['others'] = [
                'count' => $totalCount - $countSum,
                'percentage' => round(100 - $percentageSum, 1),
                'numEditors' => count($counts) - $numEditors,
            ];
        }
    }

    /**
     * Get a map of user IDs to usernames, given the IDs.
     * @param int[] $userIds
     * @return array IDs as keys, usernames as values.
     */
    private function getUsernameMap(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $userIdsNames = $this->getRepository()->getUsernamesFromIds(
            $this->page->getProject(),
            $userIds
        );

        $usernameMap = [];
        foreach ($userIdsNames as $userIdName) {
            $usernameMap[$userIdName['user_id']] = $userIdName['user_name'];
        }

        return $usernameMap;
    }

    /**
     * Get counts of token lengths for each author. Used in self::prepareData()
     * @param array $tokens
     * @return array [counts by user, total count, IDs of accounts]
     */
    private function countTokens(array $tokens): array
    {
        $counts = [];
        $userIds = [];
        $totalCount = 0;

        // Loop through the tokens, keeping totals (token length) for each author.
        foreach ($tokens as $token) {
            $editor = $token['editor'];

            // IPs are prefixed with '0|', otherwise it's the user ID.
            if ('0|' === substr($editor, 0, 2)) {
                $editor = substr($editor, 2);
            } else {
                $userIds[] = $editor;
            }

            if (!isset($counts[$editor])) {
                $counts[$editor] = 0;
            }

            $counts[$editor] += strlen($token['str']);
            $totalCount += strlen($token['str']);
        }

        // Sort authors by count.
        arsort($counts);

        return [$counts, $totalCount, $userIds];
    }
}
