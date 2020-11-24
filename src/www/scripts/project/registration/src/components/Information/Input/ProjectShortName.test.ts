/*
 * Copyright (c) Enalean, 2019 - present. All Rights Reserved.
 *
 *  This file is a part of Tuleap.
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
 *
 */

import { shallowMount, Wrapper } from "@vue/test-utils";
import { createProjectRegistrationLocalVue } from "../../../helpers/local-vue-for-tests";
import { Store } from "vuex-mock-store";
import ProjectShortName from "./ProjectShortName.vue";
import { DefaultData } from "vue/types/options";
import { createStoreMock } from "../../../../../../vue-components/store-wrapper-jest";
import EventBus from "../../../helpers/event-bus";

describe("ProjectShortName", () => {
    let store: Store;

    async function createWrapper(
        data: DefaultData<ProjectShortName>
    ): Promise<Wrapper<ProjectShortName>> {
        const store_options = {
            getters: { has_error: false }
        };

        store = createStoreMock(store_options);

        return shallowMount(ProjectShortName, {
            data(): DefaultData<ProjectShortName> {
                return data;
            },
            localVue: await createProjectRegistrationLocalVue(),
            mocks: { $store: store }
        });
    }

    describe("Slug display", () => {
        it(`Does not display anything if project shortname is empty`, async () => {
            const data = {
                slugified_project_name: "",
                has_slug_error: false,
                is_in_edit_mode: false
            };
            const wrapper = await createWrapper(data);
            expect(wrapper.contains("[data-test=project-shortname-slugified-section]")).toBe(false);
            expect(wrapper.find("[data-test=project-shortname-edit-section]").classes()).toEqual([
                "tlp-form-element",
                "project-short-name-hidden-section"
            ]);
        });

        it(`Display the edit mode if user switched to edit short name mode`, async () => {
            const data = {
                slugified_project_name: "",
                has_slug_error: false,
                is_in_edit_mode: true
            };
            const wrapper = await createWrapper(data);

            expect(wrapper.contains("[data-test=project-shortname-slugified-section]")).toBe(false);
            expect(wrapper.find("[data-test=project-shortname-edit-section]").classes()).toEqual([
                "tlp-form-element",
                "project-short-name-edit-section"
            ]);
        });

        it(`Displays slugged project name`, async () => {
            const data = {
                slugified_project_name: "my-short-name",
                has_slug_error: false,
                is_in_edit_mode: false
            };
            const wrapper = await createWrapper(data);

            expect(wrapper.contains("[data-test=project-shortname-slugified-section]")).toBe(true);
            expect(wrapper.find("[data-test=project-shortname-edit-section]").classes()).toEqual([
                "tlp-form-element",
                "project-short-name-hidden-section"
            ]);
        });
    });

    describe("Slugify parent label", () => {
        it(`Has an error when shortname has less than 3 characters`, async () => {
            const event_bus_emit = jest.spyOn(EventBus, "$emit");

            const data = {
                slugified_project_name: "",
                has_slug_error: false,
                is_in_edit_mode: false
            };
            const wrapper = await createWrapper(data);

            EventBus.$emit("slugify-project-name", "My");

            expect(wrapper.vm.$data.slugified_project_name).toBe("My");
            expect(wrapper.vm.$data.has_slug_error).toBe(true);

            expect(event_bus_emit).toHaveBeenCalledWith("update-project-name", {
                slugified_name: wrapper.vm.$data.slugified_project_name,
                name: "My"
            });
        });

        it(`Has an error when shortname start by a numerical character`, async () => {
            const event_bus_emit = jest.spyOn(EventBus, "$emit");

            const data = {
                slugified_project_name: "",
                has_slug_error: false,
                is_in_edit_mode: false
            };
            const wrapper = await createWrapper(data);

            EventBus.$emit("slugify-project-name", "0My project");

            expect(wrapper.vm.$data.slugified_project_name).toBe("0My-project");
            expect(wrapper.vm.$data.has_slug_error).toBe(true);

            expect(event_bus_emit).toHaveBeenCalledWith("update-project-name", {
                slugified_name: wrapper.vm.$data.slugified_project_name,
                name: "0My project"
            });
        });

        it(`Has an error when shortname contains invalid characters`, async () => {
            const event_bus_emit = jest.spyOn(EventBus, "$emit");

            const data = {
                slugified_project_name: "",
                has_slug_error: false,
                is_in_edit_mode: false
            };
            const wrapper = await createWrapper(data);
            EventBus.$emit("slugify-project-name", "******");

            expect(wrapper.vm.$data.slugified_project_name).toBe("******");
            expect(wrapper.vm.$data.has_slug_error).toBe(true);

            expect(event_bus_emit).toHaveBeenCalledWith("update-project-name", {
                slugified_name: wrapper.vm.$data.slugified_project_name,
                name: "******"
            });
        });

        it(`Store and validate the project name`, async () => {
            const event_bus_emit = jest.spyOn(EventBus, "$emit");

            const data = {
                slugified_project_name: "",
                has_slug_error: false,
                is_in_edit_mode: false
            };
            const wrapper = await createWrapper(data);
            EventBus.$emit("slugify-project-name", "My project name");

            expect(wrapper.vm.$data.slugified_project_name).toBe("My-project-name");
            expect(wrapper.vm.$data.has_slug_error).toBe(false);

            expect(event_bus_emit).toHaveBeenCalledWith("update-project-name", {
                slugified_name: wrapper.vm.$data.slugified_project_name,
                name: "My project name"
            });
        });

        it(`Slugified project name handle correctly the accents`, async () => {
            const event_bus_emit = jest.spyOn(EventBus, "$emit");

            const data = {
                slugified_project_name: "",
                has_slug_error: false,
                is_in_edit_mode: false
            };
            const wrapper = await createWrapper(data);
            EventBus.$emit("slugify-project-name", "Accentué ç è é ù ë");

            expect(wrapper.vm.$data.slugified_project_name).toBe("Accentue-c-e-e-u-e");
            expect(wrapper.vm.$data.has_slug_error).toBe(false);

            expect(event_bus_emit).toHaveBeenCalledWith("update-project-name", {
                slugified_name: wrapper.vm.$data.slugified_project_name,
                name: "Accentué ç è é ù ë"
            });
        });

        it(`Does not slugify in edit mode`, async () => {
            const event_bus_emit = jest.spyOn(EventBus, "$emit");

            const data = {
                slugified_project_name: "",
                has_slug_error: false,
                is_in_edit_mode: true,
                project_name: "test-project"
            };
            const wrapper = await createWrapper(data);
            EventBus.$emit("slugify-project-name", "test-project!!!!");

            expect(wrapper.vm.$data.slugified_project_name).toBe("");
            expect(wrapper.vm.$data.has_slug_error).toBe(false);

            expect(event_bus_emit).not.toHaveBeenCalledWith("update-project-name", {
                slugified_name: "test-project!!!!",
                name: "test-project"
            });
        });
    });

    describe("Project shortname update", () => {
        it(`Validate string but not calls slugify when shortname is in edit mode`, async () => {
            const event_bus_emit = jest.spyOn(EventBus, "$emit");

            const data = {
                slugified_project_name: "my-short-name",
                has_slug_error: false,
                is_in_edit_mode: false,
                project_name: "my-short-name"
            };
            const wrapper = await createWrapper(data);

            wrapper.find("[data-test=new-project-name]").setValue("Original");

            wrapper.find("[data-test=project-shortname-slugified-section]").trigger("click");

            wrapper.find("[data-test=new-project-name]").setValue("Accentué ç è é ù ë");
            expect(wrapper.vm.$data.slugified_project_name).toBe("Accentué ç è é ù ë");
            expect(wrapper.vm.$data.has_slug_error).toBe(true);

            expect(event_bus_emit).toHaveBeenCalledWith("update-project-name", {
                slugified_name: "Accentué ç è é ù ë",
                name: wrapper.vm.$data.project_name
            });
        });
    });
});
