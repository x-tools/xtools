<?php

namespace AppBundle\Helper;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LabsHelper
{
    /** @var string */
    protected $dbName;

    /** @var \Doctrine\DBAL\Connection */
    protected $client;

    /** @var ContainerInterface */
    protected $container;

    /** @var string */
    protected $url;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function checkEnabled($tool)
    {
        if (!$this->container->getParameter("enable.$tool")) {
            throw new NotFoundHttpException('This tool is disabled');
        }
    }

    /**
     * Set up LabsHelper::$client and return get the database name, wiki name, and URL of a given
     * project.
     * @todo: Handle failure better
     * @param string $project The project name (e.g. 'enwiki').
     * @return string[] With keys 'dbName', 'wikiName', and 'url'.
     */
    public function databasePrepare($project = 'wiki')
    {
        if ($this->container->getParameter('app.single_wiki')) {
            $dbName = $this->container->getParameter('database_replica_name');
            $wikiName = 'wiki';
            $url = $this->container->getParameter('wiki_url');
        } else {
            // Grab the connection to the meta database
            $this->client = $this->container->get('doctrine')->getManager('meta')->getConnection();

            // Create the query we're going to run against the meta database
            $wikiQuery = $this->client->createQueryBuilder();
            $wikiQuery
                ->select([ 'dbName', 'name', 'url' ])
                ->from('wiki')
                ->where($wikiQuery->expr()->eq('dbname', ':project'))
                // The meta database will have the project's URL stored as https://en.wikipedia.org
                // so we need to query for it accordingly, trying different variations the user
                // might have inputted.
                ->orwhere($wikiQuery->expr()->like('url', ':projectUrl'))
                ->orwhere($wikiQuery->expr()->like('url', ':projectUrl2'))
                ->setParameter('project', $project)
                ->setParameter('projectUrl', "https://$project")
                ->setParameter('projectUrl2', "https://$project.org");
            $wikiStatement = $wikiQuery->execute();

            // Fetch the wiki data
            $wikis = $wikiStatement->fetchAll();

            // Throw an exception if we can't find the wiki
            if (count($wikis) < 1) {
                // TODO: Fix so that we're rendering a flash rather than dying...
                throw new Exception('Unable to find project');
                // $this->container->get('controller')->addFlash('notice', ["nowiki", $project]);
                // return $this->container->redirectToRoute($route);
            }

            // Grab the data we need out of it, using the first result
            // (in the rare event there are more than one).
            $dbName = $wikis[0]['dbName'];
            $wikiName = $wikis[0]['name'];
            $url = $wikis[0]['url'];
        }

        if ($this->container->getParameter('app.is_labs') && substr($dbName, -2) != '_p') {
            $dbName .= '_p';
        }

        $this->dbName = $dbName;
        $this->url = $url;

        return [ 'dbName' => $dbName, 'wikiName' => $wikiName, 'url' => $url ];
    }

    /**
     * All mapping tables to environment-specific names, as specified in config/table_map.yml
     * Used for example to convert revision -> revision_replica
     * https://wikitech.wikimedia.org/wiki/Help:Tool_Labs/Database#Tables_for_revision_or_logging_queries_involving_user_names_and_IDs
     * @param string $table Table name
     * @param string $dbName Database name
     * @return string Converted table name
     */
    public function getTable($table, $dbName = null)
    {
        // Use the table specified in the table mapping configuration, if present.
        $mapped = false;
        if ($this->container->hasParameter("app.table.$table")) {
            $mapped = true;
            $table = $this->container->getParameter("app.table.$table");
        }

        // For 'revision' and 'logging' tables (actually views) on Labs, use the indexed versions
        // (that have some rows hidden, e.g. for revdeleted users).
        $isLoggingOrRevision = in_array($table, ['revision', 'logging', 'archive']);
        if (!$mapped && $isLoggingOrRevision && $this->container->getParameter('app.is_labs')) {
            $table = $table."_userindex";
        }

        // Prepend database name.
        // Use class variable for the database name if not set via function parameter.
        $dbName = $dbName || $this->dbName;
        if (!empty($dbName)) {
            $table = "$this->dbName.$table";
        }

        return $table;
    }

    // TODO: figure out how to use Doctrine to query host 'tools-db'
}
