<?php

namespace Xtools;

use Mediawiki\Api\MediawikiApi;
use Symfony\Component\VarDumper\VarDumper;

/**
 * A Project is a single wiki that Xtools is querying.
 */
class Project extends Model
{

    /** @var string The project name as supplied by the user. */
    protected $nameUnnormalized;

    /** @var string[] Basic metadata about the project */
    protected $metadata;

    public function __construct($nameOrUrl)
    {
        $this->nameUnnormalized = $nameOrUrl;
    }

    protected function getMetadata()
    {
        if (!$this->metadata) {
            $this->metadata = $this->getRepository()->getOne($this->nameUnnormalized);
        }
        return $this->metadata;
    }

    /**
     * Does this project exist?
     * @return bool
     */
    public function exists()
    {
        return !empty($this->getDomain());
    }

    /**
     * The unique domain name of this project, without protocol or path components.
     * This should be used as the canonical project identifier.
     *
     * @return string
     */
    public function getDomain()
    {
        $url = isset($this->getMetadata()['url']) ? $this->getMetadata()['url'] : '';
        return parse_url($url, PHP_URL_HOST);
    }

    /**
     * The name of the database for this project.
     *
     * @return string
     */
    public function getDatabaseName()
    {
        return isset($this->getMetadata()['dbname']) ? $this->getMetadata()['dbname'] : '';
    }

    /**
     * The language for this project.
     *
     * @return string
     */
    public function getLang()
    {
        return isset($this->getMetadata()['lang']) ? $this->getMetadata()['lang'] : '';
    }

    /**
     * The project URL is the fully-qualified domain name, with protocol and trailing slash.
     *
     * @param bool $withTrailingSlash Whether to append a slash.
     * @return string
     */
    public function getUrl($withTrailingSlash = true)
    {
        return rtrim($this->getMetadata()['url'], '/') . ($withTrailingSlash ? '/' : '');
    }

    /**
     * Get a MediawikiApi object for this Project.
     *
     * @return MediawikiApi
     */
    public function getApi()
    {
        return $this->getRepository()->getMediawikiApi($this);
    }

    /**
     * The base URL path of this project (that page titles are appended to).
     * For some wikis the title (apparently) may not be at the end.
     * Replace $1 with the article name.
     *
     * @link https://www.mediawiki.org/wiki/Manual:$wgArticlePath
     *
     * @return string
     */
    public function getArticlePath()
    {
        $metadata = $this->getRepository()->getMetadata($this->getUrl());
        return isset($metadata['general']['articlePath'])
            ? $metadata['general']['articlePath']
            : '/wiki/$1';
    }

    /**
     * The URL path of the directory that contains index.php, with no trailing slash.
     * Defaults to '/w' which is the same as the normal WMF set-up.
     *
     * @link https://www.mediawiki.org/wiki/Manual:$wgScriptPath
     *
     * @return string
     */
    public function getScriptPath()
    {
        $metadata = $this->getRepository()->getMetadata($this->getUrl());
        return isset($metadata['general']['scriptPath'])
            ? $metadata['general']['scriptPath']
            : '/w';
    }

    /**
     * The URL path to index.php
     * Defaults to '/w/index.php' which is the same as the normal WMF set-up.
     *
     * @return string
     */
    public function getScript()
    {
        $metadata = $this->getRepository()->getMetadata($this->getUrl());
        return isset($metadata['general']['script'])
            ? $metadata['general']['script']
            : $this->getScriptPath() . '/index.php';
    }

    /**
     * Get this project's title, the human-language full title of the wiki (e.g. "English
     * Wikipedia" or
     */
    public function getTitle()
    {
        $metadata = $this->getRepository()->getMetadata($this->getUrl());
        return $metadata['general']['wikiName'].' ('.$this->getDomain().')';
    }

    /**
     * Get an array of this project's namespaces and their IDs.
     *
     * @return string[] Keys are IDs, values are names.
     */
    public function getNamespaces()
    {
        $metadata = $this->getRepository()->getMetadata($this->getUrl());
        return $metadata['namespaces'];
    }

    /**
     * Get the name of the page on this project that the user must create in order to opt in for
     * restricted statistics display.
     * @param User $user
     * @return string
     */
    public function userOptInPage(User $user)
    {
        $localPageName = 'User:' . $user->getUsername() . '/EditCounterOptIn.js';
        return $localPageName;
    }

    /**
     * Has a user opted in to having their restricted statistics displayed to anyone?
     * @param User $user
     * @return bool
     */
    public function userHasOptedIn(User $user)
    {
        // 1. First check to see if the whole project has opted in.
        if (!isset($this->metadata['opted_in'])) {
            $optedInProjects = $this->getRepository()->optedIn();
            $this->metadata['opted_in'] = in_array($this->getDatabaseName(), $optedInProjects);
        }
        if ($this->metadata['opted_in']) {
            return true;
        }

        // 2. Then see if the user has opted in on this project.
        $userNsId = 2;
        $localExists = $this->getRepository()
            ->pageHasContent($this, $userNsId, $this->userOptInPage($user));
        if ($localExists) {
            return true;
        }

        // 3. Lastly, see if they've opted in globally on the default project or Meta.
        $globalPageName = $user->getUsername() . '/EditCounterGlobalOptIn.js';
        $globalProject = $this->getRepository()->getGlobalProject();
        if ($globalProject instanceof Project) {
            $globalExists = $globalProject->getRepository()
                ->pageHasContent($globalProject, $userNsId, $globalPageName);
            if ($globalExists) {
                return true;
            }
        }

        return false;
    }
}
