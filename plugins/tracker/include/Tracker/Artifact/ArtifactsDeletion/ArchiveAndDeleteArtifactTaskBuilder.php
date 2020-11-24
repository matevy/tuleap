<?php
/**
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

namespace Tuleap\Tracker\Artifact\ArtifactsDeletion;

use CrossReferenceManager;
use EventManager;
use ForgeConfig;
use Logger;
use PermissionsDao;
use PermissionsManager;
use Tracker_Artifact_PriorityDao;
use Tracker_Artifact_PriorityHistoryDao;
use Tracker_Artifact_PriorityManager;
use Tracker_Artifact_XMLExport;
use Tracker_ArtifactDao;
use Tracker_ArtifactFactory;
use Tracker_FormElement_Field_ComputedDao;
use Tracker_FormElement_Field_ComputedDaoCache;
use Tracker_FormElementFactory;
use Tracker_Workflow_Trigger_RulesBuilderFactory;
use Tracker_Workflow_Trigger_RulesDao;
use Tracker_Workflow_Trigger_RulesManager;
use Tracker_Workflow_Trigger_RulesProcessor;
use TrackerFactory;
use TrackerXmlExport;
use Tuleap\DB\DBConnection;
use Tuleap\DB\DBFactory;
use Tuleap\Tracker\Admin\ArtifactLinksUsageDao;
use Tuleap\Tracker\Artifact\ArtifactWithTrackerStructureExporter;
use Tuleap\Tracker\FormElement\Field\ArtifactLink\Nature\NatureDao;
use Tuleap\Tracker\FormElement\Field\ArtifactLink\Nature\NaturePresenterFactory;
use Tuleap\Tracker\Artifact\RecentlyVisited\RecentlyVisitedDao;
use Tuleap\Tracker\Workflow\WorkflowBackendLogger;
use Tuleap\Tracker\Workflow\WorkflowRulesManagerLoopSafeGuard;
use UserManager;
use UserXMLExportedCollection;
use UserXMLExporter;
use XML_RNGValidator;
use XML_SimpleXMLCDATAFactory;

class ArchiveAndDeleteArtifactTaskBuilder
{
    public function build(Logger $logger)
    {
        $user_manager             = UserManager::instance();
        $tracker_artifact_factory = Tracker_ArtifactFactory::instance();
        $formelement_factory      = Tracker_FormElementFactory::instance();
        $event_manager            = EventManager::instance();
        $rng_validator            = new XML_RNGValidator();
        $user_xml_exporter        = new UserXMLExporter(
            $user_manager,
            new UserXMLExportedCollection($rng_validator, new XML_SimpleXMLCDATAFactory())
        );

        $workflow_logger = new WorkflowBackendLogger(new \BackendLogger(), ForgeConfig::get('sys_logger_level'));

        return new ArchiveAndDeleteArtifactTask(
            new ArtifactWithTrackerStructureExporter(
                new TrackerXmlExport(
                    TrackerFactory::instance(),
                    new Tracker_Workflow_Trigger_RulesManager(
                        new Tracker_Workflow_Trigger_RulesDao(),
                        $formelement_factory,
                        new Tracker_Workflow_Trigger_RulesProcessor(new \Tracker_Workflow_WorkflowUser(), $workflow_logger),
                        $workflow_logger,
                        new Tracker_Workflow_Trigger_RulesBuilderFactory($formelement_factory),
                        new WorkflowRulesManagerLoopSafeGuard($workflow_logger)
                    ),
                    $rng_validator,
                    new Tracker_Artifact_XMLExport(
                        $rng_validator,
                        $tracker_artifact_factory,
                        false,
                        $user_xml_exporter
                    ),
                    $user_xml_exporter,
                    $event_manager,
                    new NaturePresenterFactory(new NatureDao(), new ArtifactLinksUsageDao()),
                    new ArtifactLinksUsageDao()
                ),
                new \Tuleap\XMLConvertor()
            ),
            new ArtifactDependenciesDeletor(
                new PermissionsManager(new PermissionsDao()),
                new CrossReferenceManager(),
                new Tracker_Artifact_PriorityManager(
                    new Tracker_Artifact_PriorityDao(),
                    new Tracker_Artifact_PriorityHistoryDao(),
                    $user_manager,
                    $tracker_artifact_factory
                ),
                new Tracker_ArtifactDao(),
                new Tracker_FormElement_Field_ComputedDaoCache(new Tracker_FormElement_Field_ComputedDao()),
                new RecentlyVisitedDao(),
                new PendingArtifactRemovalDao()
            ),
            $event_manager,
            DBFactory::getMainTuleapDBConnection(),
            $logger
        );
    }
}
