<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230419221648 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Adds unique indexes to usage_api_timeline';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('CREATE UNIQUE INDEX date_endpoint ON usage_api_timeline (date, endpoint)');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('DROP INDEX date_endpoint ON usage_api_timeline');
    }
}
