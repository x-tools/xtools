<?php
/**
 * This file contains only the UserTest class.
 */

declare(strict_types = 1);

namespace App\Tests\Model;

use App\Model\Project;
use App\Model\User;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Tests\TestAdapter;
use DateTime;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for the User class.
 */
class UserTest extends TestAdapter
{

    /**
     * A username should be given an initial capital letter in all cases.
     */
    public function testUsernameHasInitialCapital(): void
    {
        $user = new User('lowercasename');
        static::assertEquals('Lowercasename', $user->getUsername());
        $user2 = new User('UPPERCASENAME');
        static::assertEquals('UPPERCASENAME', $user2->getUsername());
    }

    /**
     * A user has an integer identifier on a project (and this can differ from project
     * to project).
     */
    public function testUserHasIdOnProject(): void
    {
        // Set up stub user and project repositories.
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getIdAndRegistration')
            ->willReturn([
                'userId' => 12,
                'regDate' => '20170101000000',
            ]);
        $projectRepo = $this->createMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getOne')
            ->willReturn(['dbname' => 'testWiki']);

        // Make sure the user has the correct ID.
        $user = new User('TestUser');
        $user->setRepository($userRepo);
        $project = new Project('wiki.example.org');
        $project->setRepository($projectRepo);
        static::assertEquals(12, $user->getId($project));
    }

    /**
     * Is a user an admin on a given project?
     * @dataProvider isAdminProvider
     * @param string $username The username.
     * @param string[] $groups The groups to test.
     * @param bool $isAdmin The desired result.
     */
    public function testIsAdmin(string $username, array $groups, bool $isAdmin): void
    {
        /** @var UserRepository|MockObject $userRepo */
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getUserRights')
            ->willReturn($groups);
        $user = new User($username);
        $user->setRepository($userRepo);
        static::assertEquals($isAdmin, $user->isAdmin(new Project('testWiki')));
    }

    /**
     * Data for self::testIsAdmin().
     * @return string[]
     */
    public function isAdminProvider(): array
    {
        return [
            ['AdminUser', ['sysop', 'autopatrolled'], true],
            ['NormalUser', ['autopatrolled'], false],
        ];
    }

    /**
     * Get the expiry of the current block of a user on a given project
     */
    public function testExpiry(): void
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getBlockExpiry')
            ->willReturn('20500601000000');
        $user = new User('TestUser');
        $user->setRepository($userRepo);

        $projectRepo = $this->createMock(ProjectRepository::class);
        $project = new Project('wiki.example.org');
        $project->setRepository($projectRepo);

        static::assertEquals(new DateTime('20500601000000'), $user->getBlockExpiry($project));
    }

    /**
     * Is the user currently blocked on a given project?
     */
    public function testIsBlocked(): void
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getBlockExpiry')
            ->willReturn('infinity');
        $user = new User('TestUser');
        $user->setRepository($userRepo);

        $projectRepo = $this->createMock(ProjectRepository::class);
        $project = new Project('wiki.example.org');
        $project->setRepository($projectRepo);

        static::assertEquals(true, $user->isBlocked($project));
    }

    /**
     * Registration date of the user
     */
    public function testRegistrationDate(): void
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getIdAndRegistration')
            ->willReturn([
                'userId' => 12,
                'regDate' => '20170101000000',
            ]);
        $user = new User('TestUser');
        $user->setRepository($userRepo);

        $projectRepo = $this->createMock(ProjectRepository::class);
        $project = new Project('wiki.example.org');
        $project->setRepository($projectRepo);

        $regDateTime = new DateTime('2017-01-01 00:00:00');
        static::assertEquals($regDateTime, $user->getRegistrationDate($project));
    }

    /**
     * System edit count.
     */
    public function testEditCount(): void
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getEditCount')
            ->willReturn('12345');
        $user = new User('TestUser');
        $user->setRepository($userRepo);

        $projectRepo = $this->createMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getOne')
            ->willReturn(['url' => 'https://wiki.example.org']);
        $project = new Project('wiki.example.org');
        $project->setRepository($projectRepo);

        static::assertEquals(12345, $user->getEditCount($project));

        // Should not call UserRepository::getEditCount() again.
        static::assertEquals(12345, $user->getEditCount($project));
    }

    /**
     * Too many edits to process?
     */
    public function testHasTooManyEdits(): void
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getEditCount')
            ->willReturn('123456789');
        $userRepo->expects($this->exactly(3))
            ->method('maxEdits')
            ->willReturn(250000);
        $user = new User('TestUser');
        $user->setRepository($userRepo);

        $projectRepo = $this->createMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getOne')
            ->willReturn(['url' => 'https://wiki.example.org']);
        $project = new Project('wiki.example.org');
        $project->setRepository($projectRepo);

        // User::maxEdits()
        static::assertEquals(250000, $user->maxEdits());

        // User::tooManyEdits()
        static::assertTrue($user->hasTooManyEdits($project));
    }

    /**
     * IP-related functionality and methods.
     */
    public function testIpMethods(): void
    {
        $user = new User('192.168.0.0');
        static::assertTrue($user->isAnon());
        static::assertFalse($user->isIpRange());
        static::assertFalse($user->isIPv6());
        static::assertEquals('192.168.0.0', $user->getUsernameIdent());

        $user = new User('74.24.52.13/20');
        static::assertTrue($user->isAnon());
        static::assertTrue($user->isQueryableRange());
        static::assertEquals('ipr-74.24.52.13/20', $user->getUsernameIdent());

        $user = new User('2600:387:0:80d::b0');
        static::assertTrue($user->isAnon());
        static::assertTrue($user->isIPv6());
        static::assertFalse($user->isIpRange());
        static::assertEquals('2600:387:0:80D:0:0:0:B0', $user->getUsername());
        static::assertEquals('2600:387:0:80D:0:0:0:B0', $user->getUsernameIdent());

        // Using 'ipr-' prefix, which should only apply in routing.
        $user = new User('ipr-2001:DB8::/32');
        static::assertTrue($user->isAnon());
        static::assertTrue($user->isIPv6());
        static::assertTrue($user->isIpRange());
        static::assertTrue($user->isQueryableRange());
        static::assertEquals('2001:DB8:0:0:0:0:0:0/32', $user->getUsername());
        static::assertEquals('2001:db8::/32', $user->getPrettyUsername());
        static::assertEquals('ipr-2001:DB8:0:0:0:0:0:0/32', $user->getUsernameIdent());

        $user = new User('2001:db8::/31');
        static::assertTrue($user->isIpRange());
        static::assertFalse($user->isQueryableRange());

        $user = new User('Test');
        static::assertFalse($user->isAnon());
        static::assertFalse($user->isIpRange());
        static::assertEquals('Test', $user->getPrettyUsername());
    }

    /**
     * @covers User::getIpSubstringFromCidr
     */
    public function testGetIpSubstringFromCidr(): void
    {
        $user = new User('2001:db8:abc:1400::/54');
        static::assertEquals('2001:DB8:ABC:1', $user->getIpSubstringFromCidr());

        $user = new User('174.197.128.0/18');
        static::assertEquals('174.197.1', $user->getIpSubstringFromCidr());

        $user = new User('174.197.128.0');
        static::assertEquals(null, $user->getIpSubstringFromCidr());
    }

    /**
     * @covers User::isQueryableRange
     */
    public function testIsQueryableRange(): void
    {
        $user = new User('2001:db8:abc:1400::/54');
        static::assertTrue($user->isQueryableRange());

        $user = new User('2001:db8:abc:1400::/5');
        static::assertFalse($user->isQueryableRange());

        $user = new User('2001:db8:abc:1400');
        static::assertTrue($user->isQueryableRange());
    }
}
