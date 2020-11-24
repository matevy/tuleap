<?php
/**
 * Copyright (c) Enalean, 2012-Present. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

use Tuleap\Git\PathJoinUtil;

require_once 'bootstrap.php';

class Git_ForkRepositories_Test extends TuleapTestCase
{

    public function testRenders_ForkRepositories_View()
    {
        $request = new Codendi_Request(array('choose_destination' => 'personal'));

        $git = \Mockery::mock(\Git::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $git->setRequest($request);
        $git->shouldReceive('addView')->with('forkRepositories')->once();

        $factory = \Mockery::spy(\GitRepositoryFactory::class);
        $git->setFactory($factory);

        $user = \Mockery::spy(\PFUser::class);
        $user->shouldReceive('isMember')->andReturns(true);
        $user->shouldReceive('getUserName')->andReturns('testman');
        $git->user = $user;

        $project_manager = Mockery::spy(ProjectManager::class);
        $project_manager->shouldReceive('getProject')->andReturn(Mockery::spy(Project::class));
        $git->setProjectManager($project_manager);

        $git->_dispatchActionAndView('do_fork_repositories', null, null, null, $user);
    }

    public function testExecutes_ForkRepositories_ActionWithAListOfRepos()
    {
        $groupId = 101;
        $repo = new GitRepository();
        $repos = array($repo);
        $user = new PFUser();
        $user->setId(42);
        $user->setUserName('Ben');
        $path = PathJoinUtil::userRepoPath('Ben', 'toto');
        $forkPermissions = array();

        $project = Mockery::mock(Project::class);
        $project->shouldReceive('getID')->andReturns($groupId);
        $project->shouldReceive('getUnixNameLowerCase')->andReturns('projectname');

        $projectManager = \Mockery::spy(\ProjectManager::class);
        $projectManager->shouldReceive('getProject')->with($groupId)->andReturns($project);

        $factory = \Mockery::spy(\GitRepositoryFactory::class);
        $factory->shouldReceive('getRepositoryById')->andReturns($repo);

        $git = \Mockery::mock(\Git::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $git->setProject($project);
        $git->setProjectManager($projectManager);
        $git->shouldReceive('addAction')->with('getProjectRepositoryList', array($groupId))->ordered();
        $git->shouldReceive('addAction')->with('fork', array($repos, $project, $path, GitRepository::REPO_SCOPE_INDIVIDUAL, $user, $GLOBALS['HTML'], '/plugins/git/?group_id=101&user=42', $forkPermissions))->ordered();
        $request = new Codendi_Request(array(
            'repos' => '1001',
            'path'  => 'toto',
            'repo_access' => $forkPermissions));
        $git->setFactory($factory);
        $git->_doDispatchForkRepositories($request, $user);
    }

    public function testItUsesTheSynchronizerTokenToAvoidDuplicateForks()
    {
        $git = \Mockery::mock(\Git::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $git->shouldReceive('checkSynchronizerToken')->andThrow(new Exception());
        $this->expectException();
        $git->_doDispatchForkRepositories(null, null);
    }
}
