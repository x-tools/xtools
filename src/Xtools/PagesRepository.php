<?php

namespace Xtools;

use Mediawiki\Api\SimpleRequest;

/**
 * A PagesRepository fetches data about Pages, either singularly or for multiple.
 */
class PagesRepository extends Repository
{

    /**
     * Get metadata about a single page from the API.
     * @param Project $project The project to which the page belongs.
     * @param string $pageTitle Page title.
     * @param boolean $followRedirects Whether or not to resolve redirects
     * @return string[] Array with some of the following keys: pageid, title, missing, displaytitle,
     * url.
     */
    public function getPageInfo(Project $project, $pageTitle, $followRedirects = true)
    {
        $info = $this->getPagesInfo($project, [$pageTitle], $followRedirects);
        return array_shift($info);
    }

    /**
     * Get metadata about a set of pages from the API.
     * @param Project $project The project to which the pages belong.
     * @param string[] $pageTitles Array of page titles.
     * @param boolean $followRedirects Whether or not to resolve redirects
     * @return string[] Array keyed by the page names, each element with some of the
     * following keys: pageid, title, missing, displaytitle, url.
     */
    public function getPagesInfo(Project $project, $pageTitles, $followRedirects = true)
    {
        // @TODO: Also include 'extlinks' prop when we start checking for dead external links.
        $params = [
            'prop' => 'info|pageprops',
            'inprop' => 'protection|talkid|watched|watchers|notificationtimestamp|subjectid|url|readable|displaytitle',
            'converttitles' => '',
            // 'ellimit' => 20,
            // 'elexpandurl' => '',
            'titles' => join('|', $pageTitles),
            'formatversion' => 2
            // 'pageids' => $pageIds // FIXME: allow page IDs
        ];
        if ($followRedirects) {
            $params['redirects'] = '';
        }

        $query = new SimpleRequest('query', $params);
        $api = $this->getMediawikiApi($project);
        $res = $api->getRequest($query);
        $result = [];
        if (isset($res['query']['pages'])) {
            foreach ($res['query']['pages'] as $pageInfo) {
                $result[$pageInfo['title']] = $pageInfo;
            }
        }
        return $result;
    }
}
