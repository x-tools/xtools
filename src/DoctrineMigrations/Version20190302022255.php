<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190302022255 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->addSql('CREATE UNIQUE INDEX date_tool ON usage_timeline (date, tool)');
        $this->addSql('CREATE UNIQUE INDEX project_tool ON usage_projects (project, tool)');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('DROP INDEX date_tool ON usage_timeline');
        $this->addSql('DROP INDEX project_tool ON usage_projects');
    }
}
