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

    /** @var Edit|null Target revision that is being blamed. */
    protected $asOf;

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
     * Get all the matches as Edits.
     * @return Edit[]|null
     */
    public function getEdits(): ?array
    {
        return array_column($this->matches, 'edit');
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
     * Get the first "token" of the search query. A "token" in this case is a word or group of syntax,
     * roughly correlating to the token structure returned by the WikiWho API.
     * @return string
     */
    public function getFirstQueryToken(): string
    {
        return strtolower(preg_split('/[\n\s]/', $this->query)[0]);
    }

    /**
     * Get the target revision that is being blamed.
     * @return Edit|null
     */
    public function getAsOf(): ?Edit
    {
        if (isset($this->asOf)) {
            return $this->asOf;
        }

        $this->asOf = $this->target
            ? $this->getRepository()->getEditFromRevId($this->page, $this->target)
            : null;

        return $this->asOf;
    }

    /**
     * Get authorship attribution from the WikiWho API.
     * @see https://www.f-squared.org/wikiwho/
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
            if ($edit) {
                $this->matches[$match['id']] = [
                    'edit' => $edit,
                    'tokens' => [$match['token']],
                ];
            }
        }
    }

    /**
     * Find matches of search query in the given list of tokens.
     * @param array $tokens
     * @return array
     */
    private function searchTokens(array $tokens): array
    {
        $matchData = [];
        $matchDataSoFar = [];
        $matchSoFar = '';
        $firstQueryToken = $this->getFirstQueryToken();
        $tokenizedQuery = $this->getTokenizedQuery();

        foreach ($tokens as $token) {
            // The previous matches plus the new token. This is basically a candidate for what may become $matchSoFar.
            $newMatchSoFar = $matchSoFar.$token['str'];

            // We first check if the first token of the query matches, because we want to allow for partial matches
            // (e.g. for query "barbaz", the tokens ["foobar","baz"] should match).
            if (false !== strpos($newMatchSoFar, $firstQueryToken)) {
                // If the full query is in the new match, use it, otherwise use just the first token. This is because
                // the full match may exist across multiple tokens, but the first match is only a partial match.
                $newMatchSoFar = false !== strpos($newMatchSoFar, $tokenizedQuery)
                    ? $newMatchSoFar
                    : $firstQueryToken;
            }

            // Keep track of tokens that match. To allow partial matches,
            // we check the query against $newMatchSoFar and vice versa.
            if (false !== strpos($tokenizedQuery, $newMatchSoFar) ||
                false !== strpos($newMatchSoFar, $tokenizedQuery)
            ) {
                $matchSoFar = $newMatchSoFar;
                $matchDataSoFar[] = [
                    'id' => $token['o_rev_id'],
                    'editor' => $token['editor'],
                    'token' => $token['str'],
                ];
            } elseif (!empty($matchSoFar)) {
                // We hit a token that isn't in the query string, so start over.
                $matchDataSoFar = [];
                $matchSoFar = '';
            }

            // A full match was found, so merge $matchDataSoFar into $matchData,
            // and start over to see if there are more matches in the article.
            if (false !== strpos($matchSoFar, $tokenizedQuery)) {
                $matchData = array_merge($matchData, $matchDataSoFar);
                $matchDataSoFar = [];
                $matchSoFar = '';
            }
        }

        // Full matches usually come last, but are the most relevant.
        return array_reverse($matchData);
    }
}
