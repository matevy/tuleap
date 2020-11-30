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

import { shallowMount } from "@vue/test-utils";
import localVue from "../../../helpers/local-vue.js";
import DropDownButton from "./DropDownButton.vue";
import EventBus from "../../../helpers/event-bus.js";
import * as tlp from "tlp";

jest.mock("tlp");

describe("DropDownButton", () => {
    let dropdown_factory;
    beforeEach(() => {
        jest.spyOn(tlp, "createDropdown");
        dropdown_factory = (props = {}) => {
            return shallowMount(DropDownButton, {
                localVue,
                propsData: { ...props },
            });
        };
    });

    it(`Given drop down button is appended (aka user has write permissions)
        When we display the button
        Then it should display the button action and the dropdown option ( | update | v |)`, () => {
        const wrapper = dropdown_factory({ isAppended: true, isInQuickLookMode: false });

        expect(wrapper.find(".tlp-append").exists()).toBeTruthy();
        expect(wrapper.find(".tlp-button-icon-right").exists()).toBeFalsy();
        expect(wrapper.find(".fa-ellipsis-h").exists()).toBeFalsy();
    });

    it(`Given drop down button is not appended (aka user has read permissions)
        When we display the button
        Then it should display an ellipsis and the dropdown option (|... v|)`, () => {
        const wrapper = dropdown_factory({ isAppended: false, isInQuickLookMode: false });

        expect(wrapper.find(".tlp-append").exists()).toBeFalsy();
        expect(wrapper.find(".fa-ellipsis-h").exists()).toBeTruthy();
        expect(wrapper.find(".tlp-button-icon-right").exists()).toBeTruthy();
    });

    it(`Given drop down button is in quick look mode
        When we display the button
        Then it should be displayed outlined`, () => {
        const wrapper = dropdown_factory({ isAppended: true, isInQuickLookMode: true });

        expect(wrapper.find(".tlp-button-outline").exists()).toBeTruthy();
    });

    it(`Given drop down button is in large mode
        When we display the button
        Then it should be displayed large`, () => {
        const wrapper = dropdown_factory({
            isAppended: true,
            isInQuickLookMode: true,
            isInLargeMode: true,
        });

        expect(wrapper.find(".tlp-button-large").exists()).toBeTruthy();
    });

    it(`Hide the dropdown
        When component is destroyed`, () => {
        const wrapper = dropdown_factory({
            isAppended: true,
            isInQuickLookMode: true,
            isInLargeMode: true,
        });

        const event_bus_off = jest.spyOn(EventBus, "$off");

        wrapper.destroy();

        expect(event_bus_off).toHaveBeenCalledWith("hide-action-menu", expect.any(Function));
    });
});
