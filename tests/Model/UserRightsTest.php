<?php

declare( strict_types = 1 );

namespace App\Tests\Model;

use App\Helper\I18nHelper;
use App\Model\Project;
use App\Model\User;
use App\Model\UserRights;
use App\Repository\UserRepository;
use App\Repository\UserRightsRepository;
use App\Tests\TestAdapter;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \App\Model\UserRights
 */
class UserRightsTest extends TestAdapter {
	protected I18nHelper $i18n;
	protected User $user;
	protected UserRights $userRights;
	protected UserRightsRepository $userRightsRepo;
	protected UserRepository $userRepo;
	protected Project $project;

	public function setUp(): void {
		$this->i18n = $this->createMock( I18nHelper::class );
		$this->i18n->expects( static::any() )
			->method( 'getLang' )
			->willReturn( 'en' );
		// static::createClient()->getContainer()->get('app.i18n_helper');
		$project = new Project( 'test.example.org' );
		$projectRepo = $this->getProjectRepo();
		$projectRepo->method( 'getMetadata' )
			->willReturn( [
				'tempAccountPatterns' => [ '~2$1' ],
			] );
		$project->setRepository( $projectRepo );
		$this->project = $project;
		$this->userRepo = $this->createMock( UserRepository::class );
		$this->user = new User( $this->userRepo, 'Testuser' );
		$this->userRightsRepo = $this->createMock( UserRightsRepository::class );
		$this->userRights = new UserRights( $this->userRightsRepo, $project, $this->user, $this->i18n );
	}

	/**
	 * User rights changes.
	 */
	public function testUserRightsChanges(): void {
		$this->userRightsRepo->expects( static::once() )
			->method( 'getRightsChanges' )
			->willReturn( [ [
				// Added: interface-admin, temporary.
				'log_id' => '92769185',
				'log_timestamp' => '20180826173045',
				'log_params' => 'a:4:{s:12:"4::oldgroups";a:3:{i:0;s:11:"abusefilter";i:1;s:9:"checkuser";i:2;s:5:' .
					'"sysop";}s:12:"5::newgroups";a:4:{i:0;s:11:"abusefilter";i:1;s:9:"checkuser";i:2;s:5:"sysop";' .
					'i:3;s:15:"interface-admin";}s:11:"oldmetadata";a:3:{i:0;a:1:{s:6:"expiry";N;}i:1;a:1:{s:6:"' .
					'expiry";N;}i:2;a:1:{s:6:"expiry";N;}}s:11:"newmetadata";a:4:{i:0;a:1:{s:6:"expiry";N;}i:1;a:1' .
					':{s:6:"expiry";N;}i:2;a:1:{s:6:"expiry";N;}i:3;a:1:{s:6:"expiry";s:14:"20181025000000";}}}',
				'log_action' => 'rights',
				'performer' => 'Worm That Turned',
				'log_comment' => 'per [[Special:Diff/856641107]]',
				'type' => 'local',
				'log_deleted' => '0',
			], [
				// Removed: ipblock-exempt, filemover.
				'log_id' => '210221',
				'log_timestamp' => '20180108132810',
				'log_comment' => '',
				'log_params' => 'a:4:{s:12:"4::oldgroups";a:6:{i:0;s:10:"bureaucrat";i:1;s:9:' .
					'"filemover";i:2;s:6:"import";i:3;s:14:"ipblock-exempt";i:4;s:5:"sysop";i:5;' .
					's:14:"templateeditor";}s:12:"5::newgroups";a:5:{i:0;s:10:"bureaucrat";i:1;s:9:' .
					'"filemover";i:2;s:6:"import";i:3;s:14:"ipblock-exempt";i:4;s:5:"sysop";}s:11:' .
					'"oldmetadata";a:6:{i:0;a:1:{s:6:"expiry";N;}i:1;a:1:{s:6:"expiry";s:14:"' .
					'20180108132858";}i:2;a:1:{s:6:"expiry";N;}i:3;a:1:{s:6:"expiry";s:14:"20180108132858"' .
					';}i:4;a:1:{s:6:"expiry";N;}i:5;a:1:{s:6:"expiry";N;}}s:11:"newmetadata";a:5:{i:0;' .
					'a:1:{s:6:"expiry";N;}i:1;a:1:{s:6:"expiry";s:14:"20180108132858";}i:2;a:1:{s:6:' .
					'"expiry";N;}i:3;a:1:{s:6:"expiry";s:14:"20180108132858";}i:4;a:1:{s:6:"expiry";N;}}}',
				'log_action' => 'rights',
				'performer' => 'MusikAnimal',
				'type' => 'local',
				'log_deleted' => '0',
			], [
				// Added: ipblock-exempt, filemover, templateeditor.
				'log_id' => '210220',
				'log_timestamp' => '20180108132758',
				'log_comment' => '',
				'log_params' => 'a:4:{s:12:"4::oldgroups";a:3:{i:0;s:10:"bureaucrat";i:1;s:6:"import";' .
					'i:2;s:5:"sysop";}s:12:"5::newgroups";a:6:{i:0;s:10:"bureaucrat";i:1;s:6:"import";' .
					'i:2;s:5:"sysop";i:3;s:14:"ipblock-exempt";i:4;s:9:"filemover";i:5;s:14:"templateeditor";}' .
					's:11:"oldmetadata";a:3:{i:0;a:1:{s:6:"expiry";N;}i:1;a:1:{s:6:"expiry";N;}i:2;a:1:' .
					'{s:6:"expiry";N;}}s:11:"newmetadata";a:6:{i:0;a:1:{s:6:"expiry";N;}i:1;a:1:{s:6:' .
					'"expiry";N;}i:2;a:1:{s:6:"expiry";N;}i:3;a:1:{s:6:"expiry";s:14:"20180108132858";}' .
					'i:4;a:1:{s:6:"expiry";s:14:"20180108132858";}i:5;a:1:{s:6:"expiry";N;}}}',
				'log_action' => 'rights',
				'performer' => 'MusikAnimal',
				'type' => 'local',
				'log_deleted' => '0',
			], [
				// Added: bureaucrat; Removed: rollbacker.
				'log_id' => '155321',
				'log_timestamp' => '20150716002614',
				'log_comment' => 'Per user request.',
				'log_params' => 'a:2:{s:12:"4::oldgroups";a:3:{i:0;s:8:"reviewer";i:1;s:10:"rollbacker"' .
					';i:2;s:5:"sysop";}s:12:"5::newgroups";a:3:{i:0;s:8:"reviewer";i:1;s:5:"sysop";i:2;' .
					's:10:"bureaucrat";}}',
				'log_action' => 'rights',
				'performer' => 'Cyberpower678',
				'type' => 'meta',
				'log_deleted' => '0',
			], [
				// Old-school log entry, adds sysop.
				'log_id' => '140643',
				'log_timestamp' => '20141222034127',
				'log_comment' => 'per request',
				'log_params' => "\nsysop",
				'log_action' => 'rights',
				'performer' => 'Snowolf',
				'type' => 'meta',
				'log_deleted' => '0',
			], [
				// Comment deleted
				'log_id' => '168397975',
				'log_timestamp' => '20250310044508',
				'log_comment' => null,
				'log_params' => null,
				'log_action' => 'rights',
				'performer' => 'Queen of Hearts',
				'type' => 'local',
				'log_deleted' => '2',
			],
			] );
		$this->userRightsRepo->expects( static::once() )
			->method( 'getAutoConfirmedAgeAndCount' )
			->willReturn( [
				'wgAutoConfirmAge' => 1, // 1 second
				'wgAutoConfirmCount' => 1, // 1 edit
			] );
		$this->userRightsRepo->expects( static::once() )
			->method( 'getNumEditsByTimestamp' )
			->willReturn( 2 );
		$this->userRightsRepo->expects( static::once() )
			->method( 'getRightsNames' )
			->willReturn( [ 'sysop' => 'Administrator' ] );

		/** @var MockObject|UserRepository $userRepo */
		$userRepo = $this->createMock( UserRepository::class );
		$userRepo->method( 'getIdAndRegistration' )
			->willReturn( [
				'userId' => 5,
				'regDate' => '20180101000000',
			] );
		$this->user->setRepository( $userRepo );

		static::assertEquals( 20180101000001, $this->userRights->getAutoConfirmedTimeStamp() );
		static::assertEquals( [
			20180101000001 => [
				'logId' => null,
				'performer' => null,
				'comment' => null,
				'added' => [ 'autoconfirmed' ],
				'removed' => [],
				'grantType' => 'automatic',
				'type' => 'local',
				'paramsDeleted' => false,
				'commentDeleted' => false,
				'performerDeleted' => false,
			],
			20181025000000 => [
				'logId' => '92769185',
				'performer' => 'Worm That Turned',
				'comment' => null,
				'added' => [],
				'removed' => [ 'interface-admin' ],
				'grantType' => 'automatic',
				'type' => 'local',
				'paramsDeleted' => false,
				'commentDeleted' => false,
				'performerDeleted' => false,
			],
			20180826173045 => [
				'logId' => '92769185',
				'performer' => 'Worm That Turned',
				'comment' => 'per [[Special:Diff/856641107]]',
				'added' => [ 'interface-admin' ],
				'removed' => [],
				'grantType' => 'manual',
				'type' => 'local',
				'paramsDeleted' => false,
				'commentDeleted' => false,
				'performerDeleted' => false,
			],
			20180108132858 => [
				'logId' => '210220',
				'performer' => 'MusikAnimal',
				'comment' => null,
				'added' => [],
				'removed' => [ 'ipblock-exempt', 'filemover' ],
				'grantType' => 'automatic',
				'type' => 'local',
				'paramsDeleted' => false,
				'commentDeleted' => false,
				'performerDeleted' => false,
			],
			20180108132810 => [
				'logId' => '210221',
				'performer' => 'MusikAnimal',
				'comment' => '',
				'added' => [],
				'removed' => [ 'templateeditor' ],
				'grantType' => 'manual',
				'type' => 'local',
				'paramsDeleted' => false,
				'commentDeleted' => false,
				'performerDeleted' => false,
			],
			20180108132758 => [
				'logId' => '210220',
				'performer' => 'MusikAnimal',
				'comment' => '',
				'added' => [ 'ipblock-exempt', 'filemover', 'templateeditor' ],
				'removed' => [],
				'grantType' => 'manual',
				'type' => 'local',
				'paramsDeleted' => false,
				'commentDeleted' => false,
				'performerDeleted' => false,
			],
			20150716002614 => [
				'logId' => '155321',
				'performer' => 'Cyberpower678',
				'comment' => 'Per user request.',
				'added' => [ 'bureaucrat' ],
				'removed' => [ 'rollbacker' ],
				'grantType' => 'manual',
				'type' => 'meta',
				'paramsDeleted' => false,
				'commentDeleted' => false,
				'performerDeleted' => false,
			],
			20141222034127 => [
				'logId' => '140643',
				'performer' => 'Snowolf',
				'comment' => 'per request',
				'added' => [ 'sysop' ],
				'removed' => [],
				'grantType' => 'manual',
				'type' => 'meta',
				'paramsDeleted' => false,
				'commentDeleted' => false,
				'performerDeleted' => false,
			],
			20250310044508 => [
				'logId' => '168397975',
				'performer' => 'Queen of Hearts',
				'comment' => null,
				'added' => [],
				'removed' => [],
				'grantType' => 'manual',
				'type' => 'local',
				'paramsDeleted' => true,
				'commentDeleted' => true,
				'performerDeleted' => false,
			],
		], $this->userRights->getRightsChanges() );

		$this->userRightsRepo->expects( static::once() )
			->method( 'getGlobalRightsChanges' )
			->willReturn( [ [
				'log_id' => '140643',
				'log_timestamp' => '20141222034127',
				'log_comment' => 'per request',
				'log_params' => "\nsysop",
				'log_action' => 'gblrights',
				'performer' => 'Snowolf',
				'type' => 'global',
				'log_deleted' => '0',
			] ] );

		static::assertEquals( [
			20141222034127 => [
				'logId' => '140643',
				'performer' => 'Snowolf',
				'comment' => 'per request',
				'added' => [ 'sysop' ],
				'removed' => [],
				'grantType' => 'manual',
				'type' => 'global',
				'paramsDeleted' => false,
				'commentDeleted' => false,
				'performerDeleted' => false,
			],
		], $this->userRights->getGlobalRightsChanges() );

		/** @var MockObject|UserRepository $userRepo */
		$userRepo = $this->createMock( UserRepository::class );
		$userRepo->expects( static::once() )
			->method( 'getUserRights' )
			->willReturn( [ 'sysop', 'bureaucrat' ] );
		$userRepo->expects( static::once() )
			->method( 'getGlobalUserRights' )
			->willReturn( [ 'sysop' ] );
		$this->user->setRepository( $userRepo );

		// Global rights and changes.
		static::assertEquals( [
			'current' => [ 'sysop' ],
			'former' => [],
		], $this->userRights->getGlobalRightsStates() );

		// Current rights.
		static::assertEquals(
			[ 'sysop', 'bureaucrat', 'autoconfirmed' ],
			$this->userRights->getRightsStates()['local']['current']
		);

		// Former rights.
		static::assertEquals(
			[ 'interface-admin', 'ipblock-exempt', 'filemover', 'templateeditor', 'rollbacker' ],
			$this->userRights->getRightsStates()['local']['former']
		);

		// Rights names.
		static::assertEquals( 'Administrator', $this->userRights->getRightsName( 'sysop' ) );
		// Missing key, and ensure caching.
		static::assertEquals( 'example', $this->userRights->getRightsName( 'example' ) );
	}

	/**
	 * Test various edge cases and unexpected incidents during log processsing
	 * @dataProvider edgeCaseProvider
	 * @param $logData
	 * @param $hasImpossibleLogs
	 * @param $result
	 */
	public function testChangesEdgeCases(
		array $logData,
		bool $hasImpossibleLogs,
		array $result
	): void {
		$this->userRightsRepo->expects( static::once() )
			->method( 'getRightsChanges' )
			->willReturn( $logData );
		static::assertEquals( $result, $this->userRights->getRightsChanges() );
		static::assertEquals( $hasImpossibleLogs, $this->userRights->hasImpossibleLogs() );
	}

	public function edgeCaseProvider(): array {
		return [ [ // Dataset #0: Expiry modification
			[
				[ // Temporary intadmin grant until timestamp 4
					'log_id' => '1234',
					'log_timestamp' => '0',
					'log_params' => 'a:4:{s:12:"4::oldgroups";a:0:{}s:12:"5::newgroups";a:1:{i:0;s:15:' .
						'"interface-admin";}s:11:"oldmetadata";a:0:{}s:11:"newmetadata";a:1:{i:0;a:1:' .
						'{s:6:"expiry";s:1:"8";}}}',
					'log_action' => 'rights',
					'performer' => 'Random',
					'log_comment' => 'One',
					'type' => 'local',
					'log_deleted' => 0,
				],
				[ // One second before, extended until 8
					'log_id' => '5678',
					'log_timestamp' => '3',
					'log_params' => 'a:4:{s:12:"4::oldgroups";a:1:{i:0;s:15:"interface-admin";}s:12:"5::newgroups";' .
						'a:1:{i:0;s:15:"interface-admin";}s:11:"oldmetadata";a:1:{i:0;a:1:{s:6:"expiry";s:1:"4";}}s:' .
						'11:"newmetadata";a:1:{i:0;a:1:{s:6:"expiry";s:1:"8";}}}',
					'log_action' => 'rights',
					'performer' => 'Random',
					'log_comment' => 'Two!',
					'type' => 'local',
					'log_deleted' => 0,
				],
			],
			false,
			[
				0 => [
					'logId' => '1234',
					'performer' => 'Random',
					'comment' => 'One',
					'added' => [ 'interface-admin' ],
					'removed' => [],
					'grantType' => 'manual',
					'type' => 'local',
					'paramsDeleted' => false,
					'commentDeleted' => false,
					'performerDeleted' => false,
				],
				3 => [
					'logId' => '5678',
					'performer' => 'Random',
					'comment' => 'Two!',
					'added' => [ 'interface-admin' ],
					'removed' => [],
					'grantType' => 'manual',
					'type' => 'local',
					'paramsDeleted' => false,
					'commentDeleted' => false,
					'performerDeleted' => false,
				],
				8 => [
					'logId' => '5678',
					'performer' => 'Random',
					'comment' => null,
					'added' => [],
					'removed' => [ 'interface-admin' ],
					'grantType' => 'automatic',
					'type' => 'local',
					'paramsDeleted' => false,
					'commentDeleted' => false,
					'performerDeleted' => false,
				],
			],
		], [ // Dataset #1: Impossible logs
			[
				[ // removal of sysop
					'log_id' => '1234',
					'log_timestamp' => '0',
					'log_params' => "sysop\n",
					'log_action' => 'rights',
					'performer' => 'Random',
					'log_comment' => '...',
					'type' => 'local',
					'log_deleted' => 0,
				],
			],
			true,
			[
				[
					'logId' => '1234',
					'performer' => 'Random',
					'comment' => '...',
					'added' => [],
					'removed' => [ 'sysop' ],
					'grantType' => 'manual',
					'type' => 'local',
					'paramsDeleted' => false,
					'commentDeleted' => false,
					'performerDeleted' => false,
				],
			],
		], [ // Dataset #2: everything revdeleted
			[
				[ // removal of sysop
					'log_id' => '1234',
					'log_timestamp' => '0',
					'log_params' => null,
					'log_action' => 'rights',
					'performer' => null,
					'log_comment' => null,
					'type' => 'local',
					'log_deleted' => 7,
				],
			],
			false,
			[
				0 => [
					'logId' => '1234',
					'performer' => null,
					'comment' => null,
					'added' => [],
					'removed' => [],
					'grantType' => 'manual',
					'type' => 'local',
					'paramsDeleted' => true,
					'commentDeleted' => true,
					'performerDeleted' => true,
				],
			],
		], [ // Dataset #3: (none)s to splice out
			[
				[ // none in old
					'log_id' => '1234',
					'log_timestamp' => '0',
					'log_params' => "(none)\nsysop",
					'log_action' => 'rights',
					'performer' => 'Random',
					'log_comment' => '...',
					'type' => 'local',
					'log_deleted' => 0,
				],
				[ // none in new
					'log_id' => '5678',
					'log_timestamp' => '1',
					'log_params' => "sysop\n(none)",
					'log_action' => 'rights',
					'performer' => 'Random',
					'log_comment' => '...',
					'type' => 'local',
					'log_deleted' => 0,
				],
			],
			false,
			[
				0 => [
					'logId' => '1234',
					'performer' => 'Random',
					'comment' => '...',
					'added' => [ 'sysop' ],
					'removed' => [],
					'grantType' => 'manual',
					'type' => 'local',
					'paramsDeleted' => false,
					'commentDeleted' => false,
					'performerDeleted' => false,
				],
				1 => [
					'logId' => '5678',
					'performer' => 'Random',
					'comment' => '...',
					'added' => [],
					'removed' => [ 'sysop' ],
					'grantType' => 'manual',
					'type' => 'local',
					'paramsDeleted' => false,
					'commentDeleted' => false,
					'performerDeleted' => false,
				],
			],
		], [ // Dataset #4: removing pending auto removals on manual removal
			[
				[ // Temporary intadmin grant until timestamp 4
					'log_id' => '1234',
					'log_timestamp' => '0',
					'log_params' => 'a:4:{s:12:"4::oldgroups";a:0:{}s:12:"5::newgroups";a:1:{i:0;s:15:' .
						'"interface-admin";}s:11:"oldmetadata";a:0:{}s:11:"newmetadata";a:1:{i:0;a:1:' .
						'{s:6:"expiry";s:1:"8";}}}',
					'log_action' => 'rights',
					'performer' => 'Random',
					'log_comment' => 'One',
					'type' => 'local',
					'log_deleted' => 0,
				],
				[ // One second before, removed manually
					'log_id' => '5678',
					'log_timestamp' => '3',
					'log_params' => 'a:4:{s:12:"4::oldgroups";a:1:{i:0;s:15:"interface-admin";}s:12:"5::newgroups";' .
						'a:0:{}s:11:"oldmetadata";a:1:{i:0;a:1:{s:6:"expiry";s:1:"4";}}s:11:"newmetadata";a:0:{}}',
					'log_action' => 'rights',
					'performer' => 'Random',
					'log_comment' => 'Two!',
					'type' => 'local',
					'log_deleted' => 0,
				],
			],
			false,
			[
				0 => [
					'logId' => '1234',
					'performer' => 'Random',
					'comment' => 'One',
					'added' => [ 'interface-admin' ],
					'removed' => [],
					'grantType' => 'manual',
					'type' => 'local',
					'paramsDeleted' => false,
					'commentDeleted' => false,
					'performerDeleted' => false,
				],
				3 => [
					'logId' => '5678',
					'performer' => 'Random',
					'comment' => 'Two!',
					'added' => [],
					'removed' => [ 'interface-admin' ],
					'grantType' => 'manual',
					'type' => 'local',
					'paramsDeleted' => false,
					'commentDeleted' => false,
					'performerDeleted' => false,
				],
			],
		] ];
	}

	/**
	 * Admin status
	 * @dataProvider adminStatusProvider
	 * @param array $currentRights
	 * @param array $rightsChanges
	 * @param string|bool $adminStatus
	 */
	public function testAdminStatus(
		array $currentRights,
		array $rightsChanges,
		$adminStatus
	): void {
		$user = $this->createMock( User::class );
		$user->expects( static::once() )
			->method( 'getUserRights' )
			->willReturn( $currentRights );
		$this->userRightsRepo->expects( static::once() )
			->method( 'getRightsChanges' )
			->willReturn( $rightsChanges );
		$userRights = new UserRights( $this->userRightsRepo, $this->project, $user, $this->i18n );
		static::assertEquals( $adminStatus, $userRights->getAdminStatus() );
	}

	public function adminStatusProvider(): array {
		return [
			[
				[ 'sysop' ],
				[],
				'current',
			],
			[
				[],
				[
					[
						'log_timestamp' => 0,
						'log_action' => '',
						'log_id' => 3,
						'log_params' => "\nsysop",
						'log_comment' => null,
						'performer' => 'Ghost',
						'type' => '',
						'log_deleted' => 2,
					],
					[
						'log_timestamp' => 1,
						'log_action' => '',
						'log_id' => 4,
						'log_params' => "sysop\n",
						'log_comment' => null,
						'performer' => 'Ghost',
						'type' => '',
						'log_deleted' => 2,
					],
				],
				'former',
			],
			[
				[],
				[],
				false,
			],
		];
	}

	/**
	 * Test autoconfirmed calculations
	 * @dataProvider autoconfirmedTimestampProvider
	 * @param bool $isTemp
	 * @param array|null $thresholds
	 * @param \DateTime|null $regDate
	 * @param int $editsByAcDate
	 * @param \DateTime|false $nthEditTimestamp
	 * @param \DateTime|false $resTimestamp
	 */
	public function testAutoconfirmedTimestamp(
		bool $isTemp,
		?array $thresholds,
		$regDate,
		int $editsByAcDate,
		$nthEditTimestamp,
		$resTimestamp
	): void {
		$user = $this->createMock( User::class );
		$user->expects( static::once() )
			->method( 'isTemp' )
			->willReturn( $isTemp );
		$this->userRightsRepo->expects( static::any() )
			->method( 'getAutoconfirmedAgeAndCount' )
			->willReturn( $thresholds );
		$user->expects( static::any() )
			->method( 'getRegistrationDate' )
			->willReturn( $regDate );
		$this->userRightsRepo->expects( static::any() )
			->method( 'getNumEditsByTimestamp' )
			->willReturn( $editsByAcDate );
		$this->userRightsRepo->expects( static::any() )
			->method( 'getNthEditTimestamp' )
			->willReturn( $nthEditTimestamp );
		$userRights = new UserRights( $this->userRightsRepo, $this->project, $user, $this->i18n );
		static::assertEquals( $resTimestamp, $userRights->getAutoconfirmedTimestamp() );
	}

	public function autoconfirmedTimestampProvider(): array {
		$stamp = static fn ( $s ) => strval( 20250101000000 + $s );
		$time = static fn ( $s ) => new \DateTime( $stamp( $s ) );
		return [
			// Dataset #0: temporary used
			[ true, null, null, 3, $time( 2 ), false ],
			// Dataset #1: null thresholds
			[ false, null, null, 3, $time( 2 ), false ],
			// Dataset #2: null registration date
			[ false, [ 'wgAutoConfirmAge' => 4, 'wgAutoConfirmCount' => 2 ], null, 3, $time( 2 ), false ],
			// Dataset #3: got the required edits before required age
			[ false, [ 'wgAutoConfirmAge' => 4, 'wgAutoConfirmCount' => 2 ], $time( 0 ), 3, $time( 2 ), $stamp( 4 ) ],
			// Dataset #4: got the required edits after required age
			[ false, [ 'wgAutoConfirmAge' => 4, 'wgAutoConfirmCount' => 2 ], $time( 0 ), 1, $time( 6 ), $stamp( 6 ) ],
			// Dataset #5: never got the required edits
			[ false, [ 'wgAutoConfirmAge' => 4, 'wgAutoConfirmCount' => 2 ], $time( 0 ), 1, false, false ],
		];
	}
}
