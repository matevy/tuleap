<?php
/**
 * Copyright (c) Enalean, 2012 - 2019. All Rights Reserved.
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

use Tuleap\Tracker\Workflow\PostAction\Visitor;

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace,Squiz.Classes.ValidClassName.NotCamelCaps
class Transition_PostAction_CIBuild extends Transition_PostAction
{

    public const SHORT_NAME                          = 'ci_build';
    public const XML_TAG_NAME                        = 'postaction_ci_build';
    public const BUILD_PARAMETER_USER                = 'userId';
    public const BUILD_PARAMETER_PROJECT_ID          = 'projectId';
    public const BUILD_PARAMETER_ARTIFACT_ID         = 'artifactId';
    public const BUILD_PARAMETER_TRACKER_ID          = 'trackerId';
    public const BUILD_PARAMETER_TRIGGER_FIELD_VALUE = 'triggerFieldValue';
    /**
     * @var string Pattern to validate a job url
     */
    public const JOB_URL_PATTERN = '^https?://.+';

    /**
     *
     * @var String job_name : name of the job to build
     */
    private $job_url;
    /**
     * @var Jenkins_Client
     */
    private $ci_client;

    /**
     * Constructor
     *
     * @param Transition                   $transition The transition the post action belongs to
     * @param int                          $id         Id of the post action
     * @param string                       $host       host of the jenkins server
     * @param string                       $job_url   name of the job
     */
    public function __construct(Transition $transition, $id, $job_url, Jenkins_Client $client)
    {
        parent::__construct($transition, $id);
        $this->job_url   = $job_url;
        $this->ci_client = $client;
    }

    /** @return string */
    public function getJobUrl()
    {
        return $this->job_url;
    }

    /**
     * Get the shortname of the post action
     *
     * @return string
     */
    public function getShortName()
    {
        return self::SHORT_NAME;
    }

    /** @return string */
    public static function getLabel()
    {
        return $GLOBALS['Language']->getText('workflow_postaction', 'ci_build');
    }

    /** @return string html */
    public function fetch()
    {
        $purifier = Codendi_HTMLPurifier::instance();
        $html  = '';
        $title = $GLOBALS['Language']->getText('workflow_admin', 'ci_url');
        $text_field = '<input type="text"
            title="'. $purifier->purify($title) .'"
            required
            class="required"
            pattern="' . $purifier->purify(self::JOB_URL_PATTERN) . '"
            name="workflow_postaction_ci_build['. $purifier->purify($this->id) .']"
            value="'. $purifier->purify($this->getJobUrl()) .'"
            size="50"
            maxsize="255" />';
        $html    .= $GLOBALS['Language']->getText('workflow_admin', 'ci_build', array($text_field));

        $trigger_field_value_label = $GLOBALS['Language']->getText(
            'workflow_admin',
            'ci_build_help_trigger_field_value',
            array(
                $purifier->purify($this->getTransition()->getFieldValueTo()->getLabel())
            )
        );

        $html    .= '<p class="help">'.$GLOBALS['Language']->getText('workflow_admin', 'ci_build_help', array(ForgeConfig::get('sys_name'))).'
            <ul class="help">
            <li>'.$GLOBALS['Language']->getText('workflow_admin', 'ci_build_help_userid', array(ForgeConfig::get('sys_name'))).'</li>
            <li>'.$GLOBALS['Language']->getText('workflow_admin', 'ci_build_help_projectid', array($this->getTransition()->getGroupId())).'</li>
            <li>'.$GLOBALS['Language']->getText('workflow_admin', 'ci_build_help_trackerid', array($this->getTransition()->getWorkflow()->getTrackerId())).'</li>
            <li>'.$GLOBALS['Language']->getText('workflow_admin', 'ci_build_help_artifactid').'</li>
            <li>'. $trigger_field_value_label .'</li>
            </ul></p>';
        return $html;
    }

    /** @return bool */
    public function isDefined()
    {
        return !empty($this->job_url);
    }

    public function process(Codendi_Request $request)
    {
        if ($request->getInArray('remove_postaction', $this->id)) {
            $this->getDao()->deletePostAction($this->id);
        } else {
            $value = $request->getInArray('workflow_postaction_ci_build', $this->id);
            $this->updateJobUrl($value);
        }
    }

    /**
     * Export postactions to XML
     *
     * @param SimpleXMLElement &$root     the node to which the postaction is attached (passed by reference)
     * @param array            $xmlMapping correspondance between real ids and xml IDs
     *
     * @return void
     */
    public function exportToXml(SimpleXMLElement $root, $xmlMapping)
    {
        if ($this->isDefined()) {
            $child = $root->addChild(Transition_PostAction_CIBuild::XML_TAG_NAME);
            $child->addAttribute('job_url', $this->getJobUrl());
        }
    }

    /**
     * @see Transition_PostAction::after()
     * @param Tracker_Artifact_Changeset $changeset
     */
    public function after(Tracker_Artifact_Changeset $changeset)
    {
        if (! $this->isDefined()) {
            return;
        }

        $build_parameters = array(
            self::BUILD_PARAMETER_USER                => $changeset->getSubmittedBy(),
            self::BUILD_PARAMETER_PROJECT_ID          => $changeset->getArtifact()->getTracker()->getProject()->getID(),
            self::BUILD_PARAMETER_ARTIFACT_ID         => $changeset->getArtifact()->getId(),
            self::BUILD_PARAMETER_TRACKER_ID          => $changeset->getArtifact()->getTracker()->getId(),
            self::BUILD_PARAMETER_TRIGGER_FIELD_VALUE => $this->getTransition()->getFieldValueTo()->getLabel(),
        );

        try {
            $this->ci_client->launchJobBuild($this->job_url, $build_parameters);
            $feedback = $GLOBALS['Language']->getText('workflow_postaction', 'ci_build_succeeded', array($this->job_url));
            $GLOBALS['Response']->addFeedback('info', $feedback);
        } catch (Jenkins_ClientUnableToLaunchBuildException $exception) {
            $GLOBALS['Response']->addFeedback('error', $exception->getMessage());
        }
    }

    /** @return Transition_PostAction_CIBuildDao */
    protected function getDao()
    {
        return new Transition_PostAction_CIBuildDao();
    }

    private function urlIsValid($url)
    {
        return preg_match('#' . self::JOB_URL_PATTERN . '#', $url) > 0;
    }

    private function updateJobUrl($new_job_url)
    {
        if ($new_job_url != $this->job_url) {
            if ($this->urlIsValid($new_job_url)) {
                $this->getDao()->updatePostAction($this->id, $new_job_url);
            } else {
                $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('workflow_postaction', 'invalid_job_url', array($new_job_url)));
            }
        }
    }

    public function bypassPermissions(Tracker_FormElement_Field $field)
    {
        return $this->bypass_permissions;
    }

    public function accept(Visitor $visitor)
    {
        $visitor->visitCIBuild($this);
    }
}
