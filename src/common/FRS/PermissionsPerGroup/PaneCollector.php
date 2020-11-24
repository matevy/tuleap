<?php
/**
 * Copyright (c) Enalean, 2018. All Rights Reserved.
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

namespace Tuleap\FRS\PermissionsPerGroup;

use ForgeConfig;
use Project;
use TemplateRendererFactory;
use Tuleap\Layout\IncludeAssets;

class PaneCollector
{
    /**
     * @var PermissionPerGroupFRSServicePresenterBuilder
     */
    private $service_presenter_builder;
    /**
     * @var PermissionPerGroupFRSPackagesPresenterBuilder
     */
    private $packages_pane_builder;

    public function __construct(
        PermissionPerGroupFRSServicePresenterBuilder $service_presenter_builder,
        PermissionPerGroupFRSPackagesPresenterBuilder $packages_pane_builder
    ) {
        $this->service_presenter_builder = $service_presenter_builder;
        $this->packages_pane_builder     = $packages_pane_builder;
    }

    public function collectPane(Project $project, $selected_ugroup = null)
    {
        if (! $project->usesFile()) {
            return;
        }

        $service_presenter = $this->service_presenter_builder->getPanePresenter($project, $selected_ugroup);
        $package_presenter = $this->packages_pane_builder->getPanePresenter($project, $selected_ugroup);

        $tuleap_base_dir = ForgeConfig::get('tuleap_dir');
        $include_assets  = new IncludeAssets(
            $tuleap_base_dir . '/src/www/assets',
            '/assets'
        );

        $GLOBALS['HTML']->includeFooterJavascriptFile($include_assets->getFileURL('frs-permissions.js'));

        $global_presenter = new GlobalPresenter($service_presenter, $package_presenter);

        $templates_dir = $tuleap_base_dir . '/src/templates/frs';
        $content       = TemplateRendererFactory::build()
            ->getRenderer($templates_dir)
            ->renderToString('project-admin-permission-per-group', $global_presenter);

        return $content;
    }
}
