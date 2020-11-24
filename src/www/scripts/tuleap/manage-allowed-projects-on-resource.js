/**
 * Copyright (c) Enalean - 2015 - 2016. All rights reserved
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

/* global tlp:readonly tuleap:readonly */
!(function($) {
    $(function() {
        bindAllowAllEvent();
        bindFilterEvent();
        bindCheckboxesEvent();
        bindDeleteEvent();
        projectAutocompleter();
    });

    function bindAllowAllEvent() {
        $("#allowed-projects-all-allowed").on("change", function() {
            $("#" + $(this).attr("data-form-id")).submit();
        });
    }

    function bindFilterEvent() {
        var filter = document.getElementById("filter-projects");
        if (filter) {
            tlp.filterInlineTable(filter);
        }
    }

    function bindCheckboxesEvent() {
        var checkboxes = $('#allowed-projects-list input[type="checkbox"]:not(#check-all)'),
            select_all = $("#check-all");

        (function toggleAll() {
            select_all.change(function() {
                if ($(this).is(":checked")) {
                    checkboxes.each(function() {
                        $(this).prop("checked", "checked");
                    });
                } else {
                    checkboxes.each(function() {
                        $(this).prop("checked", "");
                    });
                }

                toggleRevokeSelectedButton();
            });
        })();

        (function projectCheckboxesEvent() {
            checkboxes.change(function() {
                select_all.prop("checked", "");
                toggleRevokeSelectedButton();
            });
        })();

        function toggleRevokeSelectedButton() {
            if (
                $('#allowed-projects-list input[type="checkbox"]:not(#check-all):checked').length >
                0
            ) {
                $("#revoke-project").prop("disabled", "");
            } else {
                $("#revoke-project").prop("disabled", "disabled");
            }
        }
    }

    function bindDeleteEvent() {
        var dom_natures_modal_create = document.getElementById("revoke-modal");

        if (dom_natures_modal_create) {
            var tlp_natures_modal_create = tlp.modal(dom_natures_modal_create);

            $("#revoke-project").on("click", function() {
                tlp_natures_modal_create.toggle();
            });

            $("#revoke-confirm").click(function() {
                $("<input>")
                    .attr("type", "hidden")
                    .attr("name", "revoke-project")
                    .attr("value", "1")
                    .appendTo("#projects-allowed-form");

                $("#projects-allowed-form").submit();
            });
        }
    }

    function projectAutocompleter() {
        var autocompleter = document.getElementById("project-to-allow");

        if (autocompleter) {
            tuleap.autocomplete_projects_for_select2(autocompleter, {
                include_private_projects: 1
            });
        }
    }
})(window.jQuery);
