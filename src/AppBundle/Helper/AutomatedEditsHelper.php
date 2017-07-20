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
}
