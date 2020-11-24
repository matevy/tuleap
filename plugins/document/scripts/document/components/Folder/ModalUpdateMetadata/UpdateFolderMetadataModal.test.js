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
 * along with Tuleap. If not, see http://www.gnu.org/licenses/.
 *
 */

import { shallowMount } from "@vue/test-utils";
import localVue from "../../../helpers/local-vue.js";

import UpdateFolderMetadataModal from "./UpdateFolderMetadataModal.vue";
import { createStoreMock } from "../../../../../../../src/www/scripts/vue-components/store-wrapper-jest.js";
import EventBus from "../../../helpers/event-bus.js";
import * as tlp from "tlp";

jest.mock("tlp");

describe("UpdateFolderMetadataModal", () => {
    let factory, store;

    beforeEach(() => {
        const general_store = {
            state: {
                current_folder: {
                    id: 42,
                    title: "My current folder",
                    metadata: [
                        {
                            short_name: "title",
                            name: "title",
                            list_value: "My current folder",
                            is_multiple_value_allowed: false,
                            type: "text",
                            is_required: false
                        },
                        {
                            short_name: "custom metadata",
                            name: "custom",
                            value: "value",
                            is_multiple_value_allowed: false,
                            type: "text",
                            is_required: false
                        }
                    ]
                },
                project_id: 102
            }
        };

        store = createStoreMock(general_store, { error: { has_modal_error: false } });

        factory = (props = {}) => {
            return shallowMount(UpdateFolderMetadataModal, {
                localVue,
                mocks: { $store: store },
                propsData: { ...props }
            });
        };

        jest.spyOn(tlp, "modal").mockReturnValue({
            addEventListener: () => {},
            show: () => {},
            hide: () => {}
        });
    });
    describe("Events received by the modal - ", () => {
        it(`Receives the metadata-recursion-metadata-list event,
       Then the metadata_list_to_update  data is updated`, () => {
            const item = {
                id: 7,
                type: "folder",
                metadata: [
                    {
                        short_name: "status",
                        list_value: [
                            {
                                id: 103
                            }
                        ]
                    }
                ]
            };

            const wrapper = factory({ item });
            EventBus.$emit("metadata-recursion-metadata-list", {
                detail: { metadata_list: ["field_1"] }
            });

            expect(wrapper.vm.metadata_list_to_update).toEqual(["field_1"]);
        });
        it(`Receives the metadata-recursion-option event,
       Then the metadata_list_to_update  data is updated`, () => {
            const item = {
                id: 7,
                type: "folder",
                metadata: [
                    {
                        short_name: "status",
                        list_value: [
                            {
                                id: 103
                            }
                        ]
                    }
                ]
            };

            const wrapper = factory({ item });
            EventBus.$emit("metadata-recursion-option", {
                detail: { recursion_option: "all_items" }
            });

            expect(wrapper.vm.recursion_option).toEqual("all_items");
        });
    });
    it("Transform item metadata rest representation", () => {
        store.state.metadata = {
            has_loaded_metadata: false
        };

        const metadata_list_to_update = {
            short_name: "field_1234",
            list_value: [
                {
                    id: 103,
                    value: "my custom displayed value"
                }
            ],
            type: "list",
            is_multiple_value_allowed: false
        };

        const item = {
            id: 7,
            type: "folder",
            metadata: [
                {
                    short_name: "status",
                    list_value: [
                        {
                            id: 103
                        }
                    ]
                },
                metadata_list_to_update
            ]
        };

        const wrapper = factory({ item });

        expect(wrapper.vm.formatted_item_metadata).toEqual([metadata_list_to_update]);
    });
});
