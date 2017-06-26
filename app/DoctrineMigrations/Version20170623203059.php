<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170623203059 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $table = $schema->createTable('usage_timeline');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('date', 'date');
        $table->addColumn('tool', 'string');
        $table->addColumn('count', 'integer');
        $table->setPrimaryKey(['id']);
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $schema->dropTable('usage_timeline');
    }
}
