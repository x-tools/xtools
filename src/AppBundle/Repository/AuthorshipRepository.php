<?php
declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Model\Page;
use AppBundle\Model\Project;
use GuzzleHttp;

/**
 * AuthorshipRepository is responsible for retrieving authorship data about a single page.
 * @codeCoverageIgnore
 */
class AuthorshipRepository extends Repository
{
    /**
     * Query the WikiWho service to get authorship percentages.
     * @see https://api.wikiwho.net/
     * @param Page $page
     * @param int|null $revId ID of revision to target, or null for latest revision.
     * @return array[]|null Response from WikiWho. null if something went wrong.
     */
    public function getData(Page $page, ?int $revId): ?array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'page_authorship');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $title = rawurlencode(str_replace(' ', '_', $page->getTitle()));
        $projectLang = $page->getProject()->getLang();

        $url = "https://api.wikiwho.net/$projectLang/api/v1.0.0-beta/rev_content/$title"
            .($revId ? "/$revId" : '')
            ."/?o_rev_id=false&editor=true&token_id=false&out=false&in=false";

        // Ignore HTTP errors to fail gracefully.
        $opts = ['http_errors' => false];

        // Use WikiWho API credentials, if present. They are not required.
        if ($this->container->hasParameter('app.wikiwho.username')) {
            $opts['auth'] = [
                $this->container->getParameter('app.wikiwho.username'),
                $this->container->getParameter('app.wikiwho.password'),
            ];
        }

        /** @var GuzzleHttp\Client $client */
        $client = $this->container->get('eight_points_guzzle.client.xtools');

        $res = $client->request('GET', $url, $opts);

        // Cache and return.
        return $this->setCache($cacheKey, json_decode($res->getBody()->getContents(), true));
    }

    /**
     * Get a map of user IDs/usernames given the user IDs.
     * @param Project $project
     * @param int[] $userIds
     * @return array
     */
    public function getUsernamesFromIds(Project $project, array $userIds): array
    {
        $userTable = $project->getTableName('user');
        $userIds = implode(',', array_unique(array_filter($userIds)));
        $sql = "SELECT user_id, user_name
                FROM $userTable
                WHERE user_id IN ($userIds)";
        return $this->executeProjectsQuery($sql)->fetchAll();
    }
}
