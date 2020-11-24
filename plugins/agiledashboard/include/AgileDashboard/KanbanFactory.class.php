<?php
/**
 * Copyright (c) Enalean, 2014-Present. All Rights Reserved.
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
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

class AgileDashboard_KanbanFactory
{

    /** @var TrackerFactory */
    private $tracker_factory;

    /** @var AgileDashboard_KanbanDao */
    private $dao;

    public function __construct(TrackerFactory $tracker_factory, AgileDashboard_KanbanDao $dao)
    {
        $this->dao             = $dao;
        $this->tracker_factory = $tracker_factory;
    }

    /**
     * @return AgileDashboard_Kanban[]
     */
    public function getListOfKanbansForProject(PFUser $user, $project_id)
    {
        $rows    = $this->dao->getKanbansForProject($project_id);
        $kanbans = array();

        foreach ($rows as $kanban_data) {
            if ($this->isUserAllowedToAccessKanban($user, $kanban_data['tracker_id'])) {
                $kanbans[] = $this->instantiateFromRow($kanban_data);
            }
        }

        return $kanbans;
    }

    /**
     * @throws AgileDashboard_KanbanCannotAccessException
     * @throws AgileDashboard_KanbanNotFoundException
     *
     * @return AgileDashboard_Kanban
     */
    public function getKanban(PFUser $user, $kanban_id)
    {
        $row = $this->dao->getKanbanById($kanban_id)->getRow();

        if (! $row) {
            throw new AgileDashboard_KanbanNotFoundException();
        }

        if (! $this->isUserAllowedToAccessKanban($user, $row['tracker_id'])) {
            throw new AgileDashboard_KanbanCannotAccessException();
        }

        return $this->instantiateFromRow($row);
    }

    /**
     * @return int[]
     */
    public function getKanbanTrackerIds($project_id)
    {
        $rows               = $this->dao->getKanbansForProject($project_id);
        $kanban_tracker_ids = array();

        foreach ($rows as $kanban_data) {
            $kanban_tracker_ids[] = $kanban_data['tracker_id'];
        }

        return $kanban_tracker_ids;
    }

    /**
     * @return int
     */
    public function getKanbanIdByTrackerId($tracker_id)
    {
        $row    = $this->dao->getKanbanByTrackerId($tracker_id)->getRow();
        $kanban = $this->instantiateFromRow($row);
        return $kanban->getId();
    }

    /** @return AgileDashboard_Kanban */
    private function instantiateFromRow($kanban_data)
    {
        return new AgileDashboard_Kanban(
            $kanban_data['id'],
            $kanban_data['tracker_id'],
            $kanban_data['name']
        );
    }

    private function isUserAllowedToAccessKanban(PFUser $user, $tracker_id)
    {
        $tracker = $this->tracker_factory->getTrackerById($tracker_id);
        if (! $tracker) {
            throw new AgileDashboard_KanbanNotFoundException();
        }

        return $tracker->userCanView($user);
    }
}
