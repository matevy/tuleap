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

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class ProjectBackgroundRetrieverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testNoBackgroundIsSelectedIfProjectDidNotSelectOne()
    {
        $project = Mockery::mock(\Project::class)->shouldReceive(['getID' => 123])->getMock();

        $dao = Mockery::mock(ProjectBackgroundDao::class);
        $dao->shouldReceive(['getBackground' => null]);

        $retriever = new ProjectBackgroundRetriever($dao);
        $backgrounds = $retriever->getBackgrounds($project);

        self::assertTrue($backgrounds[0]->is_no_background);
        self::assertTrue($backgrounds[0]->is_selected);
        for ($i = 1, $length = count($backgrounds); $i < $length; $i++) {
            self::assertFalse($backgrounds[$i]->is_no_background);
            self::assertFalse($backgrounds[$i]->is_selected);
        }
    }
    public function testGetBackgrounds()
    {
        $project = Mockery::mock(\Project::class)->shouldReceive(['getID' => 123])->getMock();

        $dao = Mockery::mock(ProjectBackgroundDao::class);
        $dao->shouldReceive(['getBackground' => 'beach']);

        $retriever = new ProjectBackgroundRetriever($dao);
        $backgrounds = $retriever->getBackgrounds($project);

        self::assertTrue($backgrounds[0]->is_no_background);
        self::assertFalse($backgrounds[0]->is_selected);
        for ($i = 1, $length = count($backgrounds); $i < $length; $i++) {
            self::assertFalse($backgrounds[$i]->is_no_background);
            if ($backgrounds[$i]->identifier === 'beach') {
                self::assertTrue($backgrounds[$i]->is_selected);
            } else {
                self::assertFalse($backgrounds[$i]->is_selected);
            }
        }
    }
}