<?php
/**
 * Copyright (c) Enalean, 2017 - present. All Rights Reserved.
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

namespace Tuleap\Tracker\FormElement;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TimePeriodWithoutWeekEnd;

final class ChartCachedDaysComparatorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testItVerifiesCacheIsCompleteForChartWhenCacheDaysAreTheSameThanTimePeriodDays(): void
    {
        $number_of_cached_days = 6;
        $start_date            = mktime(0, 0, 0, 20, 12, 2016);
        $duration              = 5;

        $time_period = TimePeriodWithoutWeekEnd::buildFromDuration($start_date, $duration);

        $cache_days_comparator = new ChartCachedDaysComparator(\Mockery::spy(LoggerInterface::class));
        $this->assertTrue($cache_days_comparator->isNumberOfCachedDaysExpected($time_period, $number_of_cached_days));
    }

    public function testItVerifiesCacheIsCompleteForChartWhenCacheDaysAreNotTheSameThanTimePeriodDays(): void
    {
        $number_of_cached_days = 6;
        $start_date            = mktime(0, 0, 0, 20, 12, 2016);
        $duration              = 15;

        $time_period = TimePeriodWithoutWeekEnd::buildFromDuration($start_date, $duration);

        $cache_days_comparator = new ChartCachedDaysComparator(\Mockery::spy(LoggerInterface::class));
        $this->assertFalse($cache_days_comparator->isNumberOfCachedDaysExpected($time_period, $number_of_cached_days));
    }
}
