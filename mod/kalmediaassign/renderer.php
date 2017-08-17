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
 * Kaltura media assignment renderer class
 *
 * @package    mod
 * @subpackage kalmediaassign
 * @copyright  (C) 2013 onwards Remote-Learner {@link http://www.remote-learner.ca/}
 * @copyright  (C) 2016-2017 Yamaguchi University <info-cc@ml.cc.yamaguchi-u.ac.jp>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/lib/tablelib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/lib/moodlelib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/kaltura_entries.class.php');

if (!defined('MOODLE_INTERNAL')) {
    // It must be included from a Moodle page.
    die('Direct access to this script is forbidden.');
}

/**
 * Table class for displaying media submissions for grading
 */
class submissions_table extends table_sql {

    protected $_quickgrade;
    protected $_gradinginfo;
    protected $_cminstance;
    protected $_grademax;
    protected $_cols = 20;
    protected $_rows = 4;
    protected $_tifirst;
    protected $_tilast;
    protected $_page;
    protected $_entries;
    protected $_access_all_groups = false;
    protected $_connection = false;

    public function __construct($uniqueid, $cm, $gradinginfo, $quickgrade = false,
                         $tifirst = '', $tilast = '', $page = 0, $entries = array(),
                         $connection) {

        global $DB;

        parent::__construct($uniqueid);

        $this->_quickgrade = $quickgrade;
        $this->_gradinginfo = $gradinginfo;

        $instance = $DB->get_record('kalmediaassign', array('id' => $cm->instance),
                                    'id,grade');

        $instance->cmid = $cm->id;

        $this->_cminstance = $instance;

        $this->_grademax = $this->_gradinginfo->items[0]->grademax;

        $this->_tifirst      = $tifirst;
        $this->_tilast       = $tilast;
        $this->_page         = $page;
        $this->_entries      = $entries;
        $this->_connection   = $connection;

    }

    public function col_picture($data) {
        global $OUTPUT;

        $user = new stdClass();
        $user->id           = $data->id;
        $user->picture      = $data->picture;
        $user->imagealt     = $data->imagealt;
        $user->firstname    = $data->firstname;
        $user->lastname     = $data->lastname;
        $user->email        = $data->email;
        $user->firstnamephonetic = $data->firstnamephonetic;
        $user->lastnamephonetic = $data->lastnamephonetic;
        $user->middlename = $data->middlename;
        $user->alternatename = $data->alternatename;

        $output = $OUTPUT->user_picture($user);

        $attr = array('type' => 'hidden',
                     'name' => 'users['.$data->id.']',
                     'value' => $data->id);
        $output .= html_writer::empty_tag('input', $attr);

        return $output;
    }

    public function col_selectgrade($data) {
        global $CFG;

        $output      = '';
        $finalgrade = false;

        if (array_key_exists($data->id, $this->_gradinginfo->items[0]->grades)) {

            $finalgrade = $this->_gradinginfo->items[0]->grades[$data->id];

            if ($CFG->enableoutcomes) {

                $finalgrade->formatted_grade = $this->_gradinginfo->items[0]->grades[$data->id]->str_grade;
            } else {

                // Equation taken from mod/assignment/lib.php display_submissions().
                $finalgrade->formatted_grade = round($finalgrade->grade, 2) . ' / ' . round($this->_grademax, 2);
            }
        }

        if (!is_bool($finalgrade) && ($finalgrade->locked || $finalgrade->overridden) ) {

            $lockedoverridden = 'locked';

            if ($finalgrade->overridden) {
                $lockedoverridden = 'overridden';
            }

            $attr = array('id' => 'g'.$data->id,
                          'class' => $lockedoverridden);

            $output = html_writer::tag('div', $finalgrade->formatted_grade, $attr);

        } else if (!empty($this->_quickgrade)) {

            $attributes = array();

            $gradesmenu = make_grades_menu($this->_cminstance->grade);

            $default = array(-1 => get_string('nograde'));

            $grade = null;

            if (!empty($data->timemarked)) {
                $grade = $data->grade;
            }

            $output = html_writer::select($gradesmenu, 'menu['.$data->id.']', $grade, $default, $attributes);

        } else {

            $output = get_string('nograde');

            if (!empty($data->timemarked)) {
                $output = $this->display_grade($data->grade);
            }
        }

        return $output;
    }


    public function col_submissioncomment($data) {
        global $OUTPUT;

        $output      = '';
        $finalgrade = false;

        if (array_key_exists($data->id, $this->_gradinginfo->items[0]->grades)) {
            $finalgrade = $this->_gradinginfo->items[0]->grades[$data->id];
        }

        if ( (!is_bool($finalgrade) && ($finalgrade->locked || $finalgrade->overridden)) ) {

            $output = shorten_text(strip_tags($data->submissioncomment), 15);

        } else if (!empty($this->_quickgrade)) {

            $param = array('id' => 'comments_' . $data->submitid,
                           'rows' => $this->_rows,
                           'cols' => $this->_cols,
                           'name' => 'submissioncomment['.$data->id.']');

            $output .= html_writer::start_tag('textarea', $param);
            $output .= $data->submissioncomment;
            $output .= html_writer::end_tag('textarea');

        } else {
            $output = shorten_text(strip_tags($data->submissioncomment), 15);
        }

        return $output;
    }

    public function col_grademarked($data) {

        $output = '';

        if (!empty($data->timemarked)) {
            $output = userdate($data->timemarked);
        }

        return $output;
    }

    public function col_timemodified($data) {

        $attr = array('id' => 'ts'.$data->id);

        $datemodified = $data->timemodified;
        $datemodified = is_null($datemodified) || empty($data->timemodified) ? '' : userdate($datemodified);

        $output = html_writer::tag('div', $datemodified, $attr);

        $output .= html_writer::empty_tag('br');
        $output .= html_writer::start_tag('center');

        if (!empty($data->entry_id)) {

            $note = '';

            $attr = array('id' => 'media_' .$data->entry_id,
                          'class' => 'media_thumbnail_cl',
                          'style' => 'cursor:pointer;');

            // Check if connection to Kaltura can be established.
            if ($this->_connection) {

                if (!array_key_exists($data->entry_id, $this->_entries)) {
                    $note = get_string('grade_media_not_cache', 'kalmediaassign');

                    /*
                     * If the entry has not yet been cached, force a call to retrieve the entry object
                     * from the Kaltura server so that the thumbnail can be displayed.
                     */
                    $entryobject = local_kaltura_get_ready_entry_object($data->entry_id, false);
                    $attr['src'] = $entryobject->thumbnailUrl;
                    $attr['alt'] = $entryobject->name;
                    $attr['title'] = $entryobject->name;
                } else {
                    // Retrieve object from cache.
                    $attr['src'] = $this->_entries[$data->entry_id]->thumbnailUrl;
                    $attr['alt'] = $this->_entries[$data->entry_id]->name;
                    $attr['title'] = $this->_entries[$data->entry_id]->name;
                }

                $output .= html_writer::tag('p', $note);

                $output .= html_writer::empty_tag('img', $attr);
            } else {
                $output .= html_writer::tag('p', get_string('cannotdisplaythumbnail', 'kalmediaassign'));
            }

            $attr = array('id' => 'hidden_media_' . $data->entry_id,
                          'type' => 'hidden',
                          'value' => $data->entry_id);
            $output .= html_writer::empty_tag('input', $attr);

            $entryobject = local_kaltura_get_ready_entry_object($data->entry_id, false);

            if ($entryobject !== null) {
                list($modalwidth, $modalheight) = kalmediaassign_get_popup_player_dimensions();
                $markup = '';

                if (KalturaMediaType::IMAGE == $entryobject->mediaType) {
                    // Determine if the mobile theme is being used.
                    $theme = core_useragent::get_device_type_theme();
                    $markup .= local_kaltura_create_image_markup($entryobject, $entryobject->name, $theme,
                                                                 $modalwidth, $modalheight);
                    $markup .= '<br><br>';
                } else {
                    $kalturahost = local_kaltura_get_host();
                    $partnerid = local_kaltura_get_partner_id();
                    $uiconfid = local_kaltura_get_player_uiconf('player_resource');
                    $now = time();
                    $markup .= "<iframe src=\"" . $kalturahost . "/p/" . $partnerid . "/sp/" . $partnerid . "00";
                    $markup .= "/embedIframeJs/uiconf_id/" . $uiconfid . "/partnerid/" . $partnerid;
                    $markup .= "?iframeembed=true&playerId=kaltura_player_" . $now;
                    $markup .= "&entry_id=" . $data->entry_id . "\" width=\"" . $modalwidth . "\" height=\"" . $modalheight . "\" ";
                    $markup .= "allowfullscreen webkitallowfullscreen mozAllowFullScreen frameborder=\"0\"></iframe>";
                }

                $attr = array('id' => 'hidden_markup_' . $data->entry_id,
                              'style' => 'display: none;');
                $output .= html_writer::start_tag('div', $attr);
                $output .= $markup;
                $output .= html_writer::end_tag('div');

            }
        }

        $output .= html_writer::end_tag('center');

        return $output;
    }

    public function col_grade($data) {
        $finalgrade = false;

        if (array_key_exists($data->id, $this->_gradinginfo->items[0]->grades)) {
            $finalgrade = $this->_gradinginfo->items[0]->grades[$data->id];
        }

        $finalgrade = (!is_bool($finalgrade)) ? $finalgrade->str_grade : '-';

        $attr = array('id' => 'finalgrade_'.$data->id);
        $output = html_writer::tag('span', $finalgrade, $attr);

        return $output;
    }

    public function col_timemarked($data) {

        $output = '-';

        if (0 < $data->timemarked) {

                $attr = array('id' => 'tt'.$data->id);
                $output = html_writer::tag('div', userdate($data->timemarked), $attr);

        } else {
            $otuput = '-';
        }

        return $output;
    }


    public function col_status($data) {
        global $OUTPUT, $CFG;

        require_once(dirname(dirname(dirname(__FILE__))) . '/lib/weblib.php');

        $url = new moodle_url('/mod/kalmediaassign/single_submission.php',
                                    array('cmid' => $this->_cminstance->cmid,
                                          'userid' => $data->id,
                                          'sesskey' => sesskey()));

        if (!empty($this->_tifirst)) {
            $url->param('tifirst', $this->_tifirst);
        }

        if (!empty($this->_tilast)) {
            $url->param('tilast', $this->_tilast);
        }

        if (!empty($this->_page)) {
            $url->param('page', $this->_page);
        }

        $buttontext = '';
        if ($data->timemarked > 0) {
            $class = 's1';
            $buttontext = get_string('update');
        } else {
            $class = 's0';
            $buttontext  = get_string('grade');
        }

        $attr = array('id' => 'up'.$data->id,
                      'class' => $class);

        $output = html_writer::link($url, $buttontext, $attr);

        return $output;

    }

    /**
     *  Return a grade in user-friendly form, whether it's a scale or not
     *
     * @global object
     * @param mixed $grade
     * @return string User-friendly representation of grade
     *
     * TODO: Move this to locallib.php
     */
    public function display_grade($grade) {
        global $DB;

        static $kalscalegrades = array();   // Cache scales for each assignment - they might have different scales!!

        if ($this->_cminstance->grade >= 0) { // Normal number.
            if ($grade == -1) {
                return '-';
            } else {
                return $grade . ' / ' . $this->_cminstance->grade;
            }

        } else { // Scale.

            if (empty($kalscalegrades[$this->_cminstance->id])) {

                if ($scale = $DB->get_record('scale', array('id' => -($this->_cminstance->grade)))) {

                    $kalscalegrades[$this->_cminstance->id] = make_menu_from_list($scale->scale);
                } else {

                    return '-';
                }
            }

            if (isset($kalscalegrades[$this->_cminstance->id][$grade])) {
                return $kalscalegrades[$this->_cminstance->id][$grade];
            }
            return '-';
        }
    }

}

class mod_kalmediaassign_renderer extends plugin_renderer_base {

    public function display_submission($kalmediaobj, $userid, $entryobj = null) {
        global $CFG, $OUTPUT;

        $imgsource = '';
        $imgname   = '';

        $html = '';

        $html .= $OUTPUT->heading(get_string('submission', 'kalmediaassign'), 3);

        $html .= html_writer::start_tag('p');

        // Tabindex -1 is required in order for the focus event to be capture amongst all browsers.
        $attr = array('id' => 'notification',
                      'class' => 'notification',
                      'tabindex' => '-1');
        $html .= html_writer::tag('div', '', $attr);

        if (!empty($entryobj)) {

            $imgname   = $entryobj->name;
            $imgsource = $entryobj->thumbnailUrl;

        } else {
            $imgname   = 'Media submission';
            $imgsource = $CFG->wwwroot . '/local/kaltura/pix/vidThumb.png';
        }

        $attr = array('id' => 'media_thumbnail',
                      'src' => $imgsource,
                      'alt' => $imgname,
                      'title' => $imgname,
                      'style' => 'z-index: -2');

        $html .= html_writer::empty_tag('img', $attr);

        $html .= html_writer::end_tag('p');

        return $html;

    }

    public function display_mod_header($kalmediaobj, $coursecontext) {
        global $DB, $COURSE;

        $html = '';

        $html .= $this->output->container_start('introduction');
        $html .= $this->output->heading($kalmediaobj->name, 2);
        $html .= $this->output->spacer(array('height' => 10));
        $html .= $this->output->box_start('generalbox introduction');
        $html .= $kalmediaobj->intro;
        $html .= $this->output->box_end();
        $html .= $this->output->container_end();
        $html .= $this->output->spacer(array('height' => 20));

        return $html;
    }

    public function display_grading_summary($cm, $kalmediaobj, $coursecontext) {
        global $DB, $COURSE;
        $html = '';

        if (!has_capability('mod/kalmediaassign:gradesubmission', $coursecontext)) {
             return '';
        }

        $html .= $this->output->container_start('gradingsummary');
        $html .= $this->output->heading(get_string('gradingsummary', 'kalmediaassign'), 3);
        $html .= $this->output->box_start('generalbox gradingsummary');

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';

        $roleid = 0;
        $roledata = $DB->get_records('role', array('shortname' => 'student'));
        foreach ($roledata as $row) {
            $roleid = $row->id;
        }

        $nummembers = $DB->count_records('role_assignments',
                                          array('contextid' => $coursecontext->id,
                                                'roleid' => $roleid)
                                         );

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('numberofmembers', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell($nummembers);
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $csql = "select count(*) " .
                "from {kalmediaassign_submission} " .
                "where mediaassignid = :mediaassignid " .
                "and timecreated > :timecreated ";
        $param = array('mediaassignid' => $kalmediaobj->id, 'timecreated' => 0);
        $numsubmissions = $DB->count_records_sql($csql, $param);

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('numberofsubmissions', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell($numsubmissions);
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $users = kalmediaassign_get_submissions($cm->instance, KALASSIGN_REQ_GRADING);

        if (empty($users)) {
            $users = array();
        }

        $students = kalmediaassign_get_assignment_students($cm);

        $numrequire = 0;

        $query = "select count({user}.id) as num from {role_assignments} " .
                 "join {user} on {user}.id={role_assignments}.userid and " .
                 "{role_assignments}.contextid='$coursecontext->id' and " .
                 "{role_assignments}.roleid='$roleid' " .
                 "left join {kalmediaassign_submission} ".
                 "on {kalmediaassign_submission}.userid = {user}.id and " .
                 "{kalmediaassign_submission}.mediaassignid = $cm->instance " .
                 "where {kalmediaassign_submission}.timemarked < {kalmediaassign_submission}.timemodified and " .
                 "{user}.deleted = 0";

        if (!empty($users) && $users !== array()) {
            $users = array_intersect(array_keys($users), array_keys($students));
            $query = $query . " and {user}.id in (" . implode(',', $users). ")";
        }

        $result = $DB->get_recordset_sql( $query );

        foreach ($result as $row) {
            $numrequire = $row->num;
        }

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('numberofrequiregrading', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell($numrequire);
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('availabledate', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell('-');

        if (!empty($kalmediaobj->timeavailable)) {
            $str = userdate($kalmediaobj->timeavailable);
            if (!kalmediaassign_assignment_submission_opened($kalmediaobj)) {
                $str = html_writer::start_tag('font', array('color' => 'blue')) . $str;
                $str .= ' (' . get_string('submissionnotopened', 'kalmediaassign'). ')';
                $str .= html_writer::end_tag('font');
            }

            $cell2 = new html_table_cell($str);
        }

        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('duedate', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell('-');

        if (!empty($kalmediaobj->timedue)) {
            $str = userdate($kalmediaobj->timedue);
            if (kalmediaassign_assignment_submission_expired($kalmediaobj)) {
                $str = html_writer::start_tag('font', array('color' => 'red')) . $str;
                $str .= ' (' . get_string('submissionexpired', 'kalmediaassign') . ')';
                $str .= html_writer::end_tag('font');
            }

            $cell2 = new html_table_cell($str);
        }

        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('remainingtime', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell('-');

        if (!empty($kalmediaobj->timedue)) {
            $remain = kalmediaassign_get_remainingdate($kalmediaobj->timedue);
            $cell2 = new html_table_cell($remain);
        }

        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $html .= html_writer::table($table);

        $html .= $this->output->box_end();
        $html .= $this->output->container_end();
        $html .= $this->output->spacer(array('height' => 20));

        return $html;
    }

    public function display_submission_status($cm, $kalmediaobj, $coursecontext) {
        global $DB, $COURSE, $USER;
        $html = '';

        if (!has_capability('mod/kalmediaassign:submit', $coursecontext)) {
            return '';
        }

        $html .= $this->output->container_start('submissionstatus');
        $html .= $this->output->heading(get_string('submissionstatus', 'kalmediaassign'), 3);
        $html .= $this->output->box_start('generalbox submissionstatus');

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $submissionstatus = get_string('status_nosubmission', 'kalmediaassign');
        $gradingstatus = get_string('status_nomarked', 'kalmediaassign');

        if (! $kalmediaassign = $DB->get_record('kalmediaassign', array("id" => $cm->instance))) {
            print_error('invalidid', 'kalmediaassign');
        }

        $param = array('mediaassignid' => $kalmediaassign->id, 'userid' => $USER->id);
        $submission = $DB->get_record('kalmediaassign_submission', $param);

        if (!empty($submission) and !empty($submission->entry_id)) {
            $submissionstatus = get_string('status_submitted', 'kalmediaassign');
            $entryobject = local_kaltura_get_ready_entry_object($submission->entry_id, false);
        }

        if (!empty($submission) and !empty($submission->timecreated) and
            $submission->timemarked > 0 and $submission->timemarked > $submission->timecreated and
            $submission->timemarked > $submission->timemodified) {
            $gradingstatus = get_string('status_marked', 'kalmediaassign');
        }

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('submissionstatus', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell($submissionstatus);
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('gradingstatus', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell($gradingstatus);
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('availabledate', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell('-');

        if (!empty($kalmediaobj->timeavailable)) {
            $str = userdate($kalmediaobj->timeavailable);
            if (!kalmediaassign_assignment_submission_opened($kalmediaobj)) {
                $str = html_writer::start_tag('font', array('color' => 'blue')) . $str;
                $str .= ' (' . get_string('submissionnotopened', 'kalmediaassign'). ')';
                $str .= html_writer::end_tag('font');
            }

            $cell2 = new html_table_cell($str);
        }

        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('duedate', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell('-');

        if (!empty($kalmediaobj->timedue)) {
            $str = userdate($kalmediaobj->timedue);
            if (kalmediaassign_assignment_submission_expired($kalmediaobj)) {
                $str = html_writer::start_tag('font', array('color' => 'red')) . $str;
                $str .= ' (' . get_string('submissionexpired', 'kalmediaassign'). ')';
                $str .= html_writer::end_tag('font');
            }

            $cell2 = new html_table_cell($str);
        }

        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('remainingtime', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell('-');

        if (!empty($kalmediaobj->timedue)) {
            $remain = kalmediaassign_get_remainingdate($kalmediaobj->timedue);
            $cell2 = new html_table_cell($remain);
        }

        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('status_timemodified', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell('-');

        if (!empty($submission->timemodified)) {
            $str = userdate($submission->timemodified);
            if ($submission->timemodified > $kalmediaobj->timedue) {
                $str = html_writer::start_tag('font', array('color' => 'red')) . $str;
                $str .= ' (' . get_string('latesubmission', 'kalmediaassign'). ')';
                $str .= html_writer::end_tag('font');
            }

            $cell2 = new html_table_cell($str);
        }

        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $html .= html_writer::table($table);

        $html .= $this->output->box_end();
        $html .= $this->output->container_end();
        $html .= $this->output->spacer(array('height' => 20));

        return $html;
    }

    public function display_student_submit_buttons($cm, $userid, $disablesubmit = false) {

        $html = '';

        $target = new moodle_url('/mod/kalmediaassign/submission.php');

        $attr = array('method' => 'POST', 'action' => $target);

        $html .= html_writer::start_tag('form', $attr);

        $attr = array('type' => 'hidden',
                     'name' => 'entry_id',
                     'id' => 'entry_id',
                     'value' => '');
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name' => 'cmid',
                     'value' => $cm->id);
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name' => 'sesskey',
                     'value' => sesskey());
        $html .= html_writer::empty_tag('input', $attr);

        $context = context_module::instance($cm->id);

        $selectorurl = new moodle_url('/local/kaltura/simple_selector.php');

        $attr = array('type' => 'button',
                     'id' => 'add_media',
                     'name' => 'add_media',
                     'value' => get_string('addmedia', 'kalmediaassign'),
                     'onclick' => "fadeInSelectorWindow('" . $selectorurl . "')");

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $attr);

        $html .= '&nbsp;&nbsp;';

        $attr = array('type' => 'submit',
                     'name' => 'submit_media',
                     'id' => 'submit_media',
                     'disabled' => 'disabled',
                     'value' => get_string('submitmedia', 'kalmediaassign'));

        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::end_tag('form');

        return $html;
    }

    public function display_student_resubmit_buttons($cm, $userid, $disablesubmit = false) {
        global $DB;

        $param = array('mediaassignid' => $cm->instance, 'userid' => $userid);
        $submissionrec = $DB->get_record('kalmediaassign_submission', $param);

        $html = '';

        $target = new moodle_url('/mod/kalmediaassign/submission.php');

        $attr = array('method' => 'POST', 'action' => $target);

        $html .= html_writer::start_tag('form', $attr);

        $attr = array('type' => 'hidden',
                     'name'  => 'cmid',
                     'value' => $cm->id);
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name'  => 'entry_id',
                     'id'    => 'entry_id',
                     'value' => $submissionrec->entry_id);
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name'  => 'sesskey',
                     'value' => sesskey());
        $html .= html_writer::empty_tag('input', $attr);

        $selectorurl = new moodle_url('/local/kaltura/simple_selector.php');

        // Add submit and review buttons.
        $attr = array('type' => 'button',
                     'name' => 'replace_media',
                     'id' => 'replace_media',
                     'value' => get_string('replacemedia', 'kalmediaassign'),
                     'onclick' => "fadeInSelectorWindow('" . $selectorurl . "')");

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $attr);

        $html .= '&nbsp;&nbsp;';

        $attr = array('type' => 'submit',
                     'id'   => 'submit_media',
                     'name' => 'submit_media',
                     'disabled' => 'disabled',
                     'value' => get_string('submitmedia', 'kalmediaassign'));

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::end_tag('form');

        return $html;

    }

    public function display_instructor_buttons($cm,  $userid) {

        $html = '';

        $target = new moodle_url('/mod/kalmediaassign/grade_submissions.php');

        $attr = array('method' => 'POST', 'action' => $target);

        $html .= html_writer::start_tag('form', $attr);

        $html .= html_writer::start_tag('center');

        $attr = array('type' => 'hidden',
                     'name' => 'sesskey',
                     'value' => sesskey());
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name' => 'cmid',
                     'value' => $cm->id);
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'submit',
                     'name' => 'grade_submissions',
                     'value' => get_string('gradesubmission', 'kalmediaassign'));

        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::end_tag('center');

        $html .= html_writer::end_tag('form');

        return $html;
    }

    public function display_submissions_table($cm, $groupfilter = 0, $filter = 'all', $perpage, $quickgrade = false,
                                       $tifirst = '', $tilast = '', $page = 0) {

        global $DB, $OUTPUT, $COURSE, $USER;

        $kalturahost = local_kaltura_get_host();
        $partnerid = local_kaltura_get_partner_id();
        $uiconfid = local_kaltura_get_player_uiconf('player_resource');

        $mediawidth = 0;
        $mediaheight = 0;

        list($modalwidth, $modalheight) = kalmediaassign_get_popup_player_dimensions();
        $mediawidth = $modalwidth - KALTURA_POPUP_WIDTH_ADJUSTMENT;
        $mediaheight = $modalheight - KALTURA_POPUP_HEIGHT_ADJUSTMENT;

        // Get a list of users who have submissions and retrieve grade data for those users.
        $users = kalmediaassign_get_submissions($cm->instance, $filter);

        $definecolumns = array('picture', 'fullname', 'selectgrade', 'submissioncomment', 'timemodified',
                                'timemarked', 'status', 'grade');

        if (empty($users)) {
            $users = array();
        }

        $entryids = array();
        $entries = array();
        foreach ($users as $usersubmission) {
            $entryids[$usersubmission->entry_id] = $usersubmission->entry_id;
        }

        if (!empty($entryids)) {
            $clientobj = local_kaltura_login(true);

            if ($clientobj) {
                $entries = new KalturaStaticEntries();
                $entries = KalturaStaticEntries::list_entries($entryids, $clientobj->baseEntry);
            } else {
                echo $OUTPUT->notification(get_string('conn_failed_alt', 'local_kaltura'));
            }
        }

        /*
         *  Compare student who have submitted to the assignment with students who are
         * currently enrolled in the course.
         */
        $students = kalmediaassign_get_assignment_students($cm);

        $allstudents = array();

        foreach ($students as $s) {
            $allstudents[] = $s->id;
        }

        $users = array_intersect(array_keys($users), array_keys($students));

        $gradinginfo = grade_get_grades($cm->course, 'mod', 'kalmediaassign', $cm->instance, $users);

        $where = '';
        switch ($filter) {
            case KALASSIGN_SUBMITTED:
                $where = ' kms.timemodified > 0 AND ';
                break;
            case KALASSIGN_REQ_GRADING:
                $where = ' kms.timemarked < kms.timemodified AND ';
                break;
        }

        // Determine logic needed for groups mode.
        $param        = array();
        $groupswhere  = '';
        $groupscolumn = '';
        $groupsjoin   = '';
        $groups       = array();
        $groupids     = '';
        $coursecontext = context_course::instance($COURSE->id);

        // Get all groups that the user belongs to, check if the user has capability to access all groups.
        if (!has_capability('moodle/site:accessallgroups', $coursecontext, $USER->id)) {
            $groups = groups_get_all_groups($COURSE->id, $USER->id);

            if (empty($groups)) {
                $message = get_string('nosubmissions', 'kalmediaassign');
                echo html_writer::tag('center', $message);
                return;
            }
        } else {
            $groups = groups_get_all_groups($COURSE->id, $USER->id);
        }

        // Create a comma separated list of group ids.
        foreach ($groups as $group) {
            $groupids .= $group->id . ',';
        }

        $groupids = rtrim($groupids, ',');

        if ('' !== $groupids) {
            switch (groups_get_activity_groupmode($cm)) {
                case NOGROUPS:
                    // No groups, do nothing.
                    break;
                case SEPARATEGROUPS:

                    /*
                     * If separate groups, but displaying all users then we must display only users
                     * who are in the same group as the current user.
                     */
                    if (0 == $groupfilter) {
                        $groupscolumn = ', {groups_members}.groupid ';
                        $groupsjoin = ' RIGHT JOIN {groups_members} ON {groups_members}.userid = {user}.id' .
                                      ' RIGHT JOIN {groups} ON {groups}.id = {groups_members}.groupid ';

                        $param['courseid'] = $COURSE->id;
                        $groupswhere  .= ' AND {groups}.courseid = :courseid ';

                        $param['groupid'] = $groupfilter;
                        $groupswhere .= ' AND {groups}.id IN ('. $groupids . ') ';

                    }
                     break;

                case VISIBLEGROUPS:

                     /*
                      * If visible groups but displaying a specific group then we must display users within
                      * that group, if displaying all groups then display all users in the course.
                      */
                    if (0 != $groupfilter) {
                        $groupscolumn = ', {groups_members}.groupid ';
                        $groupsjoin = ' RIGHT JOIN {groups_members} ON {groups_members}.userid = u.id' .
                                      ' RIGHT JOIN {groups} ON {groups}.id = {groups_members}.groupid ';

                        $param['courseid'] = $COURSE->id;
                        $groupswhere .= ' AND {groups_members}.courseid = :courseid ';

                        $param['groupid'] = $groupfilter;
                        $groupswhere .= ' AND {groups_members}.groupid = :groupid ';

                    }
                    break;
            }
        }

        $kaltura    = new kaltura_connection();
        $connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);
        $table      = new submissions_table('kal_media_submit_table', $cm, $gradinginfo, $quickgrade,
                                            $tifirst, $tilast, $page, $entries, $connection);

        $roleid = 0;

        $roledata = $DB->get_records('role', array('shortname' => 'student'));

        foreach ($roledata as $row) {
            $roleid = $row->id;
        }

        /*
         * In order for the sortable first and last names to work.
         * User ID has to be the first column returned and must be returned as id.
         * Otherwise the table will display links to user profiles that are incorrect or do not exist.
         */
        $columns = '{user}.id, {kalmediaassign_submission}.id as submitid, {user}.firstname, {user}.lastname, ' .
                   '{user}.picture, {user}.firstnamephonetic, {user}.lastnamephonetic, {user}.middlename, ' .
                   '{user}.alternatename, {user}.imagealt, {user}.email, '.
                   '{kalmediaassign_submission}.grade, {kalmediaassign_submission}.submissioncomment, ' .
                   '{kalmediaassign_submission}.timemodified, {kalmediaassign_submission}.entry_id, ' .
                   '{kalmediaassign_submission}.timemarked, ' .
                   ' 1 as status, 1 as selectgrade' . $groupscolumn;
        $where .= ' {user}.deleted = 0 ';

        if ($filter == KALASSIGN_NOTSUBMITTEDYET and $users !== array()) {
            $where .= ' and {user}.id not in (' . implode(',', $users) . ') ';
        } else {
            if (($filter == KALASSIGN_REQ_GRADING or $filter == KALASSIGN_SUBMITTED) and $users !== array()) {
                $where          .= ' and {user}.id in (' . implode(',', $users) . ') ';
            }
        }

        $where .= $groupswhere;

        $param['instanceid'] = $cm->instance;
        $from = "{role_assignments} " .
                "join {user} on {user}.id={role_assignments}.userid and " .
                "{role_assignments}.contextid='$coursecontext->id' and {role_assignments}.roleid='$roleid' " .
                "left join {kalmediaassign_submission} on {kalmediaassign_submission}.userid = {user}.id and " .
                "{kalmediaassign_submission}.mediaassignid = :instanceid " .
                $groupsjoin;

        $baseurl = new moodle_url('/mod/kalmediaassign/grade_submissions.php', array('cmid' => $cm->id));

        $col1 = get_string('fullname', 'kalmediaassign');
        $col2 = get_string('grade', 'kalmediaassign');
        $col3 = get_string('submissioncomment', 'kalmediaassign');
        $col4 = get_string('timemodified', 'kalmediaassign');
        $col5 = get_string('grademodified', 'kalmediaassign');
        $col6 = get_string('status', 'kalmediaassign');
        $col7 = get_string('finalgrade', 'kalmediaassign');

        $table->set_sql($columns, $from, $where, $param);
        $table->define_baseurl($baseurl);
        $table->collapsible(true);

        $table->define_columns($definecolumns);
        $table->define_headers(array('', $col1, $col2, $col3, $col4, $col5, $col6, $col7));

        echo html_writer::start_tag('center');

        $attributes = array('action' => new moodle_url('grade_submissions.php'),
                            'id'     => 'fastgrade',
                            'method' => 'post');
        echo html_writer::start_tag('form', $attributes);

        $attributes = array('type' => 'hidden',
                            'name' => 'cmid',
                            'value' => $cm->id);
        echo html_writer::empty_tag('input', $attributes);

        $attributes['name'] = 'mode';
        $attributes['value'] = 'fastgrade';

        echo html_writer::empty_tag('input', $attributes);

        $attributes['name'] = 'sesskey';
        $attributes['value'] = sesskey();

        echo html_writer::empty_tag('input', $attributes);

        $table->out($perpage, true);

        if ($quickgrade) {
            $attributes = array('type' => 'submit',
                                'name' => 'save_feedback',
                                'value' => get_string('savefeedback', 'kalmediaassign'));

            echo html_writer::empty_tag('input', $attributes);
        }

        echo html_writer::end_tag('form');

        echo html_writer::end_tag('center');

        $attr = array('type' => 'hidden', 'name' => 'kalturahost', 'id' => 'kalturahost', 'value' => $kalturahost);
        echo html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden', 'name' => 'partnerid', 'id' => 'partnerid', 'value' => $partnerid);
        echo html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden', 'name' => 'uiconfid', 'id' => 'uiconfid', 'value' => $uiconfid);
        echo html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden', 'name' => 'modalwidth', 'id' => 'modalwidth', 'value' => $modalwidth);
        echo html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden', 'name' => 'modalheight', 'id' => 'modalheight', 'value' => $modalheight);
        echo html_writer::empty_tag('input', $attr);

        $attr = array('id' => 'modal_content', 'style' => '');
        echo html_writer::start_tag('div', $attr);
        echo html_writer::end_tag('div');

    }

    /**
     * Displays the assignments listing table.
     *
     * @param object $course The course odject.
     */
    public function display_kalmediaassignments_table($course) {
        global $CFG, $DB, $PAGE, $OUTPUT, $USER;

        echo html_writer::start_tag('center');

        $strplural = get_string('modulenameplural', 'kalmediaassign');

        if (!$cms = get_coursemodules_in_course('kalmediaassign', $course->id, 'm.timedue')) {
            echo get_string('noassignments', 'mod_kalmediaassign');
            echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$course->id);
        }

        $strsectionname  = get_string('sectionname', 'format_'.$course->format);
        $usesections = course_format_uses_sections($course->format);
        $modinfo = get_fast_modinfo($course);

        if ($usesections) {
            $sections = $modinfo->get_section_info_all();
        }
        $courseindexsummary = new kalmediaassign_course_index_summary($usesections, $strsectionname);

        $timenow = time();
        $currentsection = '';
        $assignmentcount = 0;

        foreach ($modinfo->instances['kalmediaassign'] as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            $assignmentcount++;
            $timedue = $cms[$cm->id]->timedue;

            $sectionname = '';
            if ($usesections && $cm->sectionnum) {
                $sectionname = get_section_name($course, $sections[$cm->sectionnum]);
            }

            $submitted = '';
            $context = context_module::instance($cm->id);

            if (has_capability('mod/kalmediaassign:gradesubmission', $context)) {
                $submitted = $DB->count_records('kalmediaassign_submission', array('mediaassignid' => $cm->instance));
            } else if (has_capability('mod/kalmediaassign:submit', $context)) {
                if ($DB->count_records('kalmediaassign_submission',
                                       array('mediaassignid' => $cm->instance, 'userid' => $USER->id)) > 0) {
                    $submitted = get_string('submitted', 'mod_kalmediaassign');
                } else {
                    $submitted = get_string('nosubmission', 'mod_kalmediaassign');
                }
            }

            $gradinginfo = grade_get_grades($course->id, 'mod', 'kalmediaassign', $cm->instance, $USER->id);
            if (isset($gradinginfo->items[0]->grades[$USER->id]) && !$gradinginfo->items[0]->grades[$USER->id]->hidden ) {
                $grade = $gradinginfo->items[0]->grades[$USER->id]->str_grade;
            } else {
                $grade = '-';
            }

            $courseindexsummary->add_assign_info($cm->id, $cm->name, $sectionname, $timedue, $submitted, $grade);
        }

        if ($assignmentcount > 0) {
            $pagerenderer = $PAGE->get_renderer('mod_kalmediaassign');
            echo $pagerenderer->render($courseindexsummary);
        }

        echo html_writer::end_tag('center');
    }

    /**
     * Display the feedback to the student
     *
     * This default method prints the teacher picture and name, date when marked,
     * grade and teacher submissioncomment.
     *
     * @global object
     * @global object
     * @global object
     * @param object $submission The submission object or NULL in which case it will be loaded
     *
     * TODO: correct documentation for this function
     */
    public function display_grade_feedback($kalmediaassign, $context) {
        global $USER, $CFG, $DB, $OUTPUT;

        require_once($CFG->libdir.'/gradelib.php');

        // Check if the user is enrolled to the coruse and can submit to the assignment.
        if (!is_enrolled($context, $USER, 'mod/kalmediaassign:submit')) {
            // Can not submit assignments -> no feedback.
            return;
        }

        $gradinginfo = grade_get_grades($kalmediaassign->course, 'mod', 'kalmediaassign', $kalmediaassign->id, $USER->id);

        $item = $gradinginfo->items[0];
        $grade = $item->grades[$USER->id];

        if ($grade->hidden || $grade->grade === false) { // Hidden or Error.
            return;
        }

        if ($grade->grade === null && empty($grade->str_feedback)) { // Nothing to show yet.
            return;
        }

        $gradeddate = $grade->dategraded;
        $gradedby   = $grade->usermodified;

        // We need the teacher info.
        if (!$teacher = $DB->get_record('user', array('id' => $gradedby))) {
            print_error('cannotfindteacher');
        }

        $html = '';

        $html .= $this->output->container_start('feedback');
        $html .= $this->output->heading(get_string('feedback', 'kalmediaassign'), 3);
        $html .= $this->output->box_start('generalbox feedback');

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('grade'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell($grade->str_long_grade);
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('gradedon', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell(userdate($gradeddate));
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('gradedby', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell($OUTPUT->user_picture($teacher) . '&nbsp;&nbsp;' . fullname($teacher));
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('feedbackcomment', 'kalmediaassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell($grade->str_feedback);
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $html .= html_writer::table($table);

        $html .= $this->output->box_end();
        $html .= $this->output->container_end();
        $html .= $this->output->spacer(array('height' => 20));

        return $html;

    }

    /**
     * Render a course index summary.
     *
     * @param kalmediaassign_course_index_summary $indexsummary Structure for index summary.
     * @return string HTML for assignments summary table
     */
    public function render_kalmediaassign_course_index_summary(kalmediaassign_course_index_summary $indexsummary) {
        $strplural = get_string('modulenameplural', 'kalmediaassign');
        $strsectionname  = $indexsummary->courseformatname;
        $strduedate = get_string('duedate', 'kalmediaassign');
        $strsubmission = get_string('submission', 'kalmediaassign');
        $strgrade = get_string('grade');

        $table = new html_table();
        if ($indexsummary->usesections) {
            $table->head  = array ($strsectionname, $strplural, $strduedate, $strsubmission, $strgrade);
            $table->align = array ('left', 'left', 'center', 'right', 'right');
        } else {
            $table->head  = array ($strplural, $strduedate, $strsubmission, $strgrade);
            $table->align = array ('left', 'left', 'center', 'right');
        }
        $table->data = array();

        $currentsection = '';
        foreach ($indexsummary->assignments as $info) {
            $params = array('id' => $info['cmid']);
            $link = html_writer::link(new moodle_url('/mod/kalmediaassign/view.php', $params), $info['cmname']);
            $due = $info['timedue'] ? userdate($info['timedue']) : '-';

            $printsection = '';
            if ($indexsummary->usesections) {
                if ($info['sectionname'] !== $currentsection) {
                    if ($info['sectionname']) {
                        $printsection = $info['sectionname'];
                    }
                    if ($currentsection !== '') {
                        $table->data[] = 'hr';
                    }
                    $currentsection = $info['sectionname'];
                }
            }

            if ($indexsummary->usesections) {
                $row = array($printsection, $link, $due, $info['submissioninfo'], $info['gradeinfo']);
            } else {
                $row = array($link, $due, $info['submissioninfo'], $info['gradeinfo']);
            }
            $table->data[] = $row;
        }

        return html_writer::table($table);
    }
}