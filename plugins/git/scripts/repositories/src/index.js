/**
 * Copyright (c) Enalean, 2018 - Present. All Rights Reserved.
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
import GetTextPlugin from "vue-gettext";
import TimeAgo from "javascript-time-ago";
import time_ago_english from "javascript-time-ago/locale/en";
import time_ago_french from "javascript-time-ago/locale/fr";
import VueDOMPurifyHTML from "vue-dompurify-html";
import french_translations from "../po/fr.po";
import App from "./components/App.vue";
import { setBreadcrumbSettings } from "./breadcrumb-presenter.js";
import { build as buildRepositoryListPresenter } from "./repository-list-presenter.js";

document.addEventListener("DOMContentLoaded", () => {
    Vue.use(VueDOMPurifyHTML);
    Vue.use(GetTextPlugin, {
        translations: {
            fr: french_translations.messages,
        },
        silent: true,
    });

    const locale = document.body.dataset.userLocale;
    Vue.config.language = locale;
    TimeAgo.locale(time_ago_english);
    TimeAgo.locale(time_ago_french);

    const vue_mount_point = document.getElementById("git-repository-list");

    if (vue_mount_point) {
        const AppComponent = Vue.extend(App);

        const {
            repositoriesAdministrationUrl,
            repositoryListUrl,
            repositoriesForkUrl,
            projectId,
            isAdmin,
            repositoriesOwners,
            displayMode,
            externalPlugins,
            externalServicesNameUsed,
            projectPublicName,
            projectUrl,
            privacy,
            projectFlags,
        } = vue_mount_point.dataset;

        setBreadcrumbSettings(
            repositoriesAdministrationUrl,
            repositoryListUrl,
            repositoriesForkUrl,
            projectPublicName,
            projectUrl,
            JSON.parse(privacy),
            JSON.parse(projectFlags)
        );
        buildRepositoryListPresenter(
            document.body.dataset.userId,
            projectId,
            isAdmin,
            locale,
            JSON.parse(repositoriesOwners),
            JSON.parse(externalPlugins)
        );

        new AppComponent({
            propsData: {
                displayMode,
                servicesNameUsed: JSON.parse(externalServicesNameUsed),
            },
        }).$mount(vue_mount_point);
    }
});
