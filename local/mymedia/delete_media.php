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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');
$entryid = required_param('entryid', PARAM_TEXT);
$confirm = required_param('confirm', PARAM_TEXT);
$page = required_param('page', PARAM_INT);
$sort = optional_param('sort', 'recent', PARAM_TEXT);

require_login();
global $USER, $SESSION, $DB;

$mymedia = get_string('heading_mymedia', 'local_mymedia');
$PAGE->set_context(context_system::instance());
$header  = format_string($SITE->shortname).": $mymedia";

$PAGE->set_url('/local/mymedia/delete_media.php');
$PAGE->set_course($SITE);

$PAGE->set_pagetype('mymedia-index');
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title($header);
$PAGE->set_heading($header);
$PAGE->add_body_class('mymedia-index');
$PAGE->requires->js('/local/kaltura/js/jquery.js', true);
$PAGE->requires->css('/local/mymedia/css/mymedia.css');

$kaltura = new kaltura_connection();
$connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);
$context = context_user::instance($USER->id);

if (!$connection) {
    $url = new moodle_url('/admin/settings.php', array('section' => 'local_kaltura'));
    print_error('conn_failed', 'local_kaltura', $url);
}

$loginsession = '';

echo $OUTPUT->header();

echo html_writer::start_tag('h3');
echo get_string('delete_media_title', 'local_mymedia');
echo html_writer::end_tag('h3');

$renderer = $PAGE->get_renderer('local_mymedia');

$media = $connection->media->get($entryid);

$mymediaurl = new moodle_url('/local/mymedia/mymedia.php',
                             array('page' => $page, 'sort' => $sort));

if ($media == null or $media->status == KalturaEntryStatus::DELETED) {
    echo $renderer->create_delete_message_markup(get_string('delete_media_not_exist', 'local_mymedia'),
                                                 $mymediaurl);
} else if ($media->userId != $USER->username) {
    echo $renderer->create_delete_message_markup(get_string('delete_media_failed_not_owner', 'local_mymedia'),
                                                 $mymediaurl);
} else {
    echo $renderer->create_media_details_table_markup($media);

    $flag = true;

    $flavorassetarray = $connection->flavorAsset->getByEntryId($media->id);

    foreach ($flavorassetarray as $flavor) {
        if ($flavor->status != KalturaFlavorAssetStatus::ERROR and
            $flavor->status != KalturaFlavorAssetStatus::READY and
            $flavor->status != KalturaFlavorAssetStatus::NOT_APPLICABLE and
            $flavor->status != KalturaFlavorAssetStatus::DELETED and
            $flavor->status != KalturaFlavorAssetStatus::TEMP) {
            $flag = false;
        }
    }

    if ($flag == false) {
        echo $renderer->create_delete_message_markup(get_string('media_converting', 'local_mymedia'),
                                                     $mymediaurl);
    } else {
        echo $renderer->create_entry_used_table($entryid, $mymediaurl, $flag);

        if ($flag == true) {
            if (strcmp($confirm , 'yet') == 0) {
                echo $renderer->create_delete_confirm_markup($entryid, $page, $sort, $mymediaurl);
            } else if (strcmp($confirm, 'Drop') == 0) {
                $flavorassetarray = $connection->flavorAsset->getByEntryId($entryid);
                foreach ($flavorassetarray as $flavor) {
                    $connection->flavorAsset->delete($flavor->id);
                }

                $thumbnailarray = $connection->thumbAsset->getByEntryId($entryid);

                foreach ($thumbnailarray as $thumbnail) {
                     $connection->thumbAsset->delete($thumbnail->id);
                }

                $connection->baseEntry->delete($entryid);

                echo $renderer->create_delete_message_markup(get_string('delete_media_complete', 'local_mymedia'),
                                                             $mymediaurl);
            } else {
                echo $renderer->create_delete_back_button_markup($mymediaurl);
            }
        }
    }
}

$connection->session->end();

echo $OUTPUT->footer();
