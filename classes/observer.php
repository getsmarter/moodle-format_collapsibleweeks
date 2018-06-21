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
 * Cloned form core weeks format + a new function handling user_loggedout event.
 *
 * @package    format_collapsibleweeks
 * @copyright  2018 - Cellule TICE - Unversite de Namur
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/format/lib.php');

/**
 * Events observed by collapsibleweeks format.
 */
class format_collapsibleweeks_observer {
    /**
     * Observe user_loggedout event in order to delete weeks collapse state for logging out user if necessary.
     *
     * @param \core\event\base $event
     * @throws coding_exception
     */
    public static function user_loggedout(core\event\base $event) {
        global $DB;
        if (!get_config('format_collapsibleweeks', 'keepstateoversession')) {
            $eventdata = $event->get_data();
            $courses = enrol_get_all_users_courses($eventdata['userid']);
            foreach ($courses as $course) {
                if (course_get_format($course->id)->get_format() == 'collapsibleweeks') {
                    $DB->delete_records_select('user_preferences', $DB->sql_like('name', ':name') . ' AND userid=:userid ',
                        array( 'name' => 'sections-toggle-' . $course->id, 'userid' => $eventdata['userid']));
                }
            }
        }
    }

    /**
     * Triggered via \core\event\course_updated event.
     *
     * @param \core\event\course_updated $event
     */
    public static function course_updated(\core\event\course_updated $event) {
        if (class_exists('format_weeks', false)) {
            // If class format_weeks was never loaded, this is definitely not a course in 'weeks' format.
            // Course may still be in another format but format_weeks::update_end_date() will check it.
            format_weeks::update_end_date($event->courseid);
        }
    }

    /**
     * Triggered via \core\event\course_section_created event.
     *
     * @param \core\event\course_section_created $event
     */
    public static function course_section_created(\core\event\course_section_created $event) {
        if (class_exists('format_weeks', false)) {
            // If class format_weeks was never loaded, this is definitely not a course in 'weeks' format.
            // Course may still be in another format but format_weeks::update_end_date() will check it.
            format_weeks::update_end_date($event->courseid);
        }
    }

    /**
     * Triggered via \core\event\course_section_deleted event.
     *
     * @param \core\event\course_section_deleted $event
     */
    public static function course_section_deleted(\core\event\course_section_deleted $event) {
        if (class_exists('format_weeks', false)) {
            // If class format_weeks was never loaded, this is definitely not a course in 'weeks' format.
            // Course may still be in another format but format_weeks::update_end_date() will check it.
            format_weeks::update_end_date($event->courseid);
        }
    }
}
