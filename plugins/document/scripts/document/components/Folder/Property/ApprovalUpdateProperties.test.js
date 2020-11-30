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

import localVue from "../../../helpers/local-vue.js";
import { shallowMount } from "@vue/test-utils";
import ApprovalUpdateProperties from "./ApprovalUpdateProperties.vue";

describe("ApprovalUpdateProperties", () => {
    let approval_update_factory;
    beforeEach(() => {
        approval_update_factory = () => {
            return shallowMount(ApprovalUpdateProperties, {
                localVue,
            });
        };
    });
    it(`Given the copy action of an approval table
        When the user updating an item
        Then it raise the 'action' event with the value 'copy'`, () => {
        const wrapper = approval_update_factory();

        const radio_input = wrapper.get(
            'input[id="document-new-file-upload-approval-table-action-copy"]'
        );
        radio_input.setChecked();

        expect(wrapper.emitted()["approval-table-action-change"][0]).toEqual(["copy"]);
    });
    it(`Given the reset action of an approval table
        When the user updating an item
        Then it raise the 'action' event with the value 'reset'`, () => {
        const wrapper = approval_update_factory();

        const radio_input = wrapper.get(
            'input[id="document-new-file-upload-approval-table-action-reset"]'
        );
        radio_input.setChecked();

        expect(wrapper.emitted()["approval-table-action-change"][0]).toEqual(["reset"]);
    });
    it(`Given the empty action of an approval table
        When the user updating an item
        Then it raise the 'action' event with the value 'empty'`, () => {
        const wrapper = approval_update_factory();

        const radio_input = wrapper.get(
            'input[id="document-new-file-upload-approval-table-action-empty"]'
        );
        radio_input.setChecked();

        expect(wrapper.emitted()["approval-table-action-change"][0]).toEqual(["empty"]);
    });
});
