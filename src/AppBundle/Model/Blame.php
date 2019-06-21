<?php
declare(strict_types = 1);

namespace AppBundle\Model;

/**
 * A Blame will search the given page for the given text and return the relevant revisions and authors.
 */
class Blame extends Authorship
{
    /** @var string Text to search for. */
    protected $query;

    /** @var array|null Matches, keyed by revision ID, each with keys 'edit' <Edit> and 'tokens' <string[]>. */
    protected $matches;

    /**
     * Blame constructor.
     * @param Page $page The page to process.
     * @param string $query Text to search for.
     * @param string|null $target Either a revision ID or date in YYYY-MM-DD format. Null to use latest revision.
     */
    public function __construct(Page $page, string $query, ?string $target = null)
    {
        parent::__construct($page, $target);

        $this->query = $query;
    }

    /**
     * Get the search query.
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * Matches, keyed by revision ID, each with keys 'edit' <Edit> and 'tokens' <string[]>.
     * @return array|null
     */
    public function getMatches(): ?array
    {
        return $this->matches;
    }

    /**
     * Strip out spaces, since they are not accounted for in the WikiWho API.
     * @return string
     */
    public function getTokenizedQuery(): string
    {
        return strtolower(preg_replace('/\s*/m', '', $this->query));
    }

    /**
     * Get authorship attribution from the WikiWho API.
     * @see https://f-squared.org/wikiwho/
     */
    public function prepareData(): void
    {
        if (isset($this->matches)) {
            return;
        }

        // Set revision data. self::setRevisionData() returns null if there are errors.
        $revisionData = $this->getRevisionData(true);
        if (null === $revisionData) {
            return;
        }

        $matches = $this->searchTokens($revisionData['tokens']);

        // We want the results grouped by editor and revision ID.
        $this->matches = [];
        foreach ($matches as $match) {
            if (isset($this->matches[$match['id']])) {
                $this->matches[$match['id']]['tokens'][] = $match['token'];
                continue;
            }

            $edit = $this->getRepository()->getEditFromRevId($this->page, $match['id']);
            $this->matches[$match['id']] = [
                'edit' => $edit,
                'tokens' => [$match['token']],
            ];
        }
    }

    /**
     * @param array $tokens
     * @return array
     */
    private function searchTokens(array $tokens): array
    {
        $matchData = [];
        $matchDataSoFar = [];
        $matchSoFar = '';

        foreach ($tokens as $token) {
            if (0 === strpos($this->getTokenizedQuery(), $matchSoFar.$token['str'])) {
                $matchSoFar .= $token['str'];
                $matchDataSoFar[] = [
                    'id' => $token['o_rev_id'],
                    'editor' => $token['editor'],
                    'token' => $token['str'],
                ];
            } elseif (!empty($matchSoFar)) {
                $matchDataSoFar = [];
                $matchSoFar = '';
            }

            if ($matchSoFar === $this->getTokenizedQuery()) {
                $matchData = array_merge($matchData, $matchDataSoFar);
                $matchDataSoFar = [];
                $matchSoFar = '';
            }
        }

        // Full matches usually come last, but are the most relevant.
        return array_reverse($matchData);
    }
}
