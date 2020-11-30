/*
 * Copyright (c) Enalean, 2019-Present. All Rights Reserved.
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

describe("Agile Dashboard", function () {
    let project_id: string;
    context("Project administrators", function () {
        before(function () {
            cy.clearCookie("__Host-TULEAP_session_hash");
            cy.ProjectAdministratorLogin();
            cy.getProjectId("agile-dashboard").as("project_id");
        });

        beforeEach(function () {
            Cypress.Cookies.preserveOnce("__Host-TULEAP_PHPSESSID", "__Host-TULEAP_session_hash");
        });

        it("can access to admin section", function () {
            project_id = this.project_id;
            cy.visit("/plugins/agiledashboard/?group_id=" + project_id + "&action=admin");
        });

        it("should start scrum", function () {
            cy.visitProjectService("agile-dashboard", "Agile Dashboard");
            cy.get("[data-test=start-scrum]").click();

            cy.contains(
                "[data-test=feedback]",
                "We created an initial scrum configuration for you. Enjoy!",
                {
                    timeout: 20000,
                }
            );
        });

        it("should start a Kanban with Scrum elements", function () {
            cy.get("[data-test=link-to-ad-administration]").click({ force: true });
            cy.get("[data-test=admin-kanban-pane]").click();
            cy.get("[data-test=admin-kanban-activate-checkbox]").check();
            cy.get("[data-test=ad-service-submit]").click();

            cy.visitProjectService("agile-dashboard", "Agile Dashboard");
            cy.get("[data-test=add-kanban-button]").click();

            cy.get("[data-test=add-kanban-modal]").within(() => {
                cy.get("[data-test=kanban-name]").type("My kanban from scrum");
                cy.get("[data-test=tracker-kanban]").select("Epics");
                cy.get("[data-test=create-kanban-modal-submit]").click();
            });
            cy.contains(
                "[data-test=feedback]",
                "Kanban My kanban from scrum successfully created."
            );
            cy.contains("[data-test=kanban-home-kanban-title]", "My kanban from scrum");
        });
    });

    describe("Project members", function () {
        before(function () {
            cy.clearCookie("__Host-TULEAP_session_hash");
            cy.projectMemberLogin();
        });

        beforeEach(function () {
            Cypress.Cookies.preserveOnce("__Host-TULEAP_PHPSESSID", "__Host-TULEAP_session_hash");
        });

        it("can not for admin page access", function () {
            cy.visit("/plugins/agiledashboard/?group_id=" + project_id + "&action=admin");
            cy.get("[data-test=scrum_title]").contains("Scrum");
        });
    });
});
