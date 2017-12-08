<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171208040821 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $table = $schema->createTable('usage_api_timeline');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('date', 'date');
        $table->addColumn('endpoint', 'string');
        $table->addColumn('count', 'integer');
        $table->setPrimaryKey(['id']);
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $schema->dropTable('usage_api_timeline');
    }
}
