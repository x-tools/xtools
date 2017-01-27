<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 1/16/17
 * Time: 16:38
 */

namespace AppBundle\Helper;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class labsHelper
{
    private $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function checkEnabled($tool) {
        if (!$this->container->getParameter("enable.$tool")) {
            throw new NotFoundHttpException("This tool is disabled");
        }
    }

    // Todo: Handle failure better
    public function databasePrepare($project = "wiki", $route = "homepage") {
        if ($this->container->getParameter("app.single_wiki")) {
            $dbName = $this->container->getParameter("database_replica_name");
            $wikiName = "wiki";
            $url = $this->container->getParameter("wiki_url");
        }
        else {
            // Grab the connection to the meta database
            $conn = $this->container->get('doctrine')->getManager("meta")->getConnection();

            // Create the query we're going to run against the meta database
            $wikiQuery = $conn->createQueryBuilder();
            $wikiQuery
                ->select(['dbName', 'name', 'url'])
                ->from("wiki")
                ->where($wikiQuery->expr()->eq('dbname', ':project'))
                ->orwhere($wikiQuery->expr()->like('name', ':project'))
                ->orwhere($wikiQuery->expr()->like('url', ":project"))
                ->setParameter("project", $project);
            $wikiStatement = $wikiQuery->execute();

            // Fetch the wiki data
            $wikis = $wikiStatement->fetchAll();

            // Throw an exception if we can't find the wiki
            if (sizeof($wikis) < 1) {
                $this->addFlash('notice', ["nowiki", $project]);
                return $this->redirectToRoute($route);
            }

            // Grab the data we need out of it.
            $dbName = $wikis[0]['dbName'];
            $wikiName = $wikis[0]['name'];
            $url = $wikis[0]['url'];
        }

        if ($this->container->getParameter("app.is_labs") && substr($dbName, -2) != "_p") {
            $dbName .= "_p";
        }

        return ["dbName" => $dbName, "wikiName" => $wikiName, "url" => $url];
    }

    public function getTable($table, $dbName = null) {
        $retVal = $table;
        if($this->container->hasParameter("app.table.$table")) {
                $retVal = $this->container->getParameter("app.table.$table");
            }
        if (isset($dbName)) {
            $retVal = "$dbName.$retVal";
        }
        return $retVal;
        }
}