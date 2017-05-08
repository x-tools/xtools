<?php

namespace AppBundle\Helper;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\VarDumper\VarDumper;

class AutomatedEditsHelper
{

    /** @var ContainerInterface */
    private $container;

    /** @var string[] The list of tools that are considered reverting. */
    public $revertTools = [
        'Generic rollback',
        'Undo',
        'Pending changes revert',
        'Huggle',
        'STiki',
        'Igloo',
        'WikiPatroller',
        'Twinkle revert',
        'Bot revert'
    ];

    /** @var string[] The list of tool names and their regexes. */
    protected $tools;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Was the edit (semi-)automated, based on the edit summary?
     * @param  string  $summary Edit summary
     * @return boolean          Yes or no
     */
    public function isAutomated($summary)
    {
        foreach ($this->getTools() as $tool => $regex) {
            if (preg_match("/$regex/", $summary)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Was the edit a revert, based on the edit summary?
     * @param  string $summary Edit summary
     * @return boolean         Yes or no
     */
    public function isRevert($summary)
    {
        foreach ($this->revertTools as $tool) {
            $regex = $this->getTools()[$tool];

            if (preg_match("/$regex/", $summary)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the name of the tool that matched the given edit summary
     * @param  string $summary Edit summary
     * @return string|boolean  Name of tool, or false if nothing was found
     */
    public function getTool($summary)
    {
        foreach ($this->getTools() as $tool => $regex) {
            if (preg_match("/$regex/", $summary)) {
                return $tool;
            }
        }

        return false;
    }

    /**
     * Get the list of automated tools
     * @return array Associative array of 'tool name' => 'regex'
     */
    public function getTools()
    {
        if (is_array($this->tools)) {
            return $this->tools;
        }
        $this->tools = $this->container->getParameter("automated_tools");
        return $this->tools;
    }

    /**
     * Get a summary of automated edits made by the given user in their last 1000 edits.
     * Will cache the result for 10 minutes.
     * @param integer $userId The user ID.
     * @return integer[] Array of edit counts, keyed by all tool names from
     * app/config/semi_automated.yml
     */
    public function getEditsSummary($userId)
    {
        // Set up cache.
        /** @var CacheItemPoolInterface $cache */
        $cache = $this->container->get('cache.app');
        $cacheItem = $cache->getItem('automatedEdits.'.$userId);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        // Get the most recent 1000 edit summaries.
        /** @var Connection $replicas */
        $replicas = $this->container->get('doctrine')->getManager('replicas')->getConnection();
        /** @var LabsHelper $labsHelper */
        $labsHelper = $this->container->get('app.labs_helper');
        $sql = "SELECT rev_comment FROM ".$labsHelper->getTable('revision')
               ." WHERE rev_user=:userId ORDER BY rev_timestamp DESC LIMIT 1000";
        $resultQuery = $replicas->prepare($sql);
        $resultQuery->bindParam("userId", $userId);
        $resultQuery->execute();
        $results = $resultQuery->fetchAll();
        $out = [];
        foreach ($results as $result) {
            $toolName = $this->getTool($result['rev_comment']);
            if ($toolName) {
                if (!isset($out[$toolName])) {
                    $out[$toolName] = 0;
                }
                $out[$toolName]++;
            }
        }
        arsort($out);

        // Cache for 10 minutes.
        $cacheItem->expiresAfter(new \DateInterval('PT10M'));
        $cacheItem->set($out);
        $cache->save($cacheItem);

        return $out;
    }
}
