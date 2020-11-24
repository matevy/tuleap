<?php
/**
 * Copyright (c) Enalean, 2011 - Present. All Rights Reserved.
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
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

use Tuleap\Tracker\TrackerColor;
use Tuleap\Tracker\Webhook\WebhookDao;
use Tuleap\Tracker\Webhook\WebhookFactory;
use Tuleap\Tracker\Workflow\WorkflowBackendLogger;
use Tuleap\Tracker\Workflow\WorkflowRulesManagerLoopSafeGuard;

class TrackerFactory
{

    public const LEGACY_SUFFIX = '_from_tv3';

    /** @var array of Tracker */
    protected $trackers;

    /** @var Tracker_HierarchyFactory */
    private $hierarchy_factory;

    /**
     * A protected constructor; prevents direct creation of object
     */
    protected function __construct()
    {
        $this->trackers = array();
    }

    /**
     * Hold an instance of the class
     */
    protected static $_instance;

    /**
     * The singleton method
     *
     * @return TrackerFactory
     */
    public static function instance()
    {
        if (!isset(self::$_instance)) {
            $c = self::class;
            self::$_instance = new $c;
        }
        return self::$_instance;
    }

    /**
     * Allows to inject a fake factory for test. DO NOT USE IT IN PRODUCTION!
     *
     * @param TrackerFactory $factory
     */
    public static function setInstance(TrackerFactory $factory)
    {
        self::$_instance = $factory;
    }

    /**
     * Allows clear factory instance for test. DO NOT USE IT IN PRODUCTION!
     */
    public static function clearInstance()
    {
        self::$_instance = null;
    }

    public function clearCaches()
    {
        $this->trackers = array();

        self::clearInstance();
    }

    /**
     * @param int $id the id of the tracker to retrieve
     * @return Tracker identified by id (null if not found)
     */
    public function getTrackerById($tracker_id)
    {
        if (!isset($this->trackers[$tracker_id])) {
            $this->trackers[$tracker_id] = null;
            if ($row = $this->getDao()->searchById($tracker_id)->getRow()) {
                $this->getCachedInstanceFromRow($row);
            }
        }
        return $this->trackers[$tracker_id];
    }

    /**
     * @param string $shortname the shortname of the tracker we are looking for
     * @param int $project_id the id of the project from wich to retrieve the tracker
     * @return Tracker identified by shortname (null if not found)
     */
    public function getTrackerByShortnameAndProjectId($shortname, $project_id)
    {
        $row = $this->getDao()->searchByItemNameAndProjectId($shortname, $project_id)->getRow();

        if ($row) {
            return $this->getCachedInstanceFromRow($row);
        }
        return null;
    }

    /**
     * Retrieve the list of deleted trackers.
     *
     * @return array
     */
    public function getDeletedTrackers()
    {
        $pending_trackers = $this->getDao()->retrieveTrackersMarkAsDeleted();
        $deleted_trackers = array();

        if ($pending_trackers && ! $pending_trackers->isError()) {
            foreach ($pending_trackers as $pending_tracker) {
                $deleted_trackers[] = $this->getTrackerById($pending_tracker['id']);
            }
        }

        return $deleted_trackers;
    }

    /**
     * Restore a tracker from the list of deleted trackers.
     *
     * @param  int $tracker_id
     *
     * @return bool
     */
    public function restoreDeletedTracker($tracker_id)
    {
        return $this->getDao()->restoreTrackerMarkAsDeleted($tracker_id);
    }

    /**
     * @param int $group_id the project id the trackers to retrieve belong to
     *
     * @return Tracker[]
     */
    public function getTrackersByGroupId($group_id)
    {
        $trackers = array();
        foreach ($this->getDao()->searchByGroupId($group_id) as $row) {
            $tracker_id = $row['id'];
            $trackers[$tracker_id] = $this->getCachedInstanceFromRow($row);
        }
        return $trackers;
    }

    /**
     * @return Tracker[]
     */
    public function getTrackersByGroupIdUserCanView($group_id, PFUser $user)
    {
        $trackers = array();
        foreach ($this->getDao()->searchByGroupId($group_id) as $row) {
            $tracker_id = $row['id'];
            $tracker    = $this->getCachedInstanceFromRow($row);
            if ($tracker->userCanView($user)) {
                $trackers[$tracker_id] = $tracker;
            }
        }
        return $trackers;
    }

    /**
     * @return Tracker[]
     */
    public function getTrackersByProjectIdUserCanAdministration($project_id, PFUser $user)
    {
        $trackers = [];
        foreach ($this->getDao()->searchByGroupId($project_id) as $row) {
            $tracker_id = $row['id'];
            $tracker    = $this->getCachedInstanceFromRow($row);
            if ($tracker->userIsAdmin($user)) {
                $trackers[$tracker_id] = $tracker;
            }
        }

        return $trackers;
    }

    /**
     * @param Tracker $tracker
     *
     * @return Children trackers of the given tracker.
     */
    public function getPossibleChildren($tracker)
    {
        $project_id = $tracker->getGroupId();
        $trackers   = $this->getTrackersByGroupId($project_id);

        unset($trackers[$tracker->getId()]);
        return $trackers;
    }

    protected $dao;

    /**
     * @return TrackerDao
     */
    protected function getDao()
    {
        if (!$this->dao) {
            $this->dao = new TrackerDao();
        }
        return $this->dao;
    }

    /**
     * @param array $row Raw data (typically from the db) of the tracker
     *
     * @return Tracker
     */
    private function getCachedInstanceFromRow($row)
    {
        $tracker_id = $row['id'];
        if (!isset($this->trackers[$tracker_id])) {
            $this->trackers[$tracker_id] = $this->getInstanceFromRow($row);
        }
        return $this->trackers[$tracker_id];
    }

    /**
     * /!\ Only for tests
     */
    public function setCachedInstances($trackers)
    {
        $this->trackers = $trackers;
    }

    /**
     * @param array the row identifing a tracker
     * @return Tracker
     */
    public function getInstanceFromRow($row)
    {
        return new Tracker(
            $row['id'],
            $row['group_id'],
            $row['name'],
            $row['description'],
            $row['item_name'],
            $row['allow_copy'],
            $row['submit_instructions'],
            $row['browse_instructions'],
            $row['status'],
            $row['deletion_date'],
            $row['instantiate_for_new_projects'],
            $row['log_priority_changes'],
            $row['notifications_level'],
            TrackerColor::fromName($row['color']),
            $row['enable_emailgateway']
        );
    }

    /**
     * @return Tracker_CannedResponseFactory
     */
    protected function getCannedResponseFactory()
    {
        return Tracker_CannedResponseFactory::instance();
    }

    /**
     * @return Tracker_FormElementFactory
     */
    protected function getFormElementFactory()
    {
        return Tracker_FormElementFactory::instance();
    }

    /**
     * @return Tracker_SemanticFactory
     */
    protected function getSemanticFactory()
    {
        return Tracker_SemanticFactory::instance();
    }

    /**
     * @return Tracker_RuleFactory
     */
    protected function getRuleFactory()
    {
        return Tracker_RuleFactory::instance();
    }

    /**
     * @return Tracker_ReportFactory
     */
    protected function getReportFactory()
    {
        return Tracker_ReportFactory::instance();
    }

    /**
     * @return WorkflowFactory
     */
    protected function getWorkflowFactory()
    {
        return WorkflowFactory::instance();
    }

    /**
     * @return ReferenceManager
     */
    protected function getReferenceManager()
    {
        return ReferenceManager::instance();
    }

    /**
     * @return ProjectManager
     */
    protected function getProjectManager()
    {
        return ProjectManager::instance();
    }

    /**
     * Mark the tracker as deleted
     */
    public function markAsDeleted($tracker_id)
    {
        return $this->getDao()->markAsDeleted($tracker_id);
    }

    /**
     * Check if the name of the tracker is already used in the project
     * @param string $name the name of the tracker we are looking for
     * @param int $group_id th ID of the group
     * @return bool
     */
    public function isNameExists($name, $group_id)
    {
        $tracker_dao = $this->getDao();
        $dar = $tracker_dao->searchByGroupId($group_id);
        while ($row = $dar->getRow()) {
            if ($name == $row['name']) {
                return true;
            }
        }
        return false;
    }

   /**
    * Check if the shortname of the tracker is already used in the project
    * @param string $shortname the shortname of the tracker we are looking for
    * @param int $group_id the ID of the group
    * @return bool
    */
    public function isShortNameExists($shortname, $group_id)
    {
        $tracker_dao = $this->getDao();
        return $tracker_dao->isShortNameExists($shortname, $group_id);
    }

    /**
     * @return bool
     */
    private function isShortNameValid($shortname)
    {
        return preg_match('/^[a-zA-Z0-9_]+$/i', $shortname) === 1;
    }

    /**
     * @return bool
     */
    private function isRequiredInformationsAvailable($name, $description, $itemname)
    {
        return trim($name) !== '' && trim($description) !== '' && trim($itemname) !== '';
    }

    /**
     * Valid the name, description and itemname on creation.
     * Add feedback if error.
     *
     * @param string $name        the name of the new tracker
     * @param string $description the description of the new tracker
     * @param string $itemname    the itemname of the new tracker
     * @param int    $group_id    the id of the group of the new tracker
     *
     * @return bool true if all valid
     */
    public function validMandatoryInfoOnCreate($name, $description, $itemname, $group_id)
    {
        if (! $this->isRequiredInformationsAvailable($name, $description, $itemname)) {
            $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_tracker_common_type', 'name_requ'));
            return false;
        }

        // Necessary test to avoid issues when exporting the tracker to a DB (e.g. '-' not supported as table name)
        if (! $this->isShortNameValid($itemname)) {
            $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_tracker_common_type', 'invalid_shortname', $itemname));
            return false;
        }

        if ($this->isNameExists($name, $group_id)) {
            $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_tracker_common_type', 'name_already_exists', $itemname));
            return false;
        }

        if ($this->isShortNameExists($itemname, $group_id)) {
            $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_tracker_common_type', 'shortname_already_exists', $itemname));
            return false;
        }

        $reference_manager = $this->getReferenceManager();
        if ($reference_manager->_isKeywordExists($itemname, $group_id)) {
            $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_tracker_common_type', 'shortname_already_exists', $itemname));
            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    public function collectTrackersNameInErrorOnMandatoryCreationInfo(array $trackers, $project_id)
    {
        $invalid_trackers_name = array();

        foreach ($trackers as $tracker) {
            if (! $this->areMandatoryCreationInformationsValid($tracker->getName(), $tracker->getDescription(), $tracker->getItemName(), $project_id)) {
                $invalid_trackers_name[] = $tracker->getName();
            }
        }

        return $invalid_trackers_name;
    }

    /**
     * @return bool
     */
    private function areMandatoryCreationInformationsValid(
        $tracker_name,
        $tracker_description,
        $tracker_shortname,
        $project_id
    ) {
        $reference_manager = $this->getReferenceManager();

        return $this->isRequiredInformationsAvailable($tracker_name, $tracker_description, $tracker_shortname)
            && $this->isShortNameValid($tracker_shortname) && ! $this->isNameExists($tracker_name, $project_id)
            && ! $this->isShortNameExists($tracker_shortname, $project_id)
            && ! $reference_manager->_isKeywordExists($tracker_shortname, $project_id);
    }

    /**
     * create - use this to create a new Tracker in the database.
     *
     * @param Project $project_id          the group id of the new tracker
     * @param int     $project_id_template the template group id (used for the copy)
     * @param int     $id_template         the template tracker id
     * @param string  $name                the name of the new tracker
     * @param string  $description         the description of the new tracker
     * @param string  $itemname            the itemname of the new tracker
     * @param Array   $ugroup_mapping the ugroup mapping
     *
     * @return mixed array(Tracker object, field_mapping array) or false on failure.
     */
    function create($project_id, $project_id_template, $id_template, $name, $description, $itemname, $ugroup_mapping = false)
    {

        if ($this->validMandatoryInfoOnCreate($name, $description, $itemname, $project_id)) {
            // Get the template tracker
            $template_tracker = $this->getTrackerById($id_template);
            if (!$template_tracker) {
                $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_tracker_common_type', 'invalid_tracker_tmpl'));
                return false;
            }

            $template_group = $template_tracker->getProject();
            if (!$template_group || !is_object($template_group) || $template_group->isError()) {
                $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_tracker_common_type', 'invalid_templ'));
                return false;
            }
            $project_id_template = $template_group->getId();

            //Ask to dao to duplicate the tracker
            if ($id = $this->getDao()->duplicate($id_template, $project_id, $name, $description, $itemname)) {
                // Duplicate Form Elements
                $field_mapping = Tracker_FormElementFactory::instance()->duplicate($id_template, $id, $ugroup_mapping);

                if ($ugroup_mapping) {
                    $duplicate_type = PermissionsDao::DUPLICATE_NEW_PROJECT;
                } elseif ($project_id == $project_id_template) {
                     $duplicate_type = PermissionsDao::DUPLICATE_SAME_PROJECT;
                } else {
                    $ugroup_manager = new UGroupManager();
                    $builder = new Tracker_UgroupMappingBuilder(new Tracker_UgroupPermissionsGoldenRetriever(new Tracker_PermissionsDao(), $ugroup_manager), $ugroup_manager);
                    $ugroup_mapping = $builder->getMapping($template_tracker, ProjectManager::instance()->getProject($project_id));
                    $duplicate_type = PermissionsDao::DUPLICATE_OTHER_PROJECT;
                }

                // Duplicate workflow
                foreach ($field_mapping as $mapping) {
                    if ($mapping['workflow']) {
                        WorkflowFactory::instance()->duplicate($id_template, $id, $mapping['from'], $mapping['to'], $mapping['values'], $field_mapping, $ugroup_mapping, $duplicate_type);
                    }
                }
                // Duplicate Reports
                $report_mapping = Tracker_ReportFactory::instance()->duplicate($id_template, $id, $field_mapping);

                // Duplicate Semantics
                Tracker_SemanticFactory::instance()->duplicate($id_template, $id, $field_mapping);

                // Duplicate Canned Responses
                Tracker_CannedResponseFactory::instance()->duplicate($id_template, $id);
                //Duplicate field dependencies
                $this->getRuleFactory()->duplicate($id_template, $id, $field_mapping);
                $tracker = $this->getTrackerById($id);

                // Process event that tracker is created
                $em = EventManager::instance();
                $pref_params = array('atid_source' => $id_template,
                        'atid_dest'   => $id);
                $em->processEvent('Tracker_created', $pref_params);
                //Duplicate Permissions
                $this->duplicatePermissions($id_template, $id, $ugroup_mapping, $field_mapping, $duplicate_type);

                $source_tracker = $this->getTrackerById($id_template);
                $this->duplicateWebhooks($source_tracker, $tracker);

                $this->postCreateActions($tracker);

                return array(
                    'tracker'        => $tracker,
                    'field_mapping'  => $field_mapping,
                    'report_mapping' => $report_mapping
                );
            }
        }
        return false;
    }

    private function duplicateWebhooks(Tracker $source_tracker, Tracker $tracker)
    {
        $this->getWebhookFactory()->duplicateWebhookFromSourceTracker($source_tracker, $tracker);
    }

    /**
     * @return WebhookFactory
     */
    private function getWebhookFactory()
    {
        return new WebhookFactory(new WebhookDao());
    }

   /**
    * Duplicat the permissions of a tracker
    *
    * @param int $id_template the id of the duplicated tracker
    * @param int $id          the id of the new tracker
    * @param array $ugroup_mapping
    * @param array $field_mapping
    * @param bool $duplicate_type
    *
    * @return bool
    */
    public function duplicatePermissions($id_template, $id, $ugroup_mapping, $field_mapping, $duplicate_type)
    {
        $pm = PermissionsManager::instance();
        $permission_type_tracker = array(Tracker::PERMISSION_ADMIN, Tracker::PERMISSION_SUBMITTER, Tracker::PERMISSION_SUBMITTER_ONLY, Tracker::PERMISSION_ASSIGNEE, Tracker::PERMISSION_FULL, Tracker::PERMISSION_NONE);
        //Duplicate tracker permissions
        $pm->duplicatePermissions($id_template, $id, $permission_type_tracker, $ugroup_mapping, $duplicate_type);

        $permission_type_field = array('PLUGIN_TRACKER_FIELD_SUBMIT','PLUGIN_TRACKER_FIELD_READ','PLUGIN_TRACKER_FIELD_UPDATE', 'PLUGIN_TRACKER_NONE');
        //Duplicate fields permissions
        foreach ($field_mapping as $f) {
            $from = $f['from'];
            $to = $f['to'];
            $pm->duplicatePermissions($from, $to, $permission_type_field, $ugroup_mapping, $duplicate_type);
        }
    }

    /**
     * Do all stuff which have to be done after a tracker creation, like reference creation for example
     *
     * @param Tracker $tracker The tracker
     *
     * @return void
     */
    protected function postCreateActions(Tracker $tracker)
    {
        $keyword   = strtolower($tracker->getItemName());
        $reference = new Tracker_Reference(
            $tracker,
            $keyword
        );

        // Force reference creation because default trackers use reserved keywords
        $this->getReferenceManager()->createReference($reference, true);
    }

    /**
     * Duplicate all trackers from a project to another one
     *
     * Duplicate among others:
     * - the trackers definition
     * - the hierarchy
     * - the shared fields
     * - etc.
     *
     * @param int $from_project_id
     * @param int $to_project_id
     * @param array $ugroup_mapping the ugroup mapping
     *
     */
    public function duplicate($from_project_id, $to_project_id, $ugroup_mapping)
    {
        $tracker_mapping        = array();
        $field_mapping          = array();
        $report_mapping         = array();
        $trackers_from_template = array();

        $tracker_ids_list         = array();
        $params = array('project_id' => $from_project_id, 'tracker_ids_list' => &$tracker_ids_list);
        EventManager::instance()->processEvent(TRACKER_EVENT_PROJECT_CREATION_TRACKERS_REQUIRED, $params);
        $tracker_ids_list = array_unique($tracker_ids_list);
        foreach ($this->getTrackersByGroupId($from_project_id) as $tracker) {
            if ($tracker->mustBeInstantiatedForNewProjects() || in_array($tracker->getId(), $tracker_ids_list)) {
                $trackers_from_template[] = $tracker;
                list($tracker_mapping, $field_mapping, $report_mapping) = $this->duplicateTracker(
                    $tracker_mapping,
                    $field_mapping,
                    $report_mapping,
                    $tracker,
                    $from_project_id,
                    $to_project_id,
                    $ugroup_mapping
                );
                /*
                 * @todo
                 * Unless there is some odd dependency on the last tracker meeting
                 * the requirement of the if() condition then there should be a break here.
                 */
            }
        }

        /*
         * @todo
         * $tracker_mapping has been defined as an array. Surely this should be
         * if(! empty($tracker_mapping))
         */
        if ($tracker_mapping) {
            $hierarchy_factory = $this->getHierarchyFactory();
            $hierarchy_factory->duplicate($tracker_mapping);

            $trigger_rules_manager = $this->getTriggerRulesManager();
            $trigger_rules_manager->duplicate($trackers_from_template, $field_mapping);
        }
        $shared_factory = $this->getFormElementFactory();
        $shared_factory->fixOriginalFieldIdsAfterDuplication($to_project_id, $from_project_id, $field_mapping);

        EventManager::instance()->processEvent(TRACKER_EVENT_TRACKERS_DUPLICATED, array(
            'tracker_mapping'   => $tracker_mapping,
            'field_mapping'     => $field_mapping,
            'report_mapping'    => $report_mapping,
            'group_id'          => $to_project_id,
            'ugroups_mapping'   => $ugroup_mapping,
            'source_project_id' => $from_project_id
        ));
    }

    /**
     * @return Tracker_Workflow_Trigger_RulesManager
     */
    public function getTriggerRulesManager()
    {
        $trigger_rule_dao        = new Tracker_Workflow_Trigger_RulesDao();
        $workflow_backend_logger = new WorkflowBackendLogger(new BackendLogger(), ForgeConfig::get('sys_logger_level'));
        $rules_processor         = new Tracker_Workflow_Trigger_RulesProcessor(
            new Tracker_Workflow_WorkflowUser(),
            $workflow_backend_logger
        );

        return new Tracker_Workflow_Trigger_RulesManager(
            $trigger_rule_dao,
            $this->getFormElementFactory(),
            $rules_processor,
            $workflow_backend_logger,
            new Tracker_Workflow_Trigger_RulesBuilderFactory($this->getFormElementFactory()),
            new WorkflowRulesManagerLoopSafeGuard($workflow_backend_logger)
        );
    }

    private function duplicateTracker(
        array $tracker_mapping,
        array $field_mapping,
        array $report_mapping,
        Tracker $tracker,
        $from_project_id,
        $to_project_id,
        $ugroup_mapping
    ) {
        $tracker_and_field_and_report_mapping = $this->create(
            $to_project_id,
            $from_project_id,
            $tracker->getId(),
            $tracker->getName(),
            $tracker->getDescription(),
            $tracker->getItemName(),
            $ugroup_mapping
        );

        if ($tracker_and_field_and_report_mapping) {
            $tracker_mapping[$tracker->getId()] = $tracker_and_field_and_report_mapping['tracker']->getId();
            $field_mapping  = array_merge($field_mapping, $tracker_and_field_and_report_mapping['field_mapping']);
            $report_mapping = $report_mapping + $tracker_and_field_and_report_mapping['report_mapping'];
        } else {
            $GLOBALS['Response']->addFeedback('warning', $GLOBALS['Language']->getText('plugin_tracker_admin', 'tracker_not_duplicated', array($tracker->getName())));
        }

        return array($tracker_mapping, $field_mapping, $report_mapping);
    }

    /**
     * /!\ Only for tests
     */
    public function setHierarchyFactory(Tracker_HierarchyFactory $hierarchy_factory)
    {
        $this->hierarchy_factory = $hierarchy_factory;
    }

    /**
     * @return Tracker_HierarchyFactory
     */
    public function getHierarchyFactory()
    {
        if (!$this->hierarchy_factory) {
            $this->hierarchy_factory = Tracker_HierarchyFactory::instance();
        }
        return $this->hierarchy_factory;
    }

    /**
     * @return Hierarchy
     */
    public function getHierarchy(array $tracker_ids)
    {
        return $this->getHierarchyFactory()->getHierarchy($tracker_ids);
    }

    /**
     * Saves the default permission of a tracker in the db
     *
     * @param int $tracker_id the id of the tracker
     * @return bool
     */
    public function saveTrackerDefaultPermission($tracker_id)
    {
        $pm = PermissionsManager::instance();
        if (!$pm->addPermission(Tracker::PERMISSION_FULL, $tracker_id, ProjectUGroup::ANONYMOUS)) {
            return false;
        }
        return true;
    }

    /**
     * Saves a Tracker object into the DataBase
     *
     * @param Tracker $tracker object to save
     * @return int id of the newly created tracker
     */
    public function saveObject($tracker)
    {
        // create tracker
        $this->getDao()->startTransaction();
        $tracker_id = $this->getDao()->create(
            $tracker->group_id,
            $tracker->name,
            $tracker->description,
            $tracker->item_name,
            $tracker->allow_copy,
            $tracker->submit_instructions,
            $tracker->browse_instructions,
            '',
            '',
            $tracker->instantiate_for_new_projects,
            $tracker->log_priority_changes,
            $tracker->getNotificationsLevel(),
            $tracker->getColor()->getName(),
            $tracker->isEmailgatewayEnabled()
        );
        if ($tracker_id) {
            $trackerDB = $this->getTrackerById($tracker_id);
            //create cannedResponses
            $response_factory = $tracker->getCannedResponseFactory();
            foreach ($tracker->cannedResponses as $response) {
                $response_factory->saveObject($tracker_id, $response);
            }
            //create formElements
            foreach ($tracker->formElements as $formElement) {
                // these fields have no parent
                Tracker_FormElementFactory::instance()->saveObject($trackerDB, $formElement, 0, true, true);
            }
            //create report
            foreach ($tracker->reports as $report) {
                Tracker_ReportFactory::instance()->saveObject($tracker_id, $report);
            }
            //create semantics
            if (isset($tracker->semantics)) {
                foreach ($tracker->semantics as $semantic) {
                    Tracker_SemanticFactory::instance()->saveObject($semantic, $trackerDB);
                }
            }
            //create rules
            if (isset($tracker->rules)) {
                $this->getRuleFactory()->saveObject($tracker->rules, $trackerDB);
            }
            //create workflow
            if (isset($tracker->workflow)) {
                WorkflowFactory::instance()->saveObject($tracker->workflow, $trackerDB);
            }

            if (count($tracker->webhooks) > 0) {
                $this->getWebhookFactory()->saveWebhooks($tracker->webhooks, $tracker_id);
            }

            //tracker permissions
            if ($tracker->permissionsAreCached()) {
                $pm = PermissionsManager::instance();
                foreach ($tracker->getPermissionsByUgroupId() as $ugroup => $permissions) {
                    foreach ($permissions as $permission) {
                        $pm->addPermission($permission, $tracker_id, $ugroup);
                    }
                }
            } else {
                $this->saveTrackerDefaultPermission($tracker_id);
            }

            $this->postCreateActions($trackerDB);
        }
        $this->getDao()->commit();
        return $tracker_id;
    }

    /**
     * Create a tracker v5 from a tracker v3
     *
     * @param PFUser         $user           the user who requested the creation
     * @param int            $atid           the id of the tracker v3
     * @param Project        $project        the Id of the project to create the tracker
     * @param string         $name           the name of the tracker (label)
     * @param string         $description    the description of the tracker
     * @param string         $itemname       the short name of the tracker
     *
     * @throws Tracker_Exception_Migration_GetTv3Exception
     *
     * @return Tracker
     */
    public function createFromTV3(PFUser $user, $atid, Project $project, $name, $description, $itemname)
    {
        $tv3 = new ArtifactType($project, $atid);
        if ($tv3->isError()) {
            throw new Tracker_Exception_Migration_GetTv3Exception($tv3->getErrorMessage());
        }
        // Check if this tracker is valid (not deleted)
        if (! $tv3->isValid()) {
            throw new Tracker_Exception_Migration_GetTv3Exception($GLOBALS['Language']->getText('tracker_add', 'invalid'));
        }
        //Check if the user can view the artifact
        if (! $tv3->userCanView($user->getId())) {
            throw new Tracker_Exception_Migration_GetTv3Exception($GLOBALS['Language']->getText('include_exit', 'no_perm'));
        }

        return $this->createTracker($name, $description, $itemname, $project, $tv3);
    }

    public function createFromTV3LegacyService(PFUser $user, ArtifactType $tracker_v3, Project $project)
    {
        $name        = $tracker_v3->getName();
        $description = $tracker_v3->getDescription();
        $itemname    = $tracker_v3->getItemName();

        if ($this->isNameExists($name, $project->getID())) {
            $name = $name . self::LEGACY_SUFFIX;
        }

        if ($this->isShortNameExists($itemname, $project->getID())) {
            $itemname = $itemname . self::LEGACY_SUFFIX;
        }

        return $this->createTracker($name, $description, $itemname, $project, $tracker_v3);
    }

    private function createTracker($name, $description, $itemname, Project $project, ArtifactType $tv3)
    {
        $tracker = null;
        if ($this->validMandatoryInfoOnCreate($name, $description, $itemname, $project->getId())) {
            $migration_v3 = new Tracker_Migration_V3($this);
            $tracker      = $migration_v3->createTV5FromTV3($project, $name, $description, $itemname, $tv3);

            $this->postCreateActions($tracker);
        }

        return $tracker;
    }
}
