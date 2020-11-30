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
import { createStoreMock } from "../../../../../../../src/scripts/vue-components/store-wrapper-jest.js";
import UnlockItem from "./UnlockItem.vue";

describe("UnlockItem", () => {
    let unlock_factory, state, store, store_options;
    beforeEach(() => {
        state = {
            user_id: 101,
        };
        store_options = {
            state,
        };
        store = createStoreMock(store_options);

        unlock_factory = (props = {}) => {
            return shallowMount(UnlockItem, {
                localVue,
                propsData: { ...props },
                mocks: { $store: store },
            });
        };
    });

    it(`Given document is not locked
        When we display the dropdown
        Then I should not be able to unlock it`, () => {
        const wrapper = unlock_factory({
            item: {
                id: 1,
                title: "my item title",
                type: "file",
                user_can_write: true,
                lock_info: null,
            },
        });

        expect(wrapper.find("[data-test=document-dropdown-menu-unlock-item]").exists()).toBeFalsy();
    });

    it(`Given an other user has locked a document, and given I don't have admin permission
        When we display the dropdown
        Then I should not be able to unlock it`, () => {
        const wrapper = unlock_factory({
            item: {
                id: 1,
                title: "my item title",
                type: "file",
                user_can_write: false,
                lock_info: {
                    locked_by: {
                        id: 105,
                    },
                },
            },
        });

        expect(wrapper.find("[data-test=document-dropdown-menu-unlock-item]").exists()).toBeFalsy();
    });

    it(`Given user can write
        When we display the dropdown
        Then I should able to unlock any item locked`, () => {
        const wrapper = unlock_factory({
            item: {
                id: 1,
                title: "my item title",
                type: "file",
                user_can_write: true,
                lock_info: {
                    locked_by: {
                        id: 105,
                    },
                },
            },
        });

        expect(
            wrapper.find("[data-test=document-dropdown-menu-unlock-item]").exists()
        ).toBeTruthy();
    });

    it(`Given item is a file and given user can write
        Then unlock option should be displayed`, () => {
        const wrapper = unlock_factory({
            item: {
                id: 1,
                title: "my file",
                type: "file",
                user_can_write: true,
                lock_info: {
                    id: 101,
                },
            },
        });

        expect(
            wrapper.find("[data-test=document-dropdown-menu-unlock-item]").exists()
        ).toBeTruthy();
    });

    it(`unlock document on click`, () => {
        const item = {
            id: 1,
            title: "my file",
            type: "file",
            user_can_write: true,
            lock_info: {
                locked_by: {
                    id: 105,
                },
            },
        };
        const wrapper = unlock_factory({
            item,
        });

        wrapper.get("[data-test=document-dropdown-menu-unlock-item]").trigger("click");

        expect(store.dispatch).toHaveBeenCalledWith("unlockDocument", item);
    });
});
