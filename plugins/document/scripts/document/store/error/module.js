/*
 * Copyright (c) Enalean, 2019 - present. All Rights Reserved.
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

import * as mutations from "./error-mutations.js";
import * as getters from "./error-getters.js";
import * as actions from "./error-actions.js";

export default {
    namespaced: true,
    state: {
        has_document_permission_error: false,
        has_document_loading_error: false,
        document_loading_error: null,
        has_folder_permission_error: false,
        has_folder_loading_error: false,
        folder_loading_error: null,
        has_modal_error: false,
        modal_error: null,
        has_document_lock_error: false,
        document_lock_error: null,
        has_global_modal_error: false,
        global_modal_error_message: null,
    },
    getters,
    mutations,
    actions,
};
