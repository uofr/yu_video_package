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
 * My Media main page
 *
 * @package    local
 * @subpackage mymedia
 * @copyright  (C) 2016-2017 Yamaguchi University <info-cc@ml.cc.yamaguchi-u.ac.jp>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');
require_once('lib.php');

if (!defined('MOODLE_INTERNAL')) {
    // It must be included from a Moodle page.
    die('Direct access to this script is forbidden.');
}

header('Access-Control-Allow-Origin: *');

require_login();

global $SESSION, $USER, $COURSE;

$page = optional_param('page', 0, PARAM_INT);
$sort = optional_param('sort', 'recent', PARAM_TEXT);
$simplesearch = '';
$medias = 0;

$mymedia = get_string('heading_mymedia', 'local_mymedia');
$PAGE->set_context(context_system::instance());
$header  = format_string($SITE->shortname).": $mymedia";

$PAGE->set_url('/local/mymedia/mymedia.php');
$PAGE->set_course($SITE);

$PAGE->set_pagetype('mymedia-index');
$PAGE->set_pagelayout('standard');
$PAGE->set_title($header);
$PAGE->set_heading($header);
$PAGE->add_body_class('mymedia-index');

$PAGE->requires->js('/local/kaltura/js/jquery.js', true);
$PAGE->requires->css('/local/mymedia/css/mymedia.css');

// Connect to Kaltura.
$kaltura = new kaltura_connection();
$connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

if (!$connection) {
    $url = new moodle_url('/admin/settings.php', array('section' => 'local_kaltura'));
    print_error('conn_failed', 'local_kaltura', $url);
}

$partnerid = local_kaltura_get_partner_id();

// Include javascript for screen recording widget.
$uiconfid  = local_kaltura_get_player_uiconf('mymedia_screen_recorder');
$host = local_kaltura_get_host();
$url = new moodle_url("{$host}/p/{$partnerid}/sp/{$partnerid}/ksr/uiconfId/{$uiconfid}");
$PAGE->requires->js($url, true);

$courseid = $COURSE->id;

if (local_kaltura_has_mobile_flavor_enabled() && local_kaltura_get_enable_html5()) {
    $uiconfid = local_kaltura_get_player_uiconf('player_resource');
    $url = new moodle_url(local_kaltura_htm5_javascript_url($uiconfid));
    $PAGE->requires->js($url, true);
    $url = new moodle_url('/local/kaltura/js/frameapi.js');
    $PAGE->requires->js($url, true);
}

echo $OUTPUT->header();

if ($data = data_submitted() and confirm_sesskey()) {
    // Make sure the user has the capability to search, and if the required parameter is set.

    if (has_capability('local/mymedia:search', $PAGE->context, $USER) && isset($data->simple_search_name)) {

        $data->simple_search_name = clean_param($data->simple_search_name, PARAM_NOTAGS);

        if (isset($data->simple_search_btn_name)) {
            $SESSION->mymedia = $data->simple_search_name;
        } else if (isset($data->clear_simple_search_btn_name)) {
            $SESSION->mymedia = '';
        }
    } else {
        // Clear the session variable in case the user's permissions were revoked during a search.
        $SESSION->mymedia = '';
    }
}

$context = context_user::instance($USER->id);

require_capability('local/mymedia:view', $context, $USER);

$renderer = $PAGE->get_renderer('local_mymedia');

if (local_kaltura_get_mymedia_permission()) {
    try {
        if (!$connection) {
            throw new Exception("Unable to connect");
        }

        $perpage = get_config(KALTURA_PLUGIN_NAME, 'mymedia_items_per_page');

        if (empty($perpage)) {
            $perpage = MYMEDIA_ITEMS_PER_PAGE;
        }

        $SESSION->mymediasort = $sort;

        $medias = null;

        $accesscontrol = local_kaltura_get_internal_access_control($connection);

        try {
            // Check if the sesison data is set.
            if (isset($SESSION->mymedia) && !empty($SESSION->mymedia)) {
                $medias = local_kaltura_search_mymedia_medias($connection, $SESSION->mymedia, $page + 1, $perpage, $sort);
            } else {
                $medias = local_kaltura_search_mymedia_medias($connection, '', $page + 1, $perpage, $sort);
            }

            $total = $medias->totalCount;
        } catch (Exception $ex) {
            $medias = null;
        }

        if ($medias instanceof KalturaMediaListResponse &&  0 < $medias->totalCount ) {
            $medias = $medias->objects;

            $pagenum = $page;

            // Set totalcount, current page number, number of items per page.
            // Remember to check the session if a search has been performed.
            $page = $OUTPUT->paging_bar($total,
                                        $page,
                                        $perpage,
                                        new moodle_url('/local/mymedia/mymedia.php', array('sort' => $sort)));


            echo $renderer->create_options_table_upper($page, $partnerid);

            echo $renderer->create_medias_table($medias, $pagenum, $sort, $accesscontrol);

            echo $renderer->create_options_table_lower($page);

        } else {
            echo $renderer->create_options_table_upper($page, $partnerid);

            echo '<center>'. get_string('no_medias', 'local_mymedia') . '</center>';

            echo $renderer->create_medias_table(array(), 0, 'recent', $connection);
        }

        // Get Media detail panel markup.
        $courses = null;

        $mediadetails = $renderer->media_details_markup($courses);
        $dialog = $renderer->create_simple_dialog_markup();

        // Load YUI modules.
        $jsmodule = array(
            'name'     => 'local_mymedia',
            'fullpath' => '/local/mymedia/js/mymedia.js',
            'requires' => array('base', 'dom', 'node',
                                'event-delegate', 'yui2-container', 'yui2-animation',
                                'yui2-dragdrop', 'tabview',
                                'collection', 'io-base', 'json-parse',

                                ),
            'strings' => array(array('media_converting',   'local_mymedia'),
                               array('loading',            'local_mymedia'),
                               array('error_saving',       'local_mymedia'),
                               array('missing_required',   'local_mymedia'),
                               array('error_not_owner',    'local_mymedia'),
                               array('failure_saved_hdr',  'local_mymedia'),
                               array('success_saving',     'local_mymedia'),
                               array('success_saving_hdr', 'local_mymedia'),
                               array('upload_success_hdr', 'local_mymedia'),
                               array('upload_success',     'local_mymedia'),
                               array('continue',           'local_mymedia')
                               )

            );

        $editmeta = has_capability('local/mymedia:editmetadata', $context, $USER) ? 1 : 0;

        $savemediascript = "../../local/mymedia/save_media_details.php?entryid=";
        $conversionscript = "../../local/mymedia/check_conversion.php?courseid={$courseid}&entryid=";
        $loadingmarkup = $renderer->create_loading_screen_markup();
        $uiconfid = local_kaltura_get_player_uiconf('player_filter');

        $PAGE->requires->js_init_call('M.local_mymedia.init_config', array($mediadetails, $dialog, $conversionscript,
                                                                           $savemediascript, $uiconfid,
                                                                           $loadingmarkup, $editmeta
                                                                          ), true, $jsmodule);

        $connection->session->end();
    } catch (Exception $ex) {
        $errormessage = 'View - error main page(' .  $ex->getMessage() . ')';
        print_error($errormessage, 'local_mymedia');

        echo get_string('problem_viewing', 'local_mymedia') . '<br>';
        echo $ex->getMessage();
    }

} else {
    echo get_string('permission_disable', 'local_mymedia');
}

echo $OUTPUT->footer();
