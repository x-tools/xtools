<?php

namespace Xtools;

use PHPUnit_Framework_TestCase;

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

    public function isAdminProvider()
    {
        return [
            ['AdminUser', ['sysop', 'autopatrolled'], true],
            ['NormalUser', ['autopatrolled'], false],
        ];
    }
}
