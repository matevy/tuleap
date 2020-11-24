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

import { shallowMount, ShallowMountOptions, Wrapper } from "@vue/test-utils";
import ReleaseDisplayer from "./ReleaseDisplayer.vue";
import { createStoreMock } from "../../../../../../../src/www/scripts/vue-components/store-wrapper-jest";
import ReleaseHeader from "./ReleaseHeader/ReleaseHeader.vue";
import { MilestoneData, StoreOptions } from "../../type";
import { DefaultData } from "vue/types/options";
import { createReleaseWidgetLocalVue } from "../../helpers/local-vue-for-test";

let release_data: MilestoneData;
let component_options: ShallowMountOptions<ReleaseDisplayer>;

describe("ReleaseDisplayer", () => {
    let store_options: StoreOptions;
    let store;

    async function getPersonalWidgetInstance(
        store_options: StoreOptions
    ): Promise<Wrapper<ReleaseDisplayer>> {
        store = createStoreMock(store_options);

        component_options.mocks = { $store: store };
        component_options.localVue = await createReleaseWidgetLocalVue();

        return shallowMount(ReleaseDisplayer, component_options);
    }

    beforeEach(() => {
        store_options = {
            state: {}
        };

        release_data = {
            label: "mile",
            id: 2,
            start_date: new Date("2017-01-22T13:42:08+02:00").toDateString(),
            capacity: 10,
            total_sprint: 20,
            initial_effort: 10,
            number_of_artifact_by_trackers: []
        };

        component_options = {
            propsData: {
                release_data
            },
            data(): DefaultData<ReleaseDisplayer> {
                return {
                    is_open: false,
                    is_loading: false,
                    error_message: null
                };
            }
        };
    });

    it("When there is a rest error, Then it displays", async () => {
        component_options.data = (): DefaultData<ReleaseDisplayer> => {
            return {
                is_open: true,
                is_loading: false,
                error_message: "404"
            };
        };
        const wrapper = await getPersonalWidgetInstance(store_options);
        expect(wrapper.contains("[data-test=show-error-message]")).toBe(true);
    });

    it("When the widget is rendered, Then toggle is closed", async () => {
        component_options.data = (): DefaultData<ReleaseDisplayer> => {
            return {
                is_open: false,
                is_loading: false,
                error_message: null
            };
        };

        const wrapper = await getPersonalWidgetInstance(store_options);
        expect(wrapper.contains("[data-test=toggle-open]")).toBe(false);
    });

    it("When the toggle is opened and the user want close it, Then an event is emit", async () => {
        component_options.data = (): DefaultData<ReleaseDisplayer> => {
            return {
                is_open: true,
                is_loading: false,
                error_message: null
            };
        };

        const wrapper = await getPersonalWidgetInstance(store_options);
        expect(wrapper.contains("[data-test=toggle-open]")).toBe(true);

        wrapper.find(ReleaseHeader).vm.$emit("toggleReleaseDetails");
        expect(wrapper.contains("[data-test=toggle-open]")).toBe(false);
    });

    it("When the milestone is loading, Then the class is disabled and a tooltip say why", async () => {
        component_options.data = (): DefaultData<ReleaseDisplayer> => {
            return {
                is_open: false,
                is_loading: true,
                error_message: null
            };
        };

        const wrapper = await getPersonalWidgetInstance(store_options);
        wrapper.setData({ is_loading: true });
        expect(wrapper.attributes("data-tlp-tooltip")).toEqual("Loading data...");
    });

    it("When the widget is rendered and the toggle opened, Then there are no errors and components called", async () => {
        component_options.data = (): DefaultData<ReleaseDisplayer> => {
            return {
                is_open: true,
                is_loading: false,
                error_message: null
            };
        };

        const wrapper = await getPersonalWidgetInstance(store_options);
        expect(wrapper.contains("[data-test=display-release-data]")).toBe(true);
    });
});
