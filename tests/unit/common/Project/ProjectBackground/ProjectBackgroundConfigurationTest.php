<?php
/**
 * Copyright (c) Enalean, 2020 - Present. All Rights Reserved.
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

declare(strict_types=1);

namespace Tuleap\Project\ProjectBackground;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Tuleap\ForgeConfigSandbox;

class ProjectBackgroundConfigurationTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use ForgeConfigSandbox;

    public function testItReturnsNullIfProjectDoesNotHaveABackground(): void
    {
        \ForgeConfig::set('feature_flag_project_background', '1');
        $project = \Mockery::mock(\Project::class)->shouldReceive(['getID' => 102])->getMock();

        $dao = \Mockery::mock(ProjectBackgroundDao::class);
        $dao->shouldReceive('getBackground')->andReturnNull();

        $configuration = new ProjectBackgroundConfiguration($dao);

        self::assertNull($configuration->getBackground($project));
    }

    public function testItReturnsTrueIfProjectDoesNotHaveABackground(): void
    {
        \ForgeConfig::set('feature_flag_project_background', '1');
        $project = \Mockery::mock(\Project::class)->shouldReceive(['getID' => 102])->getMock();

        $dao = \Mockery::mock(ProjectBackgroundDao::class);
        $dao->shouldReceive('getBackground')->andReturn('beach');

        $configuration = new ProjectBackgroundConfiguration($dao);

        self::assertEquals('beach', $configuration->getBackground($project));
    }

    public function testItReturnsNullIfFeatureFlagIsDeactivated(): void
    {
        \ForgeConfig::set('feature_flag_project_background', '0');
        $project = \Mockery::mock(\Project::class)->shouldReceive(['getID' => 102])->getMock();

        $dao = \Mockery::mock(ProjectBackgroundDao::class);
        $dao->shouldReceive('getBackground')->andReturn('beach');

        $configuration = new ProjectBackgroundConfiguration($dao);

        self::assertNull($configuration->getBackground($project));
    }

    public function testItCanIgnoreTheFeatureFlagSoThatWeCanDisplayBackgroundOnDashboards(): void
    {
        \ForgeConfig::set('feature_flag_project_background', '0');
        $project = \Mockery::mock(\Project::class)->shouldReceive(['getID' => 102])->getMock();

        $dao = \Mockery::mock(ProjectBackgroundDao::class);
        $dao->shouldReceive('getBackground')->andReturn('beach');

        $configuration = new ProjectBackgroundConfiguration($dao);

        self::assertEquals('beach', $configuration->getBackgroundIgnoringFeatureFlag($project));
    }
}
