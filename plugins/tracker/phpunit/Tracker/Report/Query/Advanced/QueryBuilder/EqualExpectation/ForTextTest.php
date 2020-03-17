<?php
/**
 * Copyright (c) Enalean, 2017-present. All Rights Reserved.
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
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

namespace Tuleap\Tracker\Report\Query\Advanced\QueryBuilder\EqualExpression;

use CodendiDataAccess;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Tuleap\DB\Compat\Legacy2018\LegacyDataAccessInterface;
use Tuleap\Tracker\Report\Query\Advanced\Grammar\EqualComparison;
use Tuleap\Tracker\Report\Query\Advanced\Grammar\Field;
use Tuleap\Tracker\Report\Query\Advanced\Grammar\SimpleValueWrapper;
use Tuleap\Tracker\Report\Query\Advanced\QueryBuilder\EqualComparison\ForText;
use Tuleap\Tracker\Report\Query\Advanced\QueryBuilder\FromWhereComparisonFieldBuilder;

final class ForTextTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        CodendiDataAccess::setInstance(\Mockery::spy(LegacyDataAccessInterface::class));
    }

    protected function tearDown(): void
    {
        CodendiDataAccess::clearInstance();
    }

    public function testItUsesTheComparisonInternalIdAsASuffixInOrderToBeAbleToHaveTheFieldSeveralTimesInTheQuery(): void
    {
        $comparison = new EqualComparison(new Field('field'), new SimpleValueWrapper('value'));
        $field_id   = 101;
        $field      = \Mockery::mock(\Tracker_FormElement_Field_Text::class);
        $field->shouldReceive('getId')->andReturn($field_id);

        $for_text   = new ForText(
            new FromWhereComparisonFieldBuilder()
        );
        $from_where = $for_text->getFromWhere($comparison, $field);

        $suffix = spl_object_hash($comparison);

        $this->assertRegExp("/tracker_changeset_value_text AS CVText_{$field_id}_{$suffix}/", $from_where->getFromAsString());
    }
}