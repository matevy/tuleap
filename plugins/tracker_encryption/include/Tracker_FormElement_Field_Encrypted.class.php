<?php
/**
 * Copyright (c) STMicroelectronics 2016. All rights reserved
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

use Tuleap\Tracker\FormElement\Field\File\CreatedFileURLMapping;
use Tuleap\Tracker\FormElement\TrackerFormElementExternalField;
use Tuleap\TrackerEncryption\ChangesetValue;
use Tuleap\TrackerEncryption\Dao\ValueDao;

class Tracker_FormElement_Field_Encrypted extends Tracker_FormElement_Field implements TrackerFormElementExternalField // @codingStandardsIgnoreLine
{

    /**
     * @return string html
     */
    protected function fetchSubmitValue(array $submitted_values)
    {
        $value = $this->getValueFromSubmitOrDefault($submitted_values);

        $html  = '<div class="input-append encrypted-field">';
        $html .= $this->fetchInput($value, 'password');
        $html .= $this->fetchButton();
        $html  .= '</div>';

        return $html;
    }

    /**
     * @return string html
     */
    private function fetchButton()
    {
        $html = '<button class="btn" type="button" id="show_password_'. $this->id .'">
                     <span id="show_password_icon_'. $this->id .'" class="fa fa-eye-slash"></span>
                 </button>';

        return $html;
    }

    /**
     * @return string html
     */
    protected function fetchAdminFormElement()
    {
        return $this->fetchSubmitValue(array());
    }

    /**
     * @return the label of the field (mainly used in admin part)
     */
    public static function getFactoryLabel()
    {
        return $GLOBALS['Language']->getText('plugin_tracker_encryption', 'field_label');
    }

    /**
     * @return the description of the field (mainly used in admin part)
     */
    public static function getFactoryDescription()
    {
          return $GLOBALS['Language']->getText('plugin_tracker_encryption', 'field_label');
    }

    /**
     * @return the path to the icon
     */
    public static function getFactoryIconUseIt()
    {
        return $GLOBALS['HTML']->getImagePath('ic/lock.png');
    }

    /**
     * @return the path to the icon
     */
    public static function getFactoryIconCreate()
    {
        return $GLOBALS['HTML']->getImagePath('ic/lock.png');
    }

    protected function validate(Tracker_Artifact $artifact, $value)
    {
        if ($this->getLastChangesetValue($artifact) !== null
            && $this->getLastChangesetValue($artifact)->getValue() === $value
        ) {
            return true;
        }

        $maximum_characters_allowed = $this->getMaxSizeAllowed();
        if ($maximum_characters_allowed !== 0 && mb_strlen($value) > $maximum_characters_allowed) {
            $GLOBALS['Response']->addFeedback(
                Feedback::ERROR,
                $GLOBALS['Language']->getText(
                    'plugin_tracker_common_artifact',
                    'error_string_max_characters',
                    array($this->getLabel(), $maximum_characters_allowed)
                )
            );
            return false;
        }
        return true;
    }

    private function getMaxSizeAllowed()
    {
        $dao_pub_key        = new TrackerPublicKeyDao();
        $value_dao          = new ValueDao();
        $tracker_key        = new Tracker_Key($dao_pub_key, $value_dao, $this->getTrackerId());
        $key                = $tracker_key->getKey();

        return $tracker_key->getFieldSize($key);
    }

    protected function saveValue(
        $artifact,
        $changeset_value_id,
        $value,
        ?Tracker_Artifact_ChangesetValue $previous_changesetvalue,
        CreatedFileURLMapping $id_mapping
    ) {
        if ($value != "") {
            $dao_pub_key        = new TrackerPublicKeyDao();
            $value_dao          = new ValueDao();
            $tracker_key        = new Tracker_Key($dao_pub_key, $value_dao, $artifact->tracker_id);
            try {
                $encryption_manager = new Encryption_Manager($tracker_key);
                return $this->getValueDao()->create($changeset_value_id, $encryption_manager->encrypt($value));
            } catch (Tracker_EncryptionException $exception) {
                return $exception->getMessage();
            }
        } else {
            return $this->getValueDao()->create($changeset_value_id, $value);
        }
    }

    public function accept(Tracker_FormElement_FieldVisitor $visitor)
    {
        return $visitor->visitExternalField($this);
    }

    public function getRESTAvailableValues()
    {
    }

    /**
     * @param Tracker_ReportCriteria $criteria
     *
     * @return string
     * @see fetchCriteria
     */
    public function fetchCriteriaValue($criteria)
    {
        return '';
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    public function fetchRawValue($value)
    {
        return '';
    }

    /**
     * @param Tracker_ReportCriteria $criteria
     *
     * @return string
     */
    public function getCriteriaFrom($criteria)
    {
        return '';
    }

    public function getQueryFrom()
    {
        $R1 = 'R1_' . $this->id;
        $R2 = 'R2_' . $this->id;

        return "LEFT JOIN ( tracker_changeset_value AS $R1
                    INNER JOIN tracker_changeset_value_encrypted AS $R2 ON ($R2.changeset_value_id = $R1.id)
                ) ON ($R1.changeset_id = c.id AND $R1.field_id = " . $this->id . " )";
    }

    public function getQuerySelect()
    {
        $R2 = 'R2_' . $this->id;

        return "$R2.value AS `" . $this->name . "`";
    }

    /**
     * @param Tracker_ReportCriteria $criteria
     *
     * @return string
     * @see getCriteriaFrom
     */
    public function getCriteriaWhere($criteria)
    {
        return '';
    }

    protected function getCriteriaDao()
    {
    }

    /**
     * @return string
     */
    protected function fetchArtifactValue(
        Tracker_Artifact $artifact,
        ?Tracker_Artifact_ChangesetValue $value = null,
        $submitted_values = array()
    ) {
        $html = '';
        if (is_array($submitted_values)
            && isset($submitted_values[$this->getId()])
            && $submitted_values[$this->getId()] !== false
        ) {
            $value = $submitted_values[$this->getId()];
        } else {
            if ($value != null) {
                $value = $value->getValue();
            }
        }
        $html .= $this->fetchEditInput($value);

        return $html;
    }

    /**
     * @return string
     */
    public function fetchArtifactValueReadOnly(
        Tracker_Artifact $artifact,
        ?Tracker_Artifact_ChangesetValue $value = null
    ) {
        if (isset($value) === false || $value->getValue() === '') {
            return $this->getNoValueLabel();
        }

        $purifier = Codendi_HTMLPurifier::instance();

        return $purifier->purify($value->getValue());
    }

    protected function getHiddenArtifactValueForEdition(
        Tracker_Artifact $artifact,
        ?Tracker_Artifact_ChangesetValue $value,
        array $submitted_values
    ) {
        return '<div class="tracker_hidden_edition_field" data-field-id="' . $this->getId() . '">' .
            $this->fetchArtifactValue($artifact, $value, $submitted_values) . '</div>';
    }

    private function fetchInput($value, $field_type)
    {
        $html_purifier = Codendi_HTMLPurifier::instance();

        return '<input
            type="' . $field_type . '"
            autocomplete="off"
            id="password_' . $this->id . '"
            class="form-control"
            name="artifact[' . $this->id . ']"
            maxlength="' . $this->getMaxSizeAllowed() . '"
            value= "' . $html_purifier->purify($value, CODENDI_PURIFIER_CONVERT_HTML) . '" />';
    }

    private function fetchEditInput($value)
    {
        return $this->fetchInput($value, 'text');
    }

    protected function fetchArtifactValueWithEditionFormIfEditable(
        Tracker_Artifact $artifact,
        ?Tracker_Artifact_ChangesetValue $value = null,
        $submitted_values = array()
    ) {
        return "<div class='tracker-form-element-encrypted'>" . $this->fetchArtifactValueReadOnly($artifact, $value) . "</div>" .
            $this->getHiddenArtifactValueForEdition($artifact, $value, $submitted_values);
    }

    /**
     * @return string html
     */
    protected function fetchSubmitValueMasschange()
    {
        return '';
    }

    /**
     * @return string
     */
    protected function fetchTooltipValue(Tracker_Artifact $artifact, ?Tracker_Artifact_ChangesetValue $value = null)
    {
        return '';
    }

    protected function getValueDao()
    {
        return new ValueDao();
    }

    /**
     * @param Tracker_Artifact $artifact
     * @param array $from
     * @param array $to
     *
     * @return string
     */
    public function fetchFollowUp($artifact, $from, $to)
    {
        return '';
    }

    /**
     * @param Tracker_Artifact_Changeset $changeset
     *
     * @return string
     */
    public function fetchRawValueFromChangeset($changeset)
    {
        return '';
    }

    /**
     * @param Tracker_Artifact_Changeset $changeset
     * @param int $value_id
     * @param bool $has_changed
     *
     * @return Tracker_Artifact_ChangesetValue | null
     */
    public function getChangesetValue($changeset, $value_id, $has_changed)
    {
        $changeset_value = null;
        if ($row = $this->getValueDao()->searchById($value_id)->getRow()) {
            $changeset_value = new ChangesetValue($value_id, $changeset, $this, $has_changed, $row['value']);
        }

        return $changeset_value;
    }

    /**
     * @param int $artifact_id
     * @param int $changeset_id
     * @param mixed $value
     * @param int $report_id
     *
     * @return string
     */
    public function fetchChangesetValue($artifact_id, $changeset_id, $value, $report_id = null, $from_aid = null)
    {
        return $value;
    }

    public function fetchArtifactForOverlay(Tracker_Artifact $artifact, array $submitted_values)
    {
    }

    public function canBeUsedAsReportCriterion()
    {
        return false;
    }

    public function hasChanges(Tracker_Artifact $artifact, Tracker_Artifact_ChangesetValue $old_value, $new_value)
    {
        return $old_value->getValue() !== $new_value;
    }

    public function getFormAdminVisitor(Tracker_FormElement_Field $element, array $used_element)
    {
        return new Tracker_FormElement_View_Admin_Field($element, $used_element);
    }
}
