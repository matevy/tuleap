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
 *
 */

declare(strict_types=1);

namespace Tuleap\Tests\Integration;

use ForgeConfig;
use PHPUnit\Framework\TestCase;
use ProjectCreator;
use ProjectManager;
use Tuleap\DB\DBFactory;
use Tuleap\GlobalLanguageMock;
use Tuleap\GlobalSVNPollution;
use Tuleap\Project\Registration\Template\TemplateFromProjectForCreation;
use Tuleap\TemporaryTestDirectory;

class ProjectCreationTest extends TestCase
{
    use GlobalLanguageMock;
    use GlobalSVNPollution;
    use TemporaryTestDirectory;

    private $backup_project_can_be_created;
    private $backup_codendi_log;
    private $backup_plogger_level;
    private $backup_svn_prefix;
    private $backup_cvs_prefix;
    private $backup_sys_default_domain;

    public function setUp(): void
    {
        $this->backup_project_can_be_created = ForgeConfig::get(ProjectManager::CONFIG_PROJECTS_CAN_BE_CREATED);
        $this->backup_codendi_log = ForgeConfig::get('codendi_log');
        $this->backup_plogger_level = ForgeConfig::get('sys_logger_level');

        $this->backup_svn_prefix = ForgeConfig::get('svn_prefix');
        ForgeConfig::set('svn_prefix', $this->getTmpDir());
        $this->backup_cvs_prefix = ForgeConfig::get('cvs_prefix');
        ForgeConfig::set('cvs_prefix', $this->getTmpDir());
        $this->backup_sys_default_domain = ForgeConfig::get('sys_default_domain');
        ForgeConfig::set('sys_default_domain', '');
    }

    public function tearDown(): void
    {
        ProjectManager::clearInstance();

        ForgeConfig::set(ProjectManager::CONFIG_PROJECTS_CAN_BE_CREATED, $this->backup_project_can_be_created);
        ForgeConfig::set('codendi_log', $this->backup_codendi_log);
        ForgeConfig::set('sys_logger_level', $this->backup_plogger_level);
        ForgeConfig::set('svn_prefix', $this->backup_svn_prefix);
        ForgeConfig::set('cvs_prefix', $this->backup_cvs_prefix);
        ForgeConfig::set('sys_default_domain', $this->backup_sys_default_domain);

        DBFactory::getMainTuleapDBConnection()->getDB()->run('DELETE FROM groups WHERE unix_group_name = "short-name"');
        unset($GLOBALS['feedback']);
        $_GET = [];
        $_REQUEST = [];
    }

    public function testItCreatesAProject(): void
    {
        ForgeConfig::set(ProjectManager::CONFIG_PROJECTS_CAN_BE_CREATED, '1');
        ForgeConfig::set('codendi_log', '/tmp');
        ForgeConfig::set('sys_logger_level', 'error');
        $projectCreator = ProjectCreator::buildSelfRegularValidation();

        $projectCreator->create(
            'short-name',
            'Long name',
            TemplateFromProjectForCreation::fromGlobalProjectAdminTemplate(),
            [
                'project' => [
                    'form_short_description' => 'description',
                    'is_test'                => false,
                    'is_public'              => false,
                    'services'               => [],
                ]
            ]
        );

        ProjectManager::clearInstance();
        $project = ProjectManager::instance()->getProjectByUnixName('short-name');
        $this->assertEquals('Long name', $project->getPublicName());
    }
}
