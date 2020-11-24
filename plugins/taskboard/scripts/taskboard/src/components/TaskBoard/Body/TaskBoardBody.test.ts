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

import { shallowMount, Wrapper } from "@vue/test-utils";
import TaskBoardBody from "./TaskBoardBody.vue";
import { createStoreMock } from "../../../../../../../../src/www/scripts/vue-components/store-wrapper-jest";
import SwimlaneSkeleton from "./Swimlane/Skeleton/SwimlaneSkeleton.vue";
import CollapsedSwimlane from "./Swimlane/CollapsedSwimlane.vue";
import { createTaskboardLocalVue } from "../../../helpers/local-vue-for-test";
import * as mapper from "../../../helpers/list-value-to-column-mapper";
import InvalidMappingSwimlane from "./Swimlane/InvalidMappingSwimlane.vue";
import { RootState } from "../../../store/type";
import { Swimlane, ColumnDefinition } from "../../../type";

interface FakeDrake {
    on: jest.SpyInstance;
    destroy: jest.SpyInstance;
}

jest.mock("dragula", () => {
    const fake_drake = {
        on: jest.fn(),
        destroy: jest.fn(),
        cancel: jest.fn()
    };
    return jest.fn((): FakeDrake => fake_drake);
});

async function createWrapper(
    swimlanes: Swimlane[],
    are_closed_items_displayed: boolean
): Promise<Wrapper<TaskBoardBody>> {
    return shallowMount(TaskBoardBody, {
        localVue: await createTaskboardLocalVue(),
        mocks: {
            $store: createStoreMock({
                state: {
                    are_closed_items_displayed,
                    swimlane: { swimlanes },
                    column: {}
                } as RootState
            })
        }
    });
}

afterEach(() => {
    jest.clearAllMocks();
});

describe("TaskBoardBody", () => {
    it("displays swimlanes for solo cards or cards with children", async () => {
        const swimlanes = [
            {
                card: {
                    id: 43,
                    has_children: false,
                    is_open: true,
                    is_collapsed: false
                }
            } as Swimlane,
            {
                card: {
                    id: 44,
                    has_children: true,
                    is_open: true,
                    is_collapsed: false
                }
            } as Swimlane
        ];
        jest.spyOn(mapper, "getColumnOfCard").mockReturnValue({
            id: 21,
            label: "Todo"
        } as ColumnDefinition);
        const wrapper = await createWrapper(swimlanes, true);
        expect(wrapper.element).toMatchSnapshot();
    });

    it("displays collapsed swimlanes", async () => {
        const swimlanes = [
            {
                card: {
                    id: 43,
                    has_children: false,
                    is_open: true,
                    is_collapsed: true
                }
            } as Swimlane
        ];
        const wrapper = await createWrapper(swimlanes, true);
        expect(wrapper.contains(CollapsedSwimlane)).toBe(true);
    });

    it(`displays swimlanes with invalid mapping`, async () => {
        const swimlanes = [
            {
                card: {
                    id: 43,
                    has_children: false,
                    is_open: true,
                    is_collapsed: false
                }
            } as Swimlane
        ];
        jest.spyOn(mapper, "getColumnOfCard").mockReturnValue(undefined);
        const wrapper = await createWrapper(swimlanes, true);
        expect(wrapper.contains(InvalidMappingSwimlane)).toBe(true);
    });

    it("does not display swimlane that are closed if user wants to hide them", async () => {
        const swimlanes = [
            {
                card: {
                    id: 43,
                    has_children: false,
                    is_open: false,
                    is_collapsed: true
                }
            } as Swimlane
        ];
        const wrapper = await createWrapper(swimlanes, false);
        expect(wrapper.element.children.length).toBe(0);
    });

    it("loads all swimlanes as soon as the component is created", async () => {
        const $store = createStoreMock({ state: { swimlane: {} } });
        shallowMount(TaskBoardBody, {
            mocks: { $store },
            localVue: await createTaskboardLocalVue()
        });
        expect($store.dispatch).toHaveBeenCalledWith("swimlane/loadSwimlanes");
    });

    it("displays skeletons when swimlanes are being loaded", async () => {
        const $store = createStoreMock({ state: { swimlane: { is_loading_swimlanes: true } } });
        const wrapper = shallowMount(TaskBoardBody, {
            mocks: { $store },
            localVue: await createTaskboardLocalVue()
        });
        expect(wrapper.contains(SwimlaneSkeleton)).toBe(true);
    });
});
