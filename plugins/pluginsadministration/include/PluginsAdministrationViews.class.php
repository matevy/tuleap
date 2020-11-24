<?php
/**
 * Copyright (c) Enalean, 2015-Present. All Rights Reserved.
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
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

/*
 * PluginsAdministrationViews
 */

use Tuleap\Admin\AdminPageRenderer;
use Tuleap\PluginsAdministration\AvailablePluginsPresenter;
use Tuleap\PluginsAdministration\PluginDisablerVerifier;
use Tuleap\PluginsAdministration\PluginPropertiesPresenter;

class PluginsAdministrationViews extends Views
{

    /** @var PluginManager */
    private $plugin_manager;

    /** @var PluginDependencySolver */
    private $dependency_solver;

    /** @var TemplateRendererFactory */
    private $renderer;

    /**
     * @var PluginDisablerVerifier
     */
    private $plugin_disabler_verifier;

    function __construct(&$controler, $view = null)
    {
        $this->View($controler, $view);
        $this->plugin_manager           = PluginManager::instance();
        $this->dependency_solver        = new PluginDependencySolver($this->plugin_manager);
        $this->renderer                 = TemplateRendererFactory::build()->getRenderer(
            PLUGINSADMINISTRATION_TEMPLATE_DIR
        );
        $plugin_administration          = $this->plugin_manager->getPluginByName('pluginsadministration');
        $this->plugin_disabler_verifier = new PluginDisablerVerifier(
            $plugin_administration,
            ForgeConfig::get('sys_plugins_that_can_not_be_disabled_from_the_web_ui')
        );
    }

    public function header()
    {
        $title = dgettext('tuleap-pluginsadministration', 'Plugins');
        $GLOBALS['HTML']->header(array('title'=>$title, 'selected_top_tab' => 'admin', 'main_classes' => array('tlp-framed')));
    }

    function footer()
    {
        $GLOBALS['HTML']->footer(array());
    }

    public function display($view = '')
    {
        $renderer       = new AdminPageRenderer();
        $request        = HTTPRequest::instance();
        $plugin_factory = PluginFactory::instance();

        switch ($view) {
            case 'restrict':
                $plugin_resource_restrictor = $this->getPluginResourceRestrictor();
                $plugin                     = $plugin_factory->getPluginById($request->get('plugin_id'));

                if ($plugin->getScope() !== Plugin::SCOPE_PROJECT) {
                    $GLOBALS['Response']->addFeedback(Feedback::ERROR, 'This project cannot be restricted');
                    $GLOBALS['Response']->redirect('/plugins/pluginsadministration/');
                }

                $presenter = $this->getPluginResourceRestrictorPresenter($plugin, $plugin_resource_restrictor);

                $renderer->renderAPresenter(
                    dgettext('tuleap-pluginsadministration', 'Plugins'),
                    ForgeConfig::get('codendi_dir') . '/src/templates/resource_restrictor',
                    PluginsAdministration_ManageAllowedProjectsPresenter::TEMPLATE,
                    $presenter
                );

                break;

            case 'properties':
                if ($request->exist('plugin_id')) {
                    $plugin = $plugin_factory->getPluginById($request->get('plugin_id'));

                    if (! $plugin) {
                        $GLOBALS['HTML']->redirect('/plugins/pluginsadministration/');
                        return;
                    } else {
                        $presenter = $this->getPluginPropertiesPresenter(
                            $plugin
                        );

                        $renderer->renderAPresenter(
                            dgettext('tuleap-pluginsadministration', 'Plugins'),
                            PLUGINSADMINISTRATION_TEMPLATE_DIR,
                            'plugin-properties',
                            $presenter
                        );
                    }
                }

                break;

            case 'available':
                $this->_searchPlugins();
                $renderer->renderANoFramedPresenter(
                    dgettext('tuleap-pluginsadministration', 'Plugins'),
                    PLUGINSADMINISTRATION_TEMPLATE_DIR,
                    'available-plugins',
                    $this->getAvailablePluginsPresenter()
                );
                break;

            case 'installed':
                $this->_searchPlugins();
                $renderer = new AdminPageRenderer();
                $renderer->renderANoFramedPresenter(
                    dgettext('tuleap-pluginsadministration', 'Plugins'),
                    PLUGINSADMINISTRATION_TEMPLATE_DIR,
                    'installed-plugins',
                    $this->getInstalledPluginsPresenter()
                );
        }
    }

    // {{{ Views
    function browse()
    {
        $output = '';
        $output .= $this->getInstalledPluginsPresenter();
        $output .= $this->getAvailablePluginsPresenter();
        echo $output;
    }

    private function getFormattedReadme($name)
    {
        $readme_file    = $this->plugin_manager->getInstallReadme($name);
        $readme_content = $this->plugin_manager->fetchFormattedReadme($readme_file);
        return $readme_content;
    }

    private function getPluginPropertiesPresenter(Plugin $plugin)
    {
        $plugin_info = $plugin->getPluginInfo();
        $descriptor  = $plugin_info->getPluginDescriptor();

        $name = $descriptor->getFullName();
        if (strlen(trim($name)) === 0) {
            $name = get_class($plugin);
        }

        $readme          = $this->getFormattedReadme($plugin->getName());
        $is_there_readme = ! empty($readme);

        $dependencies           = implode(', ', $plugin->getDependencies());
        $are_there_dependencies = ! empty($dependencies);

        $properties = array();
        if (ForgeConfig::get('sys_plugins_editable_configuration')) {
            $descs = $plugin_info->getPropertyDescriptors();
            $keys  = $descs->getKeys();
            $iter  = $keys->iterator();
            while ($iter->valid()) {
                $key       = $iter->current();
                $desc      = $descs->get($key);
                $prop_name = $desc->getName();

                $properties[] = array(
                    'name' => $prop_name,
                    'is_bool' => is_bool($desc->getValue()),
                    'value' => $desc->getValue()
                );

                $iter->next();
            }
        }
        $are_there_properties = ! empty($properties);

        $additional_options           = $plugin->getAdministrationOptions();
        $are_there_additional_options = ! empty($additional_options);

        $enable_switch = $this->getFlag(
            $plugin->getId(),
            $this->plugin_manager->isPluginAvailable($plugin),
            (strcasecmp(get_class($plugin), 'PluginsAdministrationPlugin') === 0),
            'properties'
        );
        $is_there_enable_switch = ! empty($enable_switch);

        $csrf_token = new CSRFSynchronizerToken(
            '/plugins/pluginsadministration/?view=properties&plugin_id=' . urlencode($plugin->getId())
        );

        return new PluginPropertiesPresenter(
            $plugin->getId(),
            $name,
            $descriptor->getVersion(),
            $descriptor->getDescription(),
            $plugin->getScope(),
            $is_there_enable_switch,
            $enable_switch,
            $are_there_dependencies,
            $dependencies,
            $is_there_readme,
            $readme,
            $are_there_properties,
            $properties,
            $are_there_additional_options,
            $additional_options,
            $csrf_token
        );
    }

    private function getPluginResourceRestrictorPresenter(
        Plugin $plugin,
        PluginResourceRestrictor $plugin_resource_restrictor
    ) {
        return new PluginsAdministration_ManageAllowedProjectsPresenter(
            $plugin,
            $plugin_resource_restrictor->searchAllowedProjectsOnPlugin($plugin),
            $plugin_resource_restrictor->isPluginRestricted($plugin)
        );
    }

    private function getPluginResourceRestrictor()
    {
        return new PluginResourceRestrictor(
            new RestrictedPluginDao()
        );
    }

    var $_plugins;

    function _emphasis($name, $enable)
    {
        if (!$enable) {
            $name = '<span class="pluginsadministration_unavailable">'.$name.'</span>';
        }
        return $name;
    }

    function _searchPlugins()
    {
        if (!$this->_plugins) {
            $this->_plugins    = array();

            $plugin_manager               = $this->plugin_manager;
            try {
                $forgeUpgradeConfig = new ForgeUpgradeConfig(new System_Command());
                $forgeUpgradeConfig->loadDefaults();
                $noFUConfig = array();
            } catch (Exception $e) {
                $GLOBALS['Response']->addFeedback('warning', $e->getMessage());
            }

            $plugins = $plugin_manager->getAllPlugins();
            foreach ($plugins as $plugin) {
                $plug_info  = $plugin->getPluginInfo();
                $descriptor = $plug_info->getPluginDescriptor();
                $available = $plugin_manager->isPluginAvailable($plugin);
                $name = $descriptor->getFullName();
                if (strlen(trim($name)) === 0) {
                    $name = get_class($plugin);
                }
                $dont_touch    = ! $this->plugin_disabler_verifier->canPluginBeDisabled($plugin);
                $dont_restrict = $plugin->getScope() !== Plugin::SCOPE_PROJECT;

                $this->_plugins[] = array(
                    'plugin_id'     => $plugin->getId(),
                    'name'          => $name,
                    'description'   => $descriptor->getDescription(),
                    'version'       => $descriptor->getVersion(),
                    'available'     => $available,
                    'scope'         => $plugin->getScope(),
                    'dont_touch'    => $dont_touch,
                    'dont_restrict' => $dont_restrict);

                if (isset($noFUConfig, $forgeUpgradeConfig) && !$forgeUpgradeConfig->existsInPath($plugin->getFilesystemPath())) {
                    $noFUConfig[] = array('name' => $name, 'plugin' => $plugin);
                }
            }

            // ForgeUpgrade configuration warning
            if (isset($noFUConfig) && count($noFUConfig) && isset($GLOBALS['forgeupgrade_file'])) {
                $txt = 'Some plugins are not referenced in ForgeUpgrade configuration, please add the following in <code>'.$GLOBALS['forgeupgrade_file'].'.</code><br/>';
                foreach ($noFUConfig as $plugInfo) {
                    $txt .= '<code>path[]="'.$plugInfo['plugin']->getFilesystemPath().'"</code><br/>';
                }
                $GLOBALS['Response']->addFeedback('warning', $txt, CODENDI_PURIFIER_DISABLED);
            }
        }
    }

    private function getInstalledPluginsPresenter()
    {
        usort(
            $this->_plugins,
            function ($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            }
        );

        $i       = 0;
        $plugins = array();
        foreach ($this->_plugins as $plugin) {
            $is_there_unmet_dependencies = false;
            $unmet_dependencies          = $this->dependency_solver->getInstalledDependencies(
                $this->plugin_manager->getPluginById($plugin['plugin_id'])
            );

            if ($unmet_dependencies) {
                $is_there_unmet_dependencies = true;
            }
            $plugins[] = array(
                'available'                   => $plugin['available']? '': 'pluginsadministration_unavailable',
                'name'                        => $plugin['name'],
                'version'                     => $plugin['version'],
                'description'                 => $plugin['description'],
                'flags'                       => $this->getFlag($plugin['plugin_id'], $plugin['available'], $plugin['dont_touch'], 'installed'),
                'scope'                       => $this->getScopeLabel((int) $plugin['scope']),
                'plugin_id'                   => $plugin['plugin_id'],
                'dont_touch'                  => $plugin['dont_touch'],
                'dont_restrict'               => $plugin['dont_restrict'],
                'is_there_unmet_dependencies' => $is_there_unmet_dependencies,
                'unmet_dependencies'          => $unmet_dependencies,
                'csrf_token'                  => new CSRFSynchronizerToken('/plugins/pluginsadministration/')
            );

            $i++;
        }

        return new PluginsAdministration_Presenter_InstalledPluginsPresenter($plugins);
    }

    private function getScopeLabel(int $scope): string
    {
        if ($scope === Plugin::SCOPE_PROJECT) {
            return dgettext('tuleap-pluginsadministration', 'Projects');
        }

        if ($scope === Plugin::SCOPE_USER) {
            return dgettext('tuleap-pluginsadministration', 'Users');
        }

        return dgettext('tuleap-pluginsadministration', 'System');
    }

    private function getFlag($plugin_id, $is_active, $dont_touch, $view)
    {
        $output  = '';
        $checked = '';
        $title   = dgettext('tuleap-pluginsadministration', 'Do not avail this plugin');
        $action  = 'available';

        if ($is_active) {
            $checked = 'checked';
            $title   = dgettext('tuleap-pluginsadministration', 'Avail this plugin');
            $action  = 'unavailable';
        }

        if (! $dont_touch) {
            $csrf_token = new CSRFSynchronizerToken('/plugins/pluginsadministration/');
            $output = '
            <form id="plugin-switch-form-'.$plugin_id.'" action="?action='. $action .'&plugin_id='. $plugin_id .'&view='.$view.'" method="POST">
                ' . $csrf_token->fetchHTMLInput() . '
                <div class="tlp-switch tlp-table-cell-actions-button">
                    <input type="checkbox" data-form-id="plugin-switch-form-'.$plugin_id.'" id="plugin-switch-toggler-'.$plugin_id.'" class="tlp-switch-checkbox" '.$checked.'>
                    <label for="plugin-switch-toggler-'.$plugin_id.'" class="tlp-switch-button">'.$title.'</label>
                </div>
            </form>';
        }

        return $output;
    }

    private function getAvailablePluginsPresenter()
    {
        $plugins = $this->plugin_manager->getNotYetInstalledPlugins();

        foreach ($plugins as $key => $plugin) {
            $plugins[$key]['is_there_readme'] = false;
            $readme                           = $this->getFormattedReadme($plugin['name']);

            if (! empty($readme)) {
                $plugins[$key]['is_there_readme'] = true;
                $plugins[$key]['readme']          = $readme;
            }

            $plugins[$key]['is_there_unmet_dependencies'] = false;
            $dependencies                                 = $this->dependency_solver->getUnmetInstalledDependencies($plugin['name']);

            if (! empty($dependencies)) {
                $plugins[$key]['is_there_unmet_dependencies'] = true;
                $plugins[$key]['unmet_dependencies'] = $dependencies;
            }
        }

        return new AvailablePluginsPresenter($plugins, new CSRFSynchronizerToken('/plugins/pluginsadministration/'));
    }
}
