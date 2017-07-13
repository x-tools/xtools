<?php
/**
 * This file contains only the AutomatedEditsHelper class.
 */

namespace AppBundle\Helper;

use DateTime;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Xtools\ProjectRepository;

/**
 * Helper class for getting information about semi-automated edits.
 */
class AutomatedEditsHelper extends HelperBase
{

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

    /**
     * AutomatedEditsHelper constructor.
     * @param ContainerInterface $container
     */
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
        foreach ($this->getTools() as $tool => $values) {
            $regex = $values['regex'];
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
            $regex = $this->getTools()[$tool]['regex'];

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
        foreach ($this->getTools() as $tool => $values) {
            $regex = $values['regex'];
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
        $this->tools = $this->container->getParameter('automated_tools');
        return $this->tools;
    }

    /**
     * Get a list of nonautomated edits by a user
     * @param  Project        $project   Project object
     * @param  string         $username
     * @param  string|integer $namespace Numerical value or 'all' for all namespaces
     * @param  integer        $offset    Used for pagination, offset results by N edits
     * @return string[]       Data as returned by database query, includes:
     *                        'page_title' (string, including namespace),
     *                        'namespace' (integer), 'rev_id' (int),
     *                        'timestamp' (DateTime), 'minor_edit' (bool)
     *                        'sumamry' (string)
     */
    public function getNonautomatedEdits($project, $username, $namespace, $offset = 0)
    {
        $namespaces = $project->getNamespaces();

        $conn = $this->container->get('doctrine')->getManager('replicas')->getConnection();

        $revTable = $project->getRepository()->getTableName($project->getDatabaseName(), 'revision');
        $pageTable = $project->getRepository()->getTableName($project->getDatabaseName(), 'page');

        $AEBTypes = array_map(function ($AEBType) {
            return $AEBType['regex'];
        }, $this->container->getParameter('automated_tools'));

        $allAETools = $conn->quote(implode('|', $AEBTypes), \PDO::PARAM_STR);

        $namespaceClause = $namespace === 'all' ? '' : "AND rev_namespace = $namespace";

        // First get the non-automated contribs
        $query = "SELECT page_title, page_namespace, rev_id, rev_len, rev_parent_id,
                         rev_timestamp, rev_minor_edit, rev_comment
                  FROM $pageTable JOIN $revTable ON page_id = rev_page
                  WHERE rev_user_text = :username
                  AND rev_timestamp > 0
                  AND rev_comment NOT RLIKE $allAETools
                  ORDER BY rev_id DESC
                  LIMIT 50
                  OFFSET $offset";
        $editData = $conn->executeQuery($query, ['username' => $username])->fetchAll();

        if (empty($editData)) {
            return [];
        }

        // Get diff sizes, based on length of each parent revision
        $parentRevIds = array_map(function ($edit) {
            return $edit['rev_parent_id'];
        }, $editData);
        $query = "SELECT rev_len, rev_id
                  FROM revision
                  WHERE rev_id IN (" . implode(',', $parentRevIds) . ")";
        $diffSizeData = $conn->executeQuery($query)->fetchAll();

        // reformat with rev_id as the key, rev_len as the value
        $diffSizes = [];
        foreach ($diffSizeData as $diff) {
            $diffSizes[$diff['rev_id']] = $diff['rev_len'];
        }

        // Build our array of nonautomated edits
        $editData = array_map(function ($edit) use ($namespaces, $diffSizes) {
            $pageTitle = $edit['page_title'];

            if ($edit['page_namespace'] !== '0') {
                $pageTitle = $namespaces[$edit['page_namespace']] . ":$pageTitle";
            }

            $diffSize = $edit['rev_len'];
            if ($edit['rev_parent_id'] > 0) {
                $diffSize = $edit['rev_len'] - $diffSizes[$edit['rev_parent_id']];
            }

            return [
                'page_title' => $pageTitle,
                'namespace' => (int) $edit['page_namespace'],
                'rev_id' => (int) $edit['rev_id'],
                'timestamp' => DateTime::createFromFormat('YmdHis', $edit['rev_timestamp']),
                'minor_edit' => (bool) $edit['rev_minor_edit'],
                'summary' => $edit['rev_comment'],
                'size' => $diffSize
            ];
        }, $editData);

        return $editData;
    }
}
