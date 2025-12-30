<?php

declare( strict_types = 1 );

namespace App\Tests\Model;

use App\Model\Project;
use App\Model\User;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Tests\TestAdapter;
use DateTime;

/**
 * Tests for the User class.
 * @covers \App\Model\User
 */
class UserTest extends TestAdapter {
	protected UserRepository $userRepo;

	public function setUp(): void {
		$this->userRepo = $this->createMock( UserRepository::class );
	}

	/**
	 * A username should be given an initial capital letter in all cases.
	 */
	public function testUsernameHasInitialCapital(): void {
		$user = new User( $this->userRepo, 'lowercasename' );
		static::assertEquals( 'Lowercasename', $user->getUsername() );
		$user2 = new User( $this->userRepo, 'UPPERCASENAME' );
		static::assertEquals( 'UPPERCASENAME', $user2->getUsername() );
	}

	/**
	 * A user has an integer identifier on a project (and this can differ from project
	 * to project).
	 */
	public function testUserHasIdOnProject(): void {
		// Set up stub user and project repositories.
		$this->userRepo->expects( $this->once() )
			->method( 'getIdAndRegistration' )
			->willReturn( [
				'userId' => 12,
				'regDate' => '20170101000000',
			] );
		$projectRepo = $this->createMock( ProjectRepository::class );
		$projectRepo->expects( $this->once() )
			->method( 'getOne' )
			->willReturn( [ 'dbname' => 'testWiki' ] );

		// Make sure the user has the correct ID.
		$user = new User( $this->userRepo, 'TestUser' );
		$project = new Project( 'wiki.example.org' );
		$project->setRepository( $projectRepo );
		static::assertEquals( 12, $user->getId( $project ) );
	}

	/**
	 * Is a user an admin on a given project?
	 * @dataProvider isAdminProvider
	 * @param string $username The username.
	 * @param string[] $groups The groups to test.
	 * @param bool $isAdmin The desired result.
	 */
	public function testIsAdmin( string $username, array $groups, bool $isAdmin ): void {
		$this->userRepo->expects( $this->once() )
			->method( 'getUserRights' )
			->willReturn( $groups );
		$user = new User( $this->userRepo, $username );
		static::assertEquals( $isAdmin, $user->isAdmin( new Project( 'testWiki' ) ) );
	}

	/**
	 * Data for self::testIsAdmin().
	 * @return string[]
	 */
	public function isAdminProvider(): array {
		return [
			[ 'AdminUser', [ 'sysop', 'autopatrolled' ], true ],
			[ 'NormalUser', [ 'autopatrolled' ], false ],
		];
	}

	/**
	 * Get the expiry of the current block of a user on a given project
	 */
	public function testCountActiveBlocks(): void {
		$this->userRepo->expects( $this->once() )
			->method( 'countActiveBlocks' )
			->willReturn( 5 );
		$user = new User( $this->userRepo, 'TestUser' );

		$projectRepo = $this->createMock( ProjectRepository::class );
		$project = new Project( 'wiki.example.org' );
		$project->setRepository( $projectRepo );

		static::assertEquals( 5, $user->countActiveBlocks( $project ) );
	}

	/**
	 * Is the user currently blocked on a given project?
	 */
	public function testIsBlocked(): void {
		$this->userRepo->expects( $this->once() )
			->method( 'countActiveBlocks' )
			->willReturn( 1 );
		$user = new User( $this->userRepo, 'TestUser' );

		$projectRepo = $this->createMock( ProjectRepository::class );
		$project = new Project( 'wiki.example.org' );
		$project->setRepository( $projectRepo );

		static::assertTrue( $user->isBlocked( $project ) );
	}

	/**
	 * Registration date of the user
	 */
	public function testRegistrationDate(): void {
		$this->userRepo->expects( $this->once() )
			->method( 'getIdAndRegistration' )
			->willReturn( [
				'userId' => 12,
				'regDate' => '20170101000000',
			] );
		$user = new User( $this->userRepo, 'TestUser' );

		$projectRepo = $this->createMock( ProjectRepository::class );
		$project = new Project( 'wiki.example.org' );
		$project->setRepository( $projectRepo );

		$regDateTime = new DateTime( '2017-01-01 00:00:00' );
		static::assertEquals( $regDateTime, $user->getRegistrationDate( $project ) );
	}

	/**
	 * System edit count.
	 */
	public function testEditCount(): void {
		$this->userRepo->expects( $this->once() )
			->method( 'getEditCount' )
			->willReturn( 12345 );
		$user = new User( $this->userRepo, 'TestUser' );

		$projectRepo = $this->createMock( ProjectRepository::class );
		$projectRepo->expects( $this->once() )
			->method( 'getOne' )
			->willReturn( [ 'url' => 'https://wiki.example.org' ] );
		$project = new Project( 'wiki.example.org' );
		$project->setRepository( $projectRepo );

		static::assertEquals( 12345, $user->getEditCount( $project ) );

		// Should not call UserRepository::getEditCount() again.
		static::assertEquals( 12345, $user->getEditCount( $project ) );
	}

	/**
	 * Too many edits to process?
	 */
	public function testHasTooManyEdits(): void {
		$this->userRepo->expects( $this->once() )
			->method( 'getEditCount' )
			->willReturn( 123456789 );
		$this->userRepo->expects( $this->exactly( 3 ) )
			->method( 'maxEdits' )
			->willReturn( 250000 );
		$user = new User( $this->userRepo, 'TestUser' );

		$projectRepo = $this->createMock( ProjectRepository::class );
		$projectRepo->expects( $this->once() )
			->method( 'getOne' )
			->willReturn( [ 'url' => 'https://wiki.example.org' ] );
		$project = new Project( 'wiki.example.org' );
		$project->setRepository( $projectRepo );

		// User::maxEdits()
		static::assertEquals( 250000, $user->maxEdits() );

		// User::tooManyEdits()
		static::assertTrue( $user->hasTooManyEdits( $project ) );
	}

	/**
	 * IP-related functionality and methods.
	 */
	public function testIpMethods(): void {
		$user = new User( $this->userRepo, '192.168.0.0' );
		static::assertTrue( $user->isIP() );
		static::assertFalse( $user->isIpRange() );
		static::assertFalse( $user->isIPv6() );
		static::assertEquals( '192.168.0.0', $user->getUsernameIdent() );

		$user = new User( $this->userRepo, '74.24.52.13/20' );
		static::assertTrue( $user->isIP() );
		static::assertTrue( $user->isQueryableRange() );
		static::assertEquals( 'ipr-74.24.52.13/20', $user->getUsernameIdent() );

		$user = new User( $this->userRepo, '2600:387:0:80d::b0' );
		static::assertTrue( $user->isIP() );
		static::assertTrue( $user->isIPv6() );
		static::assertFalse( $user->isIpRange() );
		static::assertEquals( '2600:387:0:80D:0:0:0:B0', $user->getUsername() );
		static::assertEquals( '2600:387:0:80D:0:0:0:B0', $user->getUsernameIdent() );

		// Using 'ipr-' prefix, which should only apply in routing.
		$user = new User( $this->userRepo, 'ipr-2001:DB8::/32' );
		static::assertTrue( $user->isIP() );
		static::assertTrue( $user->isIPv6() );
		static::assertTrue( $user->isIpRange() );
		static::assertTrue( $user->isQueryableRange() );
		static::assertEquals( '2001:DB8:0:0:0:0:0:0/32', $user->getUsername() );
		static::assertEquals( '2001:db8::/32', $user->getPrettyUsername() );
		static::assertEquals( 'ipr-2001:DB8:0:0:0:0:0:0/32', $user->getUsernameIdent() );

		$user = new User( $this->userRepo, '2001:db8::/31' );
		static::assertTrue( $user->isIpRange() );
		static::assertFalse( $user->isQueryableRange() );

		$user = new User( $this->userRepo, 'Test' );
		static::assertFalse( $user->isIP() );
		static::assertFalse( $user->isIpRange() );
		static::assertEquals( 'Test', $user->getPrettyUsername() );
	}

	public function testGetIpSubstringFromCidr(): void {
		$user = new User( $this->userRepo, '2001:db8:abc:1400::/54' );
		static::assertEquals( '2001:DB8:ABC:1', $user->getIpSubstringFromCidr() );

		$user = new User( $this->userRepo, '174.197.128.0/18' );
		static::assertEquals( '174.197.1', $user->getIpSubstringFromCidr() );

		$user = new User( $this->userRepo, '174.197.128.0' );
		static::assertNull( $user->getIpSubstringFromCidr() );
	}

	public function testIsQueryableRange(): void {
		$user = new User( $this->userRepo, '2001:db8:abc:1400::/54' );
		static::assertTrue( $user->isQueryableRange() );

		$user = new User( $this->userRepo, '2001:db8:abc:1400::/5' );
		static::assertFalse( $user->isQueryableRange() );

		$user = new User( $this->userRepo, '2001:db8:abc:1400' );
		static::assertTrue( $user->isQueryableRange() );
	}

	/**
	 * From Core's PatternTest https://w.wiki/BZQH (GPL-2.0-or-later)
	 * @dataProvider provideIsTempUsername
	 * @param string $stringPattern
	 * @param string $name
	 * @param bool $expected
	 * @return void
	 */
	public function testIsTemp( string $stringPattern, string $name, bool $expected ): void {
		$project = $this->createMock( Project::class );
		$project->method( 'hasTempAccounts' )->willReturn( true );
		$project->method( 'getTempAccountPatterns' )->willReturn( [ $stringPattern ] );
		static::assertSame( $expected, User::isTempUsername( $project, $name ) );
	}

	/**
	 * From Core's PatternTest https://w.wiki/BZQH (GPL-2.0-or-later)
	 */
	public static function provideIsTempUsername(): array {
		return [
			'prefix mismatch' => [
				'pattern' => '*$1',
				'name' => 'Test',
				'expected' => false,
			],
			'prefix match' => [
				'pattern' => '*$1',
				'name' => '*Some user',
				'expected' => true,
			],
			'suffix only match' => [
				'pattern' => '$1*',
				'name' => 'Some user*',
				'expected' => true,
			],
			'suffix only mismatch' => [
				'pattern' => '$1*',
				'name' => 'Some user',
				'expected' => false,
			],
			'prefix and suffix match' => [
				'pattern' => '*$1*',
				'name' => '*Unregistered 123*',
				'expected' => true,
			],
			'prefix and suffix mismatch' => [
				'pattern' => '*$1*',
				'name' => 'Unregistered 123*',
				'expected' => false,
			],
			'prefix and suffix zero length match' => [
				'pattern' => '*$1*',
				'name' => '**',
				'expected' => true,
			],
			'prefix and suffix overlapping' => [
				'pattern' => '*$1*',
				'name' => '*',
				'expected' => false,
			],
		];
	}
}
