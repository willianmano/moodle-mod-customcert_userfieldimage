<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the customcert element userfieldimage's core interaction API.
 *
 * @package    customcertelement_userfieldimage
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_userfieldimage;

use core_user\fields;

defined('MOODLE_INTERNAL') || die();

/**
 * The customcert element userfieldimage's core interaction API.
 *
 * @package    customcertelement_userfieldimage
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element {

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        // Get the user custom fields.
        $arrcustomfields = \availability_profile\condition::get_custom_profile_fields();
        $fields = array();
        foreach ($arrcustomfields as $key => $customfield) {
            if ($customfield->datatype != 'file') {
                continue;
            }

            $fields[$customfield->id] = $customfield->name;
        }
        \core_collator::asort($fields);

        // Create the select box where the user field is selected.
        $mform->addElement('select', 'userfieldimage', get_string('userfieldimage', 'customcertelement_userfieldimage'), $fields);
        $mform->setType('userfieldimage', PARAM_ALPHANUM);
        $mform->addHelpButton('userfieldimage', 'userfieldimage', 'customcertelement_userfieldimage');

        $mform->addElement('text', 'width', get_string('width', 'customcertelement_userfieldimage'), array('size' => 10));
        $mform->setType('width', PARAM_INT);
        $mform->setDefault('width', 0);
        $mform->addHelpButton('width', 'width', 'customcertelement_userfieldimage');

        $mform->addElement('text', 'height', get_string('height', 'customcertelement_userfieldimage'), array('size' => 10));
        $mform->setType('height', PARAM_INT);
        $mform->setDefault('height', 0);
        $mform->addHelpButton('height', 'height', 'customcertelement_userfieldimage');
    }

    /**
     * Performs validation on the element values.
     *
     * @param array $data the submitted data
     * @param array $files the submitted files
     * @return array the validation errors
     */
    public function validate_form_elements($data, $files) {
        // Array to return the errors.
        $errors = array();

        // Check if width is not set, or not numeric or less than 0.
        if ((!isset($data['width'])) || (!is_numeric($data['width'])) || ($data['width'] < 0)) {
            $errors['width'] = get_string('invalidwidth', 'customcertelement_userpictureimage');
        }

        // Check if height is not set, or not numeric or less than 0.
        if ((!isset($data['height'])) || (!is_numeric($data['height'])) || ($data['height'] < 0)) {
            $errors['height'] = get_string('invalidheight', 'customcertelement_userpictureimage');
        }

        // Check if height is not set, or not numeric or less than 0.
        if (!isset($data['userfieldimage'])) {
            $errors['userfieldimage'] = get_string('invaliduserfieldimage', 'customcertelement_userpictureimage');
        }

        return $errors;
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param \stdClass $data the form data
     * @return string the text
     */
    public function save_unique_data($data) {
        // Array of data we will be storing in the database.
        $arrtostore = array(
            'width' => (int) $data->width,
            'height' => (int) $data->height,
            'userfieldimage' => $data->userfieldimage
        );

        return json_encode($arrtostore);
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        global $CFG;

        $context = \context_user::instance($user->id);

        $imageinfo = json_decode($this->get_data());

        $file = $this->get_image_file($context);

        if ($file) {
            $location = make_request_directory() . '/target';
            $file->copy_content_to($location);
            $pdf->Image($location, $this->get_posx(), $this->get_posy(), $imageinfo->width, $imageinfo->height);
        } else if ($preview) { // Can't find an image, but we are in preview mode then display default pic.
            $location = $CFG->dirroot . '/mod/customcert/element/userfieldimage/pix/signature.png';

            $pdf->Image($location, $this->get_posx(), $this->get_posy(), $imageinfo->width, $imageinfo->height);
        }
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     */
    public function render_html() {
        global $USER;

        $url = $this->get_image_file_url($USER->id);

        $imageinfo = json_decode($this->get_data());

        $style = '';
        if (empty($imageinfo->width) && empty($imageinfo->height)) {
            // Put this in so code checker doesn't complain.
            $style .= 'width: 200mm; height: 50mm;';
        }

        if (!empty($imageinfo->width)) {
            $style .= 'width: ' . $imageinfo->width . 'mm; ';
        }

        if (!empty($imageinfo->height)) {
            $style .= 'height: ' . $imageinfo->height . 'mm';
        }

        return \html_writer::img($url, '', ['style' => $style]);
    }

    private function get_image_file($context) {
        $imageinfo = json_decode($this->get_data());

        $fs = get_file_storage();

        $files = $fs->get_area_files($context->id, 'profilefield_file', "files_{$imageinfo->userfieldimage}",
            0,
            'timemodified',
            false);

        if ($files) {
            foreach ($files as $file) {
                if ($file->is_valid_image()) {
                    return $file;
                }
            }
        }

        return false;
    }

    private function get_image_file_url($userid) {
        $context = \context_user::instance($userid);

        $file = $this->get_image_file($context);

        if ($file) {
            $imageinfo = json_decode($this->get_data());

            $path = '/' . $context->id . '/profilefield_file/files_' . $imageinfo->userfieldimage . '/' .
                $file->get_itemid() .
                $file->get_filepath() .
                $file->get_filename();

            $usersignature = new \moodle_url("/pluginfile.php{$path}");

            return $usersignature->out();
        }

        $usersignature = new \moodle_url('/mod/customcert/element/userfieldimage/pix/signature.png');

        return $usersignature->out();
    }

    /**
     * Helper function that returns the text.
     *
     * @param \stdClass $user the user we are rendering this for
     * @param bool $preview Is this a preview?
     * @return string
     */
    protected function get_user_field_value(\stdClass $user, bool $preview) : string {
        global $CFG, $DB;

        // The user field to display.
        $field = $this->get_data();
        // The value to display - we always want to show a value here so it can be repositioned.
        if ($preview) {
            $value = $field;
        } else {
            $value = '';
        }
        if (is_number($field)) { // Must be a custom user profile field.
            if ($field = $DB->get_record('user_info_field', array('id' => $field))) {
                // Found the field name, let's update the value to display.
                $value = $field->name;
                $file = $CFG->dirroot . '/user/profile/field/' . $field->datatype . '/field.class.php';
                if (file_exists($file)) {
                    require_once($CFG->dirroot . '/user/profile/lib.php');
                    require_once($file);
                    $class = "profile_field_{$field->datatype}";
                    $field = new $class($field->id, $user->id);
                    $value = $field->display_data();
                }
            }
        } else if (!empty($user->$field)) { // Field in the user table.
            $value = $user->$field;
        }

        $context = \mod_customcert\element_helper::get_context($this->get_id());
        return format_string($value, true, ['context' => $context]);
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        // Set the image, width and height for this element.
        if (!empty($this->get_data())) {
            $imageinfo = json_decode($this->get_data());

            $element = $mform->getElement('width');
            $element->setValue($imageinfo->width);

            $element = $mform->getElement('height');
            $element->setValue($imageinfo->height);

            $element = $mform->getElement('userfieldimage');
            $element->setValue($imageinfo->userfieldimage);
        }

        parent::definition_after_data($mform);
    }
}
