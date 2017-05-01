<?php

namespace Xtools;

use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;

class ProjectRepository extends Repository
{

    /** @var array Project metadata. */
    protected $metadata;

    /** @var string[] */
    protected $singleMetadata;

    /**
     * For single-wiki installations, you must manually set the wiki URL and database name
     * (because there's no meta.wiki database to query).
     * @param $metadata
     * @throws \Exception
     */
    public function setSingleMetadata($metadata)
    {
        if (!array_key_exists('url', $metadata) || !array_key_exists('dbname', $metadata)) {
            $error = "Single-wiki metadata should contain 'url' and 'dbname' keys.";
            throw new \Exception($error);
        }
        $this->singleMetadata = array_intersect_key($metadata, ['url' => '', 'dbname' => '']);
    }

    /**
     * Get metadata about all projects.
     * @return string[] Each item has 'dbname' and 'url' keys.
     */
    public function getAll()
    {
        if ($this->singleMetadata) {
            return [$this->getOne('')];
        }
        $wikiQuery = $this->metaConnection->createQueryBuilder();
        $wikiQuery->select(['dbname', 'url'])->from('wiki');
        return $wikiQuery->execute()->fetchAll();
    }

    /**
     * Get metadata about one project.
     * @param string $project A project URL, domain name, or database name.
     * @return string[] With 'dbname' and 'url' keys.
     */
    public function getOne($project)
    {
        // For single-wiki setups, every project is the same.
        if ($this->singleMetadata) {
            return $this->singleMetadata;
        }

        // Otherwise, fetch the project's metadata from the meta.wiki table.
        $wikiQuery = $this->metaConnection->createQueryBuilder();
        $wikiQuery->select(['dbname', 'url'])
            ->from('wiki')
            ->where($wikiQuery->expr()->eq('dbname', ':project'))
            // The meta database will have the project's URL stored as https://en.wikipedia.org
            // so we need to query for it accordingly, trying different variations the user
            // might have inputted.
            ->orwhere($wikiQuery->expr()->like('url', ':projectUrl'))
            ->orwhere($wikiQuery->expr()
                ->like('url', ':projectUrl2'))
            ->setParameter('project', $project)
            ->setParameter('projectUrl', "https://$project")
            ->setParameter('projectUrl2', "https://$project.org");
        $wikiStatement = $wikiQuery->execute();

        // Fetch the wiki data.
        return $wikiStatement->fetch();
    }

    /**
     * Get metadata about a project.
     *
     * @param string $projectUrl The project's URL.
     * @return array With 'general' and 'namespaces' keys: the former contains 'wikiName',
     * 'wikiId', 'url', 'lang', 'articlePath', 'scriptPath', 'script', 'timezone', and
     * 'timezoneOffset'; the latter contains all namespace names, keyed by their IDs.
     */
    public function getMetadata($projectUrl)
    {
        if ($this->metadata) {
            return $this->metadata;
        }
        
        $api = MediawikiApi::newFromPage($projectUrl);

        $params = ['meta' => 'siteinfo', 'siprop' => 'general|namespaces'];
        $query = new SimpleRequest('query', $params);

        $this->metadata = [
            'general' => [],
            'namespaces' => [],
        ];

        $res = $api->getRequest($query);

        if (isset($res['query']['general'])) {
            $info = $res['query']['general'];
            $this->metadata['general'] = [
                'wikiName' => $info['sitename'],
                'wikiId' => $info['wikiid'],
                'url' => $info['server'],
                'lang' => $info['lang'],
                'articlePath' => $info['articlepath'],
                'scriptPath' => $info['scriptpath'],
                'script' => $info['script'],
                'timezone' => $info['timezone'],
                'timeOffset' => $info['timeoffset'],
            ];

//            if ($this->container->getParameter('app.is_labs') &&
//                substr($result['general']['dbName'], -2) != '_p'
//            ) {
//                $result['general']['dbName'] .= '_p';
//            }
        }

        if (isset($res['query']['namespaces'])) {
            foreach ($res['query']['namespaces'] as $namespace) {
                if ($namespace['id'] < 0) {
                    continue;
                }

                if (isset($namespace['name'])) {
                    $name = $namespace['name'];
                } elseif (isset($namespace['*'])) {
                    $name = $namespace['*'];
                } else {
                    continue;
                }

                // FIXME: Figure out a way to i18n-ize this
                if ($name === '') {
                    $name = 'Article';
                }

                $this->metadata['namespaces'][$namespace['id']] = $name;
            }
        }

        return $this->metadata;
    }
}
