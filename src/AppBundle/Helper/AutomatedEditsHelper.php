<?php

namespace AppBundle\Helper;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AutomatedEditsHelper
{
    private $container;
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
        return call_user_func_array(
            'array_merge',
            $this->container->getParameter("automated_tools")
        );
    }
}
