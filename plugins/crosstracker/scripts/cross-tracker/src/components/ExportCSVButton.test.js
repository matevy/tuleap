/*
 * Copyright (c) Enalean, 2018-Present. All Rights Reserved.
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
import { localVue } from "../helpers/local-vue";
import { createStoreMock } from "../../../../../../src/scripts/vue-components/store-wrapper-jest";
import { createStore } from "../store/index.js";
import ExportCSVButton from "./ExportCSVButton.vue";
import * as rest_querier from "../api/rest-querier.js";
import * as download_helper from "../helpers/download-helper.js";
import * as bom_helper from "../helpers/bom-helper.js";

describe("ExportCSVButton", () => {
    let download, getCSVReport, addBOM;
    beforeEach(() => {
        download = jest.spyOn(download_helper, "download").mockImplementation(() => {});
        getCSVReport = jest.spyOn(rest_querier, "getCSVReport");
        addBOM = jest.spyOn(bom_helper, "addBOM");
    });

    function instantiateComponent() {
        return shallowMount(ExportCSVButton, {
            localVue,
            mocks: {
                $store: createStoreMock(createStore()),
            },
        });
    }

    describe("exportCSV()", () => {
        it(`When the server responds,
            then it will hide feedbacks,
            show a spinner and offer to download a CSV file with the results`, async () => {
            const wrapper = instantiateComponent();
            wrapper.vm.$store.state.report_id = 36;
            const csv = `"id"\r\n72\r\n17\r\n`;
            getCSVReport.mockResolvedValue(csv);
            addBOM.mockImplementation((csv) => csv);

            const promise = wrapper.vm.exportCSV();

            expect(wrapper.vm.is_loading).toBe(true);
            await promise;

            expect(wrapper.vm.$store.commit).toHaveBeenCalledWith("resetFeedbacks");
            expect(getCSVReport).toHaveBeenCalledWith(36);
            expect(download).toHaveBeenCalledWith(csv, "export-36.csv", "text/csv;encoding:utf-8");
            expect(wrapper.vm.is_loading).toBe(false);
        });

        it("When there is a REST error, then it will be shown", async () => {
            const wrapper = instantiateComponent();
            getCSVReport.mockImplementation(() =>
                Promise.reject({
                    response: {
                        status: 404,
                        text: () => Promise.resolve("Report with id 90 not found"),
                    },
                })
            );

            await wrapper.vm.exportCSV();

            expect(wrapper.vm.is_loading).toBe(false);
            expect(wrapper.vm.$store.commit).toHaveBeenCalledWith(
                "setErrorMessage",
                "Report with id 90 not found"
            );
        });

        it("When there is a 50x REST error, then a generic error message will be shown", async () => {
            const wrapper = instantiateComponent();
            getCSVReport.mockImplementation(() =>
                Promise.reject({
                    response: {
                        status: 503,
                    },
                })
            );

            await wrapper.vm.exportCSV();

            expect(wrapper.vm.is_loading).toBe(false);
            expect(wrapper.vm.$store.commit).toHaveBeenCalledWith(
                "setErrorMessage",
                expect.any(String)
            );
        });
    });
});
