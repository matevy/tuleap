/**
 * Copyright (c) Enalean, 2019. All Rights Reserved.
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

import { createPopover } from "tlp";

export function init() {
    const project_flags_element = document.getElementById("project-sidebar-project-flags");
    if (!project_flags_element) {
        return;
    }

    const popover_content = document.getElementById("project-sidebar-project-flags-popover");

    createPopover(project_flags_element, popover_content, {
        anchor: project_flags_element.querySelector(".project-sidebar-project-flags-labels"),
        placement: "right-start",
    });
}
