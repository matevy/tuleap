<?php
/**
 * Copyright (c) Enalean, 2019 - present. All Rights Reserved.
 *
 *  This file is a part of Tuleap.
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

declare(strict_types = 1);

namespace Tuleap\AgileDashboard\Planning;

use AgileDashboard_ConfigurationManager;
use AgileDashboard_KanbanFactory;
use AgileDashboard_KanbanManager;
use AgileDashboard_XMLFullStructureExporter;
use Codendi_Request;
use EventManager;
use ForgeConfig;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Planning_Controller;
use Planning_MilestoneFactory;
use PlanningFactory;
use PlanningPermissionsManager;
use Project;
use ProjectManager;
use Tracker_FormElementFactory;
use Tuleap\AgileDashboard\BreadCrumbDropdown\AdministrationCrumbBuilder;
use Tuleap\AgileDashboard\BreadCrumbDropdown\AgileDashboardCrumbBuilder;
use Tuleap\AgileDashboard\ExplicitBacklog\ArtifactsInExplicitBacklogDao;
use Tuleap\AgileDashboard\MonoMilestone\ScrumForMonoMilestoneChecker;
use Tuleap\GlobalLanguageMock;
use Tuleap\Layout\BaseLayout;
use Tuleap\Test\DB\DBTransactionExecutorPassthrough;
use Tuleap\Tracker\Semantic\Timeframe\TimeframeChecker;

class PlanningControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration, GlobalLanguageMock;
    /**
     * @var Mockery\LegacyMockInterface|Mockery\MockInterface|PlanningFactory
     */
    public $planning_factory;
    /**
     * @var Mockery\LegacyMockInterface|Mockery\MockInterface|ArtifactsInExplicitBacklogDao
     */
    public $explicit_backlog_dao;
    /**
     * @var Mockery\LegacyMockInterface|Mockery\MockInterface|PlanningUpdater
     */
    private $planning_updater;
    /**
     * @var Mockery\LegacyMockInterface|Mockery\MockInterface|\Planning_RequestValidator
     */
    private $planning_request_validator;
    /**
     * @var EventManager|Mockery\LegacyMockInterface|Mockery\MockInterface
     */
    private $event_manager;
    /**
     * @var Codendi_Request|Mockery\LegacyMockInterface|Mockery\MockInterface
     */
    private $request;

    /**
     * @var Planning_Controller
     */
    private $planning_controller;

    protected function setUp(): void
    {
        parent::setUp();

        ForgeConfig::store();
        ForgeConfig::set('codendi_dir', AGILEDASHBOARD_BASE_DIR . '/../../..');

        $plugin_path = "/plugins/agiledashboard";

        $this->request = Mockery::mock(Codendi_Request::class);
        $project       = Mockery::mock(Project::class);
        $this->request->shouldReceive('getProject')->andReturn($project);
        $project->shouldReceive('getID')->andReturn(101);

        $GLOBALS['Response'] = Mockery::mock(BaseLayout::class);

        $this->planning_factory     = Mockery::mock(PlanningFactory::class);
        $this->explicit_backlog_dao = Mockery::mock(ArtifactsInExplicitBacklogDao::class);

        $this->event_manager              = Mockery::mock(EventManager::class);
        $this->planning_request_validator = Mockery::mock(\Planning_RequestValidator::class);
        $this->planning_updater           = Mockery::mock(PlanningUpdater::class);
        $this->planning_controller        = new Planning_Controller(
            $this->request,
            $this->planning_factory,
            Mockery::mock(Planning_MilestoneFactory::class),
            Mockery::mock(ProjectManager::class),
            Mockery::mock(AgileDashboard_XMLFullStructureExporter::class),
            $plugin_path,
            Mockery::mock(AgileDashboard_KanbanManager::class),
            Mockery::mock(AgileDashboard_ConfigurationManager::class),
            Mockery::mock(AgileDashboard_KanbanFactory::class),
            Mockery::mock(PlanningPermissionsManager::class),
            Mockery::mock(ScrumForMonoMilestoneChecker::class),
            Mockery::mock(ScrumPlanningFilter::class),
            Mockery::mock(Tracker_FormElementFactory::class),
            Mockery::mock(AgileDashboardCrumbBuilder::class),
            Mockery::mock(AdministrationCrumbBuilder::class),
            Mockery::mock(TimeframeChecker::class),
            new DBTransactionExecutorPassthrough(),
            $this->explicit_backlog_dao,
            $this->planning_updater,
            $this->event_manager,
            $this->planning_request_validator
        );
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['Response']);
        ForgeConfig::restore();

        parent::tearDown();
    }

    public function testItDeletesThePlanningAndRedirectsToTheIndex(): void
    {
        $user = Mockery::mock(\PFUser::class);
        $user->shouldReceive('isAdmin')->once()->andReturnTrue();
        $this->request->shouldReceive('getCurrentUser')->twice()->andReturn($user);
        $this->request->shouldReceive('get')->once()->withArgs(['planning_id'])->andReturn(42);

        $root_planning = Mockery::mock(\Planning_Milestone::class);
        $root_planning->shouldReceive('getId')->andReturn(109);
        $this->planning_factory->shouldReceive('getRootPlanning')->andReturn($root_planning);
        $this->planning_factory->shouldReceive('deletePlanning')->once()->withArgs([42]);
        $this->explicit_backlog_dao->shouldReceive('removeExplicitBacklogOfPlanning')->never();

        $GLOBALS['Response']->shouldReceive('redirect')->once()->withArgs(
            ['/plugins/agiledashboard/?group_id=101&action=admin']
        );

        $this->planning_controller->delete();
    }

    public function testItDeletesExplicitBacklogPlanning(): void
    {
        $user = Mockery::mock(\PFUser::class);
        $user->shouldReceive('isAdmin')->once()->andReturnTrue();
        $this->request->shouldReceive('getCurrentUser')->twice()->andReturn($user);
        $this->request->shouldReceive('get')->once()->withArgs(['planning_id'])->andReturn(42);

        $root_planning = Mockery::mock(\Planning_Milestone::class);
        $root_planning->shouldReceive('getId')->andReturn(42);
        $this->planning_factory->shouldReceive('getRootPlanning')->andReturn($root_planning);
        $this->planning_factory->shouldReceive('deletePlanning')->once()->withArgs([42]);
        $this->explicit_backlog_dao->shouldReceive('removeExplicitBacklogOfPlanning')->once()->withArgs([42]);

        $GLOBALS['Response']->shouldReceive('redirect')->once()->withArgs(
            ['/plugins/agiledashboard/?group_id=101&action=admin']
        );

        $this->planning_controller->delete();
    }

    public function testItDoesntDeleteAnythingIfTheUserIsNotAdmin(): void
    {
        $user = Mockery::mock(\PFUser::class);
        $user->shouldReceive('isAdmin')->once()->andReturnFalse();
        $user->shouldReceive('isSuperUser')->once()->andReturnFalse();
        $this->request->shouldReceive('getCurrentUser')->once()->andReturn($user);
        $this->request->shouldReceive('get')->never()->withArgs(['planning_id']);

        $this->expectException(\Exception::class);
        $this->planning_controller->delete();
    }

    public function testItOnlyUpdateCardWallConfigWhenRequestIsInvalid(): void
    {
        $user = Mockery::mock(\PFUser::class);
        $user->shouldReceive('isAdmin')->once()->andReturnTrue();

        $this->request->shouldReceive('getCurrentUser')->once()->andReturn($user);
        $this->request->shouldReceive('get')->withArgs(['planning_id'])->andReturn(1);

        $GLOBALS['Response']->shouldReceive('addFeedback')->once();

        $this->event_manager->shouldReceive('processEvent')->once();

        $planning = Mockery::mock(\Planning::class);
        $planning->shouldReceive('getPlanningTracker')->once();
        $this->planning_factory->shouldReceive('getPlanning')->once()->andReturn($planning);

        $this->planning_request_validator->shouldReceive('isValid')->andReturnFalse();

        $GLOBALS['Response']->shouldReceive('redirect')->once();

        $this->planning_controller->update();
    }

    public function testItUpdatesThePlanning(): void
    {
        $user = Mockery::mock(\PFUser::class);
        $user->shouldReceive('isAdmin')->once()->andReturnTrue();

        $this->request->shouldReceive('getCurrentUser')->twice()->andReturn($user);
        $this->request->shouldReceive('get')->withArgs(['planning_id'])->andReturn(1);

        $planning_parameters = [];
        $this->request->shouldReceive('get')->withArgs(['planning'])->andReturn($planning_parameters);

        $GLOBALS['Response']->shouldReceive('addFeedback')->once();

        $this->event_manager->shouldReceive('processEvent')->once();

        $planning = Mockery::mock(\Planning::class);
        $planning->shouldReceive('getPlanningTracker')->once();
        $this->planning_factory->shouldReceive('getPlanning')->once()->andReturn($planning);

        $this->planning_request_validator->shouldReceive('isValid')->andReturnTrue();

        $this->planning_updater->shouldReceive('update')->once();

        $GLOBALS['Response']->shouldReceive('redirect')->once();

        $this->planning_controller->update();
    }
}
