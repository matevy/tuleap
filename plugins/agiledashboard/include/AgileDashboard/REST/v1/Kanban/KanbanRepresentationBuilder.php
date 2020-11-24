<?php
/**
 * Copyright (c) Enalean, 2014-2015. All Rights Reserved.
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

namespace Tuleap\AgileDashboard\REST\v1\Kanban;

use AgileDashboard_Kanban;
use AgileDashboard_KanbanColumnFactory;
use PFUser;
use AgileDashboard_KanbanActionsChecker;
use AgileDashboard_KanbanUserPreferences;
use Exception;

class KanbanRepresentationBuilder
{

    /**
     * @var AgileDashboard_KanbanUserPreferences
     */
    private $user_preferences;
    /**
     * @var AgileDashboard_KankanColumnFactory
     */
    private $kanban_column_factory;

    /**
     * @var AgileDashboard_KanbanActionsChecker
     */
    private $kanban_actions_checker;

    public function __construct(
        AgileDashboard_KanbanUserPreferences $user_preferences,
        AgileDashboard_KanbanColumnFactory $kanban_column_factory,
        AgileDashboard_KanbanActionsChecker $kanban_actions_checker
    ) {
        $this->kanban_column_factory  = $kanban_column_factory;
        $this->user_preferences       = $user_preferences;
        $this->kanban_actions_checker = $kanban_actions_checker;
    }

    /**
     * @return Tuleap\AgileDashboard\REST\v1\Kanban\KanbanRepresentation
     */
    public function build(AgileDashboard_Kanban $kanban, PFUser $user)
    {

        try {
            $this->kanban_actions_checker->checkUserCanAddInPlace($user, $kanban);
            $user_can_add_in_place = true;
        } catch (Exception $exception) {
            $user_can_add_in_place = false;
        }

        try {
            $this->kanban_actions_checker->checkUserCanAddColumns($user, $kanban);
            $user_can_add_columns = true;
        } catch (Exception $exception) {
            $user_can_add_columns = false;
        }

        try {
            $this->kanban_actions_checker->checkUserCanReorderColumns($user, $kanban);
            $user_can_reorder_columns = true;
        } catch (Exception $exception) {
            $user_can_reorder_columns = false;
        }

        $kanban_representation = new KanbanRepresentation();
        $kanban_representation->build(
            $kanban,
            $this->kanban_column_factory,
            $this->user_preferences,
            $this->kanban_actions_checker,
            $user_can_add_columns,
            $user_can_reorder_columns,
            $user_can_add_in_place,
            $user
        );

        return $kanban_representation;
    }
}
