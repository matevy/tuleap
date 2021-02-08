/**
 * Copyright (c) STMicroelectronics 2011. All rights reserved
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

/* global $:readonly */

var tuleap = tuleap || {};
tuleap.textarea = tuleap.textarea || {};

document.observe("dom:loaded", function () {
    var html_by_default = false;
    var allow_permissions_set = false;

    if ($(document.body).hasClassName("default_format_html")) {
        html_by_default = true;
    }

    var obj_rte_use_permissions_checkbox = $("tracker_followup_comment_use_permissions_new");
    if ( obj_rte_use_permissions_checkbox != null ) {
        allow_permissions_set = true;
    }

    var newFollowup = $("tracker_followup_comment_new");
    if (newFollowup) {
        new tuleap.textarea.RTE(newFollowup, {
            toggle: true,
            default_in_html: false,
            id: "new",
            full_width: true,
            htmlFormat: html_by_default,
            allow_permissions_set: allow_permissions_set,
            use_permissions: false,
        });
    }

    var massChangeFollowup = $("artifact_masschange_followup_comment");
    if (massChangeFollowup) {
        new tuleap.textarea.RTE(massChangeFollowup, {
            toggle: true,
            default_in_html: false,
            id: "mass_change",
            htmlFormat: html_by_default,
            allow_permissions_set: false,
            use_permissions: false,
        });
    }
});
