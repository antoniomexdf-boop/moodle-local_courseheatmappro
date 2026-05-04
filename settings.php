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
 * Admin entry for local_courseheatmappro.
 *
 * This file intentionally adds only a navigation entry and no settings page.
 *
 * @package   local_courseheatmappro
 * @copyright 2026 Jesus Antonio Jimenez Aviña <support@kaviratech.com> <moodle@kaviratech.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_courseheatmappro',
        get_string('pluginname', 'local_courseheatmappro'),
        new moodle_url('/local/courseheatmappro/index.php'),
        'local/courseheatmappro:view'
    ));
}
