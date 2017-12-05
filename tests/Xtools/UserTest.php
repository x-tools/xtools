<?php
/**
 * This file contains only the UserTest class.
 */

namespace Tests\Xtools;

use PHPUnit_Framework_TestCase;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;
use DateTime;

/**
 * Tests for the User class.
 */
class UserTest extends PHPUnit_Framework_TestCase
{

    /**
     * A username should be given an initial capital letter in all cases.
     */
    public function testUsernameHasInitialCapital()
    {
        $user = new User('lowercasename');
        $this->assertEquals('Lowercasename', $user->getUsername());
        $user2 = new User('UPPERCASENAME');
        $this->assertEquals('UPPERCASENAME', $user2->getUsername());
    }

    /**
     * A user has an integer identifier on a project (and this can differ from project
     * to project).
     */
    public function testUserHasIdOnProject()
    {
        // Set up stub user and project repositories.
        $userRepo = $this->getMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getId')
            ->willReturn(12);
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getOne')
            ->willReturn(['dbname' => 'testWiki']);

        // Make sure the user has the correct ID.
        $user = new User('TestUser');
        $user->setRepository($userRepo);
        $project = new Project('wiki.example.org');
        $project->setRepository($projectRepo);
        $this->assertEquals(12, $user->getId($project));
    }

    /**
     * Is a user an admin on a given project?
     * @dataProvider isAdminProvider
     * @param string $username The username.
     * @param string[] $groups The groups to test.
     * @param bool $isAdmin The desired result.
     */
    public function testIsAdmin($username, $groups, $isAdmin)
    {
        $userRepo = $this->getMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getGroups')
            ->willReturn($groups);
        $user = new User($username);
        $user->setRepository($userRepo);
        $this->assertEquals($isAdmin, $user->isAdmin(new Project('testWiki')));
    }

    /**
     * Data for self::testIsAdmin().
     * @return string[]
     */
    public function isAdminProvider()
    {
        return [
            ['AdminUser', ['sysop', 'autopatrolled'], true],
            ['NormalUser', ['autopatrolled'], false],
        ];
    }

    /**
     * Get the expiry of the current block of a user on a given project
     */
    public function testExpiry()
    {
        $userRepo = $this->getMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getBlockExpiry')
            ->willReturn('20500601000000');
        $user = new User('TestUser');
        $user->setRepository($userRepo);

        $projectRepo = $this->getMock(ProjectRepository::class);
        $project = new Project('wiki.example.org');
        $project->setRepository($projectRepo);

        $this->assertEquals(new DateTime('20500601000000'), $user->getBlockExpiry($project));
    }

    /**
     * Is the user currently blocked on a given project?
     */
    public function testIsBlocked()
    {
        $userRepo = $this->getMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getBlockExpiry')
            ->willReturn('infinity');
        $user = new User('TestUser');
        $user->setRepository($userRepo);

        $projectRepo = $this->getMock(ProjectRepository::class);
        $project = new Project('wiki.example.org');
        $project->setRepository($projectRepo);

        $this->assertEquals(true, $user->isBlocked($project));
    }

    /**
     * Registration date of the user
     */
    public function testRegistrationDate()
    {
        $userRepo = $this->getMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getRegistrationDate')
            ->willReturn('20170101000000');
        $user = new User('TestUser');
        $user->setRepository($userRepo);

        $projectRepo = $this->getMock(ProjectRepository::class);
        $project = new Project('wiki.example.org');
        $project->setRepository($projectRepo);

        $regDateTime = new DateTime('2017-01-01 00:00:00');
        $this->assertEquals($regDateTime, $user->getRegistrationDate($project));
    }

    /**
     * System edit count.
     */
    public function testEditCount()
    {
        $userRepo = $this->getMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getEditCount')
            ->willReturn('12345');
        $user = new User('TestUser');
        $user->setRepository($userRepo);

        $projectRepo = $this->getMock(ProjectRepository::class);
        $project = new Project('wiki.example.org');
        $project->setRepository($projectRepo);

        $this->assertEquals(12345, $user->getEditCount($project));

        // Should not call UserRepository::getEditCount() again.
        $this->assertEquals(12345, $user->getEditCount($project));
    }

    /**
     * Too many edits to process?
     */
    public function testHasTooManyEdits()
    {
        $userRepo = $this->getMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getEditCount')
            ->willReturn('123456789');
        $userRepo->expects($this->exactly(3))
            ->method('maxEdits')
            ->willReturn('250000');
        $user = new User('TestUser');
        $user->setRepository($userRepo);

        $projectRepo = $this->getMock(ProjectRepository::class);
        $project = new Project('wiki.example.org');
        $project->setRepository($projectRepo);

        // User::maxEdits()
        $this->assertEquals(250000, $user->maxEdits());

        // User::tooManyEdits()
        $this->assertTrue($user->hasTooManyEdits($project));
    }
}
