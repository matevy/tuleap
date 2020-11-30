/*
 * Copyright (c) Enalean, 2019. All Rights Reserved.
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
 *
 */

import { shallowMount } from "@vue/test-utils";
import localVue from "../../../helpers/local-vue.js";
import { USER_CANNOT_PROPAGATE_DELETION_TO_WIKI_SERVICE } from "../../../constants.js";
import ConfirmationModal from "./ModalConfirmDeletion.vue";
import { createStoreMock } from "../../../../../../../src/scripts/vue-components/store-wrapper-jest.js";
import VueRouter from "vue-router";
import * as tlp from "tlp";

jest.mock("tlp");

describe("ModalConfirmDeletion", () => {
    let router, state, store_options, store;

    function getDeletionModal(props = {}) {
        return shallowMount(ConfirmationModal, {
            localVue,
            mocks: { $store: store },
            propsData: {
                ...props,
            },
            stubs: ["delete-associated-wiki-page-checkbox"],
            router,
        });
    }

    beforeEach(() => {
        router = new VueRouter({
            routes: [
                {
                    path: "folder/42",
                    name: "folder",
                },
            ],
        });
        state = {
            error: {
                has_modal_error: false,
            },
            currently_previewed_item: null,
            current_folder: { id: 42 },
        };
        store_options = {
            state,
        };
        store = createStoreMock(store_options);
        jest.spyOn(store, "dispatch").mockImplementation();

        jest.spyOn(tlp, "createModal").mockReturnValue({
            addEventListener: () => {},
            show: () => {},
            hide: () => {},
        });

        store.getters.is_item_a_wiki = () => false;
        store.getters.is_item_a_folder = () => false;
    });

    describe("When the item is a wiki", () => {
        let item;

        beforeEach(() => {
            item = {
                id: 42,
                title: "my wiki",
                wiki_properties: {
                    page_name: "my wiki",
                    page_id: 123,
                },
                type: "wiki",
            };

            store.getters.is_item_a_wiki = () => true;
        });

        it(`When some docman wikis reference the same wiki page, then it should add a checkbox`, async () => {
            store.dispatch.mockImplementation((actionName) => {
                if (actionName === "getWikisReferencingSameWikiPage") {
                    return [
                        {
                            id: 43,
                            title: "my other wiki",
                            wiki_properties: {
                                page_name: "my wiki",
                                page_id: 123,
                            },
                            type: "wiki",
                        },
                    ];
                }
                return [];
            });

            const deletion_modal = await getDeletionModal({ item });
            await deletion_modal.vm.$nextTick();

            expect(store.dispatch).toHaveBeenCalledWith("getWikisReferencingSameWikiPage", item);
            expect(deletion_modal.find("[data-test=delete-wiki-checkbox]").exists()).toBeTruthy();
        });

        it(`When there is a problem retrieving the wiki page referencers (either not found or either unreadable), then it should not add a checkbox`, async () => {
            store.dispatch.mockImplementation((actionName) => {
                if (actionName === "getWikisReferencingSameWikiPage") {
                    return USER_CANNOT_PROPAGATE_DELETION_TO_WIKI_SERVICE;
                }
                return undefined;
            });

            const deletion_modal = await getDeletionModal({ item });

            expect(store.dispatch).toHaveBeenCalledWith("getWikisReferencingSameWikiPage", item);
            expect(deletion_modal.find("[data-test=checkbox]").exists()).toBeFalsy();
        });

        it(`when it does not reference an existing wiki page, then it should not add a checkbox`, () => {
            item.wiki_properties.page_id = null;

            const deletion_modal = getDeletionModal({ item });

            expect(store.dispatch).not.toHaveBeenCalled();
            expect(deletion_modal.find("[data-test=checkbox]").exists()).toBeFalsy();
        });
    });

    it("When the item is a folder, then it should display a special warning and the checkbox should not be shown", () => {
        const item = {
            id: 42,
            title: "my folder",
            type: "folder",
        };

        store.getters.is_item_a_folder = () => true;

        const deletion_modal = getDeletionModal({ item });

        expect(store.dispatch).not.toHaveBeenCalled();
        expect(deletion_modal.find("[data-test=delete-folder-warning]").exists()).toBeTruthy();
        expect(deletion_modal.find("[data-test=checkbox]").exists()).toBeFalsy();
    });

    it(`when I click on the delete button, it deletes the item`, () => {
        const item = {
            id: 42,
            title: "my folder",
            type: "folder",
        };

        const additional_options = {};

        const deletion_modal = getDeletionModal({ item, additional_options });
        const deleteItem = jest.spyOn(deletion_modal.vm, "deleteItem");
        deletion_modal.get("[data-test=document-confirm-deletion-button]").trigger("click");

        expect(deleteItem).toHaveBeenCalled();
    });

    describe("Redirection after deletion", () => {
        it("Closes the quick look pane when the item to be deleted is currently previewed", async () => {
            const item = {
                id: 50,
                title: "my file",
                type: "file",
                parent_id: 42,
            };

            const additional_options = {};

            store.state.currently_previewed_item = item;
            store.getters.is_item_a_folder = () => false;

            const deletion_modal = getDeletionModal({ item, additional_options });
            jest.spyOn(deletion_modal.vm.$router, "replace");

            await deletion_modal.vm.$router.push("preview/50");
            await deletion_modal.vm.deleteItem();
            await deletion_modal.vm.$nextTick();

            expect(store.dispatch).toHaveBeenCalledWith("deleteItem", [item, additional_options]);
            expect(deletion_modal.vm.$store.commit).toHaveBeenCalledWith(
                "showPostDeletionNotification"
            );
            expect(store.commit).toHaveBeenCalledWith("updateCurrentlyPreviewedItem", null);
            expect(deletion_modal.vm.$router.replace).toHaveBeenCalledWith({
                name: "folder",
                params: { item_id: 42 },
            });
        });

        it("redirects to the parent folder when the item to be deleted is the current folder", async () => {
            const item = {
                id: 42,
                title: "my folder",
                type: "folder",
                parent_id: 41,
            };

            const additional_options = {};
            const deletion_modal = getDeletionModal({ item, additional_options });

            store.getters.is_item_a_folder = () => true;
            jest.spyOn(deletion_modal.vm.$router, "replace");

            await deletion_modal.vm.deleteItem();
            await deletion_modal.vm.$nextTick();

            expect(store.dispatch).toHaveBeenCalledWith("deleteItem", [item, additional_options]);
            expect(deletion_modal.vm.$store.commit).toHaveBeenCalledWith(
                "showPostDeletionNotification"
            );
            expect(store.commit).toHaveBeenCalledWith("updateCurrentlyPreviewedItem", null);
            expect(deletion_modal.vm.$router.replace).toHaveBeenCalledWith({
                name: "folder",
                params: { item_id: 41 },
            });
        });
    });
});
