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

namespace local_courseheatmappro\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_courseheatmappro.
 *
 * @package   local_courseheatmappro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <support@kaviratech.com> <moodle@kaviratech.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the stored metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_courseheatmappro_exports', [
            'userid' => 'privacy:metadata:local_courseheatmappro_exports:userid',
            'courseid' => 'privacy:metadata:local_courseheatmappro_exports:courseid',
            'exporttype' => 'privacy:metadata:local_courseheatmappro_exports:exporttype',
            'filename' => 'privacy:metadata:local_courseheatmappro_exports:filename',
            'filtersjson' => 'privacy:metadata:local_courseheatmappro_exports:filtersjson',
            'timecreated' => 'privacy:metadata:local_courseheatmappro_exports:timecreated',
        ], 'privacy:metadata:local_courseheatmappro_exports');

        return $collection;
    }

    /**
     * Get contexts containing user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        if ($DB->record_exists('local_courseheatmappro_exports', ['userid' => $userid])) {
            $contextlist->add_context(context_system::instance()->id);
        }

        return $contextlist;
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $systemcontext = context_system::instance();
        if (!in_array($systemcontext->id, $contextlist->get_contextids(), true)) {
            return;
        }

        $userid = (int)$contextlist->get_user()->id;
        $records = $DB->get_records('local_courseheatmappro_exports', ['userid' => $userid], 'timecreated ASC, id ASC');
        if (empty($records)) {
            return;
        }

        $export = [];
        foreach ($records as $record) {
            $export[] = (object)[
                'courseid' => (int)$record->courseid,
                'exporttype' => (string)$record->exporttype,
                'filename' => (string)$record->filename,
                'filters' => json_decode((string)$record->filtersjson, true) ?: [],
                'timecreated' => \core_privacy\local\request\transform::datetime((int)$record->timecreated),
            ];
        }

        writer::with_context($systemcontext)->export_data(
            [get_string('pluginname', 'local_courseheatmappro'), get_string('exporthistory', 'local_courseheatmappro')],
            (object)['items' => $export]
        );
    }

    /**
     * Delete all user data within one context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $DB->delete_records('local_courseheatmappro_exports');
    }

    /**
     * Delete user data for one user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $DB->delete_records('local_courseheatmappro_exports', ['userid' => (int)$contextlist->get_user()->id]);
    }

    /**
     * Get users in a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $userids = $DB->get_fieldset_select(
            'local_courseheatmappro_exports',
            'DISTINCT userid',
            'userid IS NOT NULL AND userid > 0'
        );
        $userlist->add_users($userids);
    }

    /**
     * Delete user data for selected users.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_courseheatmappro_exports', "userid {$insql}", $params);
    }
}
