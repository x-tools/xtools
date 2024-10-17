<?php

declare(strict_types = 1);

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
class UserRightsTest extends TestAdapter
{
    protected I18nHelper $i18n;
    protected User $user;
    protected UserRights $userRights;
    protected UserRightsRepository $userRightsRepo;
    protected UserRepository $userRepo;

    public function setUp(): void
    {
        $this->i18n = static::createClient()->getContainer()->get('app.i18n_helper');
        $project = new Project('test.example.org');
        $projectRepo = $this->getProjectRepo();
        $projectRepo->method('getMetadata')
            ->willReturn([
                'tempAccountPatterns' => ['~2$1'],
            ]);
        $project->setRepository($projectRepo);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->user = new User($this->userRepo, 'Testuser');
        $this->userRightsRepo = $this->createMock(UserRightsRepository::class);
        $this->userRights = new UserRights($this->userRightsRepo, $project, $this->user, $this->i18n);
    }

    /**
     * User rights changes.
     */
    public function testUserRightsChanges(): void
    {
        $this->userRightsRepo->expects(static::once())
            ->method('getRightsChanges')
            ->willReturn([[
                // Added: interface-admin, temporary.
                'log_id' => '92769185',
                'log_timestamp' => '20180826173045',
                'log_params' => 'a:4:{s:12:"4::oldgroups";a:3:{i:0;s:11:"abusefilter";i:1;s:9:"checkuser";i:2;s:5:'.
                    '"sysop";}s:12:"5::newgroups";a:4:{i:0;s:11:"abusefilter";i:1;s:9:"checkuser";i:2;s:5:"sysop";'.
                    'i:3;s:15:"interface-admin";}s:11:"oldmetadata";a:3:{i:0;a:1:{s:6:"expiry";N;}i:1;a:1:{s:6:"'.
                    'expiry";N;}i:2;a:1:{s:6:"expiry";N;}}s:11:"newmetadata";a:4:{i:0;a:1:{s:6:"expiry";N;}i:1;a:1'.
                    ':{s:6:"expiry";N;}i:2;a:1:{s:6:"expiry";N;}i:3;a:1:{s:6:"expiry";s:14:"20181025000000";}}}',
                'log_action' => 'rights',
                'performer' => 'Worm That Turned',
                'log_comment' => 'per [[Special:Diff/856641107]]',
                'type' => 'local',
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
            ], [
                // Old-school log entry, adds sysop.
                'log_id' => '140643',
                'log_timestamp' => '20141222034127',
                'log_comment' => 'per request',
                'log_params' => "\nsysop",
                'log_action' => 'rights',
                'performer' => 'Snowolf',
                'type' => 'meta',
            ],
            ]);

        /** @var MockObject|UserRepository $userRepo */
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('getIdAndRegistration')
            ->willReturn([
                'userId' => 5,
                'regDate' => '20180101000000',
            ]);
        $this->user->setRepository($userRepo);

        static::assertEquals([
            20181025000000 => [
                'logId' => '92769185',
                'performer' => 'Worm That Turned',
                'comment' => null,
                'added' => [],
                'removed' => ['interface-admin'],
                'grantType' => 'automatic',
                'type' => 'local',
            ],
            20180826173045 => [
                'logId' => '92769185',
                'performer' => 'Worm That Turned',
                'comment' => 'per [[Special:Diff/856641107]]',
                'added' => ['interface-admin'],
                'removed' => [],
                'grantType' => 'manual',
                'type' => 'local',
            ],
            20180108132858 => [
                'logId' => '210220',
                'performer' => 'MusikAnimal',
                'comment' => null,
                'added' => [],
                'removed' => ['ipblock-exempt', 'filemover'],
                'grantType' => 'automatic',
                'type' => 'local',
            ],
            20180108132810 => [
                'logId' => '210221',
                'performer' => 'MusikAnimal',
                'comment' => '',
                'added' => [],
                'removed' => ['templateeditor'],
                'grantType' => 'manual',
                'type' => 'local',
            ],
            20180108132758 => [
                'logId' => '210220',
                'performer' => 'MusikAnimal',
                'comment' => '',
                'added' => ['ipblock-exempt', 'filemover', 'templateeditor'],
                'removed' => [],
                'grantType' => 'manual',
                'type' => 'local',
            ],
            20150716002614 => [
                'logId' => '155321',
                'performer' => 'Cyberpower678',
                'comment' => 'Per user request.',
                'added' => ['bureaucrat'],
                'removed' => ['rollbacker'],
                'grantType' => 'manual',
                'type' => 'meta',
            ],
            20141222034127 => [
                'logId' => '140643',
                'performer' => 'Snowolf',
                'comment' => 'per request',
                'added' => ['sysop'],
                'removed' => [],
                'grantType' => 'manual',
                'type' => 'meta',
            ],
        ], $this->userRights->getRightsChanges());

        $this->userRightsRepo->expects(static::once())
            ->method('getGlobalRightsChanges')
            ->willReturn([[
                'log_id' => '140643',
                'log_timestamp' => '20141222034127',
                'log_comment' => 'per request',
                'log_params' => "\nsysop",
                'log_action' => 'gblrights',
                'performer' => 'Snowolf',
                'type' => 'global',
            ]]);

        static::assertEquals([
            20141222034127 => [
                'logId' => '140643',
                'performer' => 'Snowolf',
                'comment' => 'per request',
                'added' => ['sysop'],
                'removed' => [],
                'grantType' => 'manual',
                'type' => 'global',
            ],
        ], $this->userRights->getGlobalRightsChanges());

        /** @var MockObject|UserRepository $userRepo */
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects(static::once())
            ->method('getUserRights')
            ->willReturn(['sysop', 'bureaucrat']);
        $userRepo->expects(static::once())
            ->method('getGlobalUserRights')
            ->willReturn(['sysop']);
        $this->user->setRepository($userRepo);

        // Current rights.
        static::assertEquals(
            ['sysop', 'bureaucrat'],
            $this->userRights->getRightsStates()['local']['current']
        );

        // Former rights.
        static::assertEquals(
            ['interface-admin', 'ipblock-exempt', 'filemover', 'templateeditor', 'rollbacker'],
            $this->userRights->getRightsStates()['local']['former']
        );

        // Admin status.
        static::assertEquals('current', $this->userRights->getAdminStatus());
    }
}
