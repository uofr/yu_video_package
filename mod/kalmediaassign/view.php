<?php
// This file is part of Moodle - http://moodle.org/
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
 * Kaltura media assignment
 *
 * @package    mod
 * @subpackage kalmediaassign
 * @copyright  (C) 2016-2017 Yamaguchi University <info-cc@ml.cc.yamaguchi-u.ac.jp>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');
require_once(dirname(__FILE__) . '/locallib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.

// Retrieve module instance.
if (empty($id)) {
    print_error('invalidid', 'kalmediaassign');
}

if (!empty($id)) {

    if (! $cm = get_coursemodule_from_id('kalmediaassign', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }

    if (! $kalmediaassign = $DB->get_record('kalmediaassign', array("id" => $cm->instance))) {
        print_error('invalidid', 'kalmediaassign');
    }
}

require_course_login($course->id, true, $cm);

global $SESSION, $CFG, $USER, $COURSE;

// Connect to Kaltura.
$kaltura     = new kaltura_connection();
$connection  = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);
$partnerid   = '';
$srunconfid  = '';
$host        = '';

if ($connection) {

    // If a connection is made then include the JS libraries.
    $partnerid = local_kaltura_get_partner_id();
    $host = local_kaltura_get_host();

    $PAGE->requires->js('/local/kaltura/js/jquery.js', true);
    $PAGE->requires->js('/local/kaltura/js/simple_selector.js', true);
    $PAGE->requires->css('/local/kaltura/css/simple_selector.css');
}


$PAGE->set_url('/mod/kalmediaassign/view.php', array('id' => $id));
$PAGE->set_title(format_string($kalmediaassign->name));
$PAGE->set_heading($course->fullname);

$modulecontext = context_module::instance(CONTEXT_MODULE, $cm->id);

// Update 'viewed' state if required by completion system.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

if (local_kaltura_has_mobile_flavor_enabled() && local_kaltura_get_enable_html5()) {
    $uiconfid = local_kaltura_get_player_uiconf('player');
    $url = new moodle_url(local_kaltura_htm5_javascript_url($uiconfid));
    $PAGE->requires->js($url, true);
    $url = new moodle_url('/local/kaltura/js/frameapi.js');
    $PAGE->requires->js($url, true);
    $url = new moodle_url('/local/kaltura/js/simple_selector.js');
    $PAGE->requires->js($url, true);
}

echo $OUTPUT->header();

$coursecontext = context_course::instance($COURSE->id);

$renderer = $PAGE->get_renderer('mod_kalmediaassign');

echo $OUTPUT->box_start('generalbox');

echo format_module_intro('kalmediaassign', $kalmediaassign, $cm->id);
echo $OUTPUT->box_end();

$entryobject   = null;
$disabled       = false;

if (empty($connection)) {

    echo $OUTPUT->notification(get_string('conn_failed_alt', 'local_kaltura'));
    $disabled = true;

}

echo $renderer->display_mod_header($kalmediaassign, $coursecontext);

if (has_capability('mod/kalmediaassign:gradesubmission', $coursecontext)) {
    echo $renderer->display_grading_summary($cm, $kalmediaassign, $coursecontext);
    echo $renderer->display_instructor_buttons($cm, $USER->id);
}

if (has_capability('mod/kalmediaassign:submit', $coursecontext)) {

    echo $renderer->display_submission_status($cm, $kalmediaassign, $coursecontext);

    $param = array('mediaassignid' => $kalmediaassign->id, 'userid' => $USER->id);
    $submission = $DB->get_record('kalmediaassign_submission', $param);

    if (!empty($submission->entry_id)) {
        $entryobject = local_kaltura_get_ready_entry_object($submission->entry_id, false);
    }

    $disabled = !kalmediaassign_assignment_submission_opened($kalmediaassign) ||
                kalmediaassign_assignment_submission_expired($kalmediaassign) &&
                $kalmediaassign->preventlate;

    echo $renderer->display_submission($cm, $USER->id, $entryobject);

    if (empty($submission->entry_id) and empty($submission->timecreated)) {

        echo $renderer->display_student_submit_buttons($cm, $USER->id, $disabled);

    } else {
        if ($disabled ||
            !kalmediaassign_assignment_submission_resubmit($kalmediaassign, $entryobject)) {

            $disabled = true;
        }

        echo $renderer->display_student_resubmit_buttons($cm, $USER->id, $disabled);
    }

    echo $renderer->display_grade_feedback($kalmediaassign, $coursecontext);
}

echo $OUTPUT->footer();