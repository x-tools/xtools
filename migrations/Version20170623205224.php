<?php

declare( strict_types = 1 );

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20170623205224 extends AbstractMigration {
	/**
	 * @param Schema $schema
	 */
	public function up( Schema $schema ): void {
		$table = $schema->createTable( 'usage_projects' );
		$table->addColumn( 'id', 'integer', [ 'autoincrement' => true ] );
		$table->addColumn( 'tool', 'string' );
		$table->addColumn( 'project', 'string' );
		$table->addColumn( 'count', 'integer' );
		$table->setPrimaryKey( [ 'id' ] );
	}

	/**
	 * @param Schema $schema
	 */
	public function down( Schema $schema ): void {
		$schema->dropTable( 'usage_projects' );
	}
}
