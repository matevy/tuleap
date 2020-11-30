/*
 * Copyright (c) Enalean, 2018. All Rights Reserved.
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

import Vue from "vue";
import { REPOSITORIES_SORTED_BY_LAST_UPDATE, REPOSITORIES_SORTED_BY_PATH } from "../constants.js";
import { formatRepository } from "../gitlab/gitlab-repository-formatter";

export default {
    setSelectedOwnerId(state, selected_owner_id) {
        state.selected_owner_id = selected_owner_id;
    },
    pushRepositoriesForCurrentOwner(state, repositories) {
        if (typeof state.repositories_for_owner[state.selected_owner_id] === "undefined") {
            Vue.set(state.repositories_for_owner, state.selected_owner_id, []);
        }
        if (repositories.length > 0) {
            repositories.forEach(extendRepository);
            state.repositories_for_owner[state.selected_owner_id].push(...repositories);
        }
    },
    pushGitlabRepositoriesForCurrentOwner(state, repositories) {
        if (typeof state.repositories_for_owner[state.selected_owner_id] === "undefined") {
            Vue.set(state.repositories_for_owner, state.selected_owner_id, []);
        }
        if (repositories.length > 0) {
            const repositories_formatted = repositories.map((repo) => formatRepository(repo));
            state.repositories_for_owner[state.selected_owner_id].push(...repositories_formatted);
        }
    },
    setFilter(state, filter) {
        state.filter = filter;
    },
    setErrorMessageType(state, error_message_type) {
        state.error_message_type = error_message_type;
    },
    setSuccessMessage(state, success_message) {
        state.success_message = success_message;
    },
    setIsLoadingInitial(state, is_loading_initial) {
        state.is_loading_initial = is_loading_initial;
    },
    setIsLoadingNext(state, is_loading_next) {
        state.is_loading_next = is_loading_next;
    },
    setAddRepositoryModal(state, modal) {
        state.add_repository_modal = modal;
    },
    setAddGitlabRepositoryModal(state, modal) {
        state.add_gitlab_repository_modal = modal;
    },
    setUnlinkGitlabRepositoryModal(state, modal) {
        state.unlink_gitlab_repository_modal = modal;
    },
    setUnlinkGitlabRepository(state, repository) {
        state.unlink_gitlab_repository = repository;
    },
    setDisplayMode(state, new_mode) {
        if (isUnknownMode(new_mode)) {
            state.display_mode = REPOSITORIES_SORTED_BY_LAST_UPDATE;
        } else {
            state.display_mode = new_mode;
        }
    },
    setIsFirstLoadDone(state, is_first_load_done) {
        state.is_first_load_done = is_first_load_done;
    },
    setServicesNameUsed(state, services_name_used) {
        state.services_name_used = services_name_used;
    },
    removeRepository(state, repository) {
        const index_of_repository = state.repositories_for_owner[state.selected_owner_id].indexOf(
            repository
        );
        state.repositories_for_owner[state.selected_owner_id].splice(index_of_repository, 1);
    },
};

function isUnknownMode(mode) {
    return mode !== REPOSITORIES_SORTED_BY_LAST_UPDATE && mode !== REPOSITORIES_SORTED_BY_PATH;
}

function extendRepository(repository) {
    repository.normalized_path =
        repository.path_without_project !== ""
            ? repository.path_without_project + "/" + repository.label
            : repository.label;
}
