<?php
/**
 * Copyright (c) Enalean, 2012. All Rights Reserved.
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

// This is an on going work to help developers to build more expressive tests
// please add the functions/methods below when needed.
// For further information about the Test Data Builder pattern
// @see http://nat.truemesh.com/archives/000727.html
require_once __DIR__.'/../bootstrap.php';

function anArtifact()
{
    return new Test_Artifact_Builder();
}

class Test_Artifact_Builder
{
    private $id;
    private $tracker;
    private $tracker_id;
    private $formElementFactory;
    private $changesets;
    private $hierarchy_factory;
    private $ancestors;
    private $title;
    private $parent_without_permission_checking;
    private $submitted_by;
    private $submitted_on;
    private $submitted_by_user;
    private $status;

    public function withId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function withStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    public function withTracker(Tracker $tracker)
    {
        $this->tracker    = $tracker;
        return $this->withTrackerId($tracker->getId());
    }

    public function withTrackerId($tracker_id)
    {
        $this->tracker_id = $tracker_id;
        return $this;
    }

    public function withSubmittedBy($submitted_by)
    {
        $this->submitted_by = $submitted_by;
        return $this;
    }

    public function withSubmittedOn($submitted_on)
    {
        $this->submitted_on = $submitted_on;
        return $this;
    }

    public function withFormElementFactory(Tracker_FormElementFactory $factory)
    {
        $this->formElementFactory = $factory;
        return $this;
    }

    public function withChangesets(array $changesets)
    {
        $this->changesets = $changesets;
        return $this;
    }

    public function withHierarchyFactory($hierarchy_factory)
    {
        $this->hierarchy_factory = $hierarchy_factory;
        return $this;
    }

    public function withParent(Tracker_Artifact $parent)
    {
        if (!isset($this->ancestors)) {
            $this->ancestors = array();
        }
        $this->ancestors[] = $parent;
        return $this;
    }

    public function withParentWithoutPermissionChecking(Tracker_Artifact $parent)
    {
        $this->parent_without_permission_checking = $parent;
        return $this;
    }

    public function withoutParentWithoutPermissionChecking()
    {
        $this->parent_without_permission_checking = Tracker_Artifact::NO_PARENT;
        return $this;
    }

    public function withoutParent()
    {
        $this->ancestors = array();
        return $this;
    }

    public function withTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function withSubmitter(PFUser $user)
    {
        $this->submitted_by_user = $user;
        return $this;
    }

    public function build()
    {
        $artifact = new Tracker_Artifact($this->id, $this->tracker_id, $this->submitted_by, $this->submitted_on, null);
        if ($this->tracker) {
            $artifact->setTracker($this->tracker);
        }
        if ($this->formElementFactory) {
            $artifact->setFormElementFactory($this->formElementFactory);
        }
        if ($this->changesets) {
            $artifact->setChangesets($this->changesets);
        }
        if ($this->hierarchy_factory) {
            $artifact->setHierarchyFactory($this->hierarchy_factory);
        }
        if (isset($this->ancestors)) {
            $artifact->setAllAncestors($this->ancestors);
        }
        if ($this->title) {
            $artifact->setTitle($this->title);
        }
        if ($this->parent_without_permission_checking) {
            $artifact->setParentWithoutPermissionChecking($this->parent_without_permission_checking);
        }
        if ($this->submitted_by_user) {
            $artifact->setSubmittedByUser($this->submitted_by_user);
        }
        if ($this->status) {
            $artifact->setStatus($this->status);
        }
        return $artifact;
    }
}
