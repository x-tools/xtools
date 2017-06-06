<?php

namespace Xtools;

use Mediawiki\Api\MediawikiApi;

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
     * The project URL is the fully-qualified domain name, with protocol and trailing slash.
     *
     * @return string
     */
    public function getUrl()
    {
        return rtrim($this->getMetadata()['url'], '/') . '/';
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
            : '/wiki/';
    }

    /**
     * The URL path to index.php
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
            : '/w/index.php';
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
}
