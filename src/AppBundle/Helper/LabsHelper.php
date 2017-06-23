<?php
/**
 * This file contains only the LabsHelper class.
 */

namespace AppBundle\Helper;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The Labs helper provides information relating to the WMF Labs installation of XTools.
 */
class LabsHelper
{
    /** @var string The current database name. */
    protected $dbName;

    /** @var Connection The database connection. */
    protected $client;

    /** @var ContainerInterface The DI container. */
    protected $container;

    /** @var string The project URL. */
    protected $url;

    /**
     * LabsHelper constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Is XTools connecting to WMF Labs?
     *
     * @return boolean
     */
    public function isLabs()
    {
        return (bool)$this->container->getParameter('app.is_labs');
    }

    /**
     * All mapping tables to environment-specific names, as specified in config/table_map.yml
     * Used for example to convert revision -> revision_replica
     * https://wikitech.wikimedia.org/wiki/Help:Tool_Labs/Database#Tables_for_revision_or_logging_queries_involving_user_names_and_IDs
     *
     * @param string $table  Table name
     * @param string $dbName Database name
     * @param string|null $table_extension Optional table extension, which will only get used if we're on labs.
     *
     * @return string Converted table name
     */
    public function getTable($table, $dbName = null, $table_extension = null)
    {
        // This is a workaround for a one-to-many mapping
        // as required by Labs.  We combine $table with
        // $table_extension in order to generate the new table name
        if ($this->isLabs() && $table_extension !== null) {
            $table = $table . "_" . $table_extension;
        }

        // Use the table specified in the table mapping configuration, if present.
        $mapped = false;
        if ($this->container->hasParameter("app.table.$table")) {
            $mapped = true;
            $table = $this->container->getParameter("app.table.$table");
        }

        // Figure out database name.
        // Use class variable for the database name if not set via function parameter.
        $dbNameActual = $dbName ? $dbName : $this->dbName;
        if ($this->isLabs() && substr($dbNameActual, -2) != '_p') {
            // Append '_p' if this is labs.
            $dbNameActual .= '_p';
        }

        return $dbNameActual ? "$dbNameActual.$table" : $table;
    }

    // TODO: figure out how to use Doctrine to query host 'tools-db'
}
