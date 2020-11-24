<?php
/**
* Copyright (c) Enalean, 2016. All Rights Reserved.
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

namespace Tuleap\SVN\AccessControl;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Tuleap\SVN\Repository\Repository;

class AccessFileReaderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var Repository */
    private $repository;

    /** @var AccessFileReader */
    private $reader;

    protected function setUp() : void
    {
        parent::setUp();
        $fixtures_dir = __DIR__ .'/_fixtures';

        $this->repository = \Mockery::mock(Repository::class);
        $this->repository->shouldReceive('getSystemPath')->andReturn($fixtures_dir);

        $this->reader = new AccessFileReader();
    }

    public function testItReadsTheDefaultBlock() : void
    {
        $this->assertRegExp(
            '/le default/',
            $this->reader->readDefaultBlock($this->repository)
        );
    }

    public function testItReadsTheContentBlock() : void
    {
        $this->assertRegExp(
            '/le content/',
            $this->reader->readContentBlock($this->repository)
        );
    }

    public function testItDoesNotContainDelimiters() : void
    {
        $this->assertNotRegExp(
            '/# BEGIN CODENDI DEFAULT SETTINGS/',
            $this->reader->readDefaultBlock($this->repository)
        );
    }
}
