<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230412051210 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Creates the table that stores user sessions.';
    }

    public function up(Schema $schema) : void
    {
        // From https://symfony.com/doc/5.4/session.html#mariadb-mysql
        $this->addSql(
            "CREATE TABLE `sessions` (
                `sess_id` VARBINARY(128) NOT NULL PRIMARY KEY,
                `sess_data` BLOB NOT NULL,
                `sess_lifetime` INTEGER UNSIGNED NOT NULL,
                `sess_time` INTEGER UNSIGNED NOT NULL,
                INDEX `sessions_sess_lifetime_idx` (`sess_lifetime`)
            ) COLLATE utf8mb4_bin, ENGINE = InnoDB;"
        );
    }

    public function down(Schema $schema) : void
    {
        $schema->dropTable('sessions');
    }
}
