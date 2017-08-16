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
 * Refactored code from the Kaltura local plug-in directory. This script
 * has less code and performs an additional check for the media's custom
 * metadata fields.
 *
 * @package    local
 * @subpackage mymedia
 * @copyright  (C) 2016-2017 Yamaguchi University <info-cc@ml.cc.yamaguchi-u.ac.jp>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/API/KalturaClient.php');

global $USER, $SESSION, $DB;

if (!defined('MOODLE_INTERNAL')) {
    // It must be included from a Moodle page.
    die('Direct access to this script is forbidden.');
}

$entryid   = required_param('entryid', PARAM_TEXT);
$page = required_param('page', PARAM_INT);
$sort = optional_param('sort', 'recent', PARAM_TEXT);

require_login();

$mymedia = get_string('heading_mymedia', 'local_mymedia');
$PAGE->set_context(context_system::instance());
$header  = format_string($SITE->shortname).": $mymedia";

$PAGE->set_url('/local/mymedia/access_media.php');
$PAGE->set_course($SITE);

$PAGE->set_pagetype('mymedia-index');
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title($header);
$PAGE->set_heading($header);
$PAGE->add_body_class('mymedia-index');
$PAGE->requires->js('/local/kaltura/js/jquery.js', true);
$PAGE->requires->js('/local/mymedia/js/access_media.js', true);
$PAGE->requires->css('/local/mymedia/css/mymedia.css');

$kaltura = new kaltura_connection();
$connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);
$context = context_user::instance($USER->id);

if (!$connection) {
    $url = new moodle_url('/admin/settings.php', array('section' => 'local_kaltura'));
    print_error('conn_failed', 'local_kaltura', $url);
}

// Get publiser name, admin secret, hostname, parter id.
$publishername = local_kaltura_get_publisher_name();
$secret = local_kaltura_get_admin_secret();
$kalturahost = local_kaltura_get_host();
$partnerid = local_kaltura_get_partner_id();
$uiconfid = local_kaltura_get_player_uiconf('player_resource');
$expiry = KALTURA_SESSION_LENGTH;

echo $OUTPUT->header();

$media = $connection->media->get($entryid);

if ($media == null or $media->status == KalturaEntryStatus::DELETED) {
    echo get_string('access_media_not_exist', 'local_mymedia');
} else if ($media->userId != $USER->username) {
    echo get_string('access_media_failed_not_owner', 'local_mymedia');
} else {
    // Star kaltura session.
    $ks = $connection->session->start($secret, $publishername, KalturaSessionType::ADMIN, $partnerid, $expiry);

    if ($ks == null or $ks == '') {
        $url = new moodle_url('/local/mymedia/access_media.php', array('entryid' => $entryid));
        print_error('kaltura_session_failed', 'local_kaltura', $url);
    } else {
        echo html_writer::start_tag('h3');
        echo get_string('access_media_title', 'local_mymedia');
        echo html_writer::end_tag('h3');

        $internalcontrol = local_kaltura_get_internal_access_control($connection);
        $defaultcontrol = local_kaltura_get_default_access_control($connection);
        $currentcontrolid = $media->accessControlId;
        $url = new moodle_url('/local/mymedia/mymedia.php', array('page' => $page, 'sort' => $sort));

        $renderer = $PAGE->get_renderer('local_mymedia');

        echo $renderer->create_media_details_table_markup($media);
        echo $renderer->create_hidden_input_markup($kalturahost, $ks, $entryid, $partnerid, $uiconfid,
                                                   $sort, $url, $currentcontrolid);
        echo $renderer->create_embed_code_markup();

        if ($media != null and !is_null($internalcontrol) and !is_null($defaultcontrol)) {
                echo $renderer->create_access_control_markup($defaultcontrol, $internalcontrol, $currentcontrolid);
        }

        echo $renderer->create_access_back_markup($url);
    }
}

echo $OUTPUT->footer();

