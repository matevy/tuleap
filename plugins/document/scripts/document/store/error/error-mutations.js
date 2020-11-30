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

export function resetErrors(state) {
    state.has_folder_permission_error = false;
    state.has_folder_loading_error = false;
    state.folder_loading_error = null;
    state.has_document_permission_error = false;
    state.has_document_loading_error = false;
    state.document_loading_error = null;
    state.has_document_lock_error = false;
    state.document_lock_error = null;
    state.has_global_modal_error = false;
    state.global_modal_error_message = null;
}

export function switchFolderPermissionError(state) {
    state.has_folder_permission_error = true;
}

export function switchItemPermissionError(state) {
    state.has_document_permission_error = true;
}

export function setFolderLoadingError(state, message) {
    state.has_folder_loading_error = true;
    state.folder_loading_error = message;
}

export function setItemLoadingError(state, message) {
    state.has_document_loading_error = true;
    state.document_loading_error = message;
}

export function setModalError(state, error_message) {
    state.has_modal_error = true;
    state.modal_error = error_message;
}

export function resetModalError(state) {
    state.has_modal_error = false;
    state.modal_error = null;
}

export function setLockError(state, error_message) {
    state.has_document_lock_error = true;
    state.document_lock_error = error_message;
}

export function setGlobalModalErrorMessage(state, message) {
    state.has_global_modal_error = true;
    state.global_modal_error_message = message;
}
