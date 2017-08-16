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
 * Kaltura my media library script
 *
 * @package    local
 * @subpackage mymedia
 * @copyright  2016-2017 Yamaguchi University <info-cc@ml.cc.yamaguchi-u.ac.jp>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');

if (!defined('MOODLE_INTERNAL')) {
    // It must be included from a Moodle page.
    die('Direct access to this script is forbidden.');
}

define('MYMEDIA_ITEMS_PER_PAGE', '9');

/**
 * This function adds my media links to the navigation block
 */
function local_mymedia_extend_navigation($navigation) {

    global $USER;

    $mymedia = get_string('nav_mymedia', 'local_mymedia');
    $upload = get_string('nav_upload', 'local_mymedia');

    $nodehome = $navigation->get('home');

    $context = context_user::instance($USER->id);

    if ($nodehome && has_capability('local/mymedia:view', $context, $USER)) {
        $nodemymedia = $nodehome->add($mymedia, new moodle_url('/local/mymedia/mymedia.php'),
                                        navigation_node::NODETYPE_LEAF, $mymedia, 'mymedia');
    }
}

/**
 * This function checks for capability across all context levels.
 *
 * @param string $capability The name of capability we are checking.
 * @return boolean true if capability found, false otherwise.
 */
function local_mymedia_check_capability($capability) {
    global $DB, $USER;
    $result = false;

    // Site admins can do anything.
    if (is_siteadmin($USER->id)) {
        $result = true;
    }

    // Look for share permissions in the USER global.
    if (!$result && isset($USER->access['rdef'])) {
        foreach ($USER->access['rdef'] as $contextelement) {
            if (isset($contextelement[$capability]) && $contextelement[$capability] == 1) {
                $result = true;
            }
        }
    }

    // Look for share permissions in the database for any context level in case it wasn't found in USER global.
    if (!$result) {
        $sql = "SELECT ra.*
                  FROM {role_assignments} ra
            INNER JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
                 WHERE ra.userid = :userid
                       AND rc.capability = :capability
                       AND rc.permission = :permission";

        $params = array(
            'userid' => $USER->id,
            'capability' => $capability,
            'permission' => CAP_ALLOW
        );

        if ($DB->record_exists_sql($sql, $params)) {
            $result = true;
        }
    }

    return $result;
}
