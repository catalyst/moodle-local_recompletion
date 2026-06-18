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

declare(strict_types=1);

namespace local_recompletion\reportbuilder\entities;

/**
 * Recompletion entity helper class.
 *
 * @package    local_recompletion
 * @author     Rossco Hellmans <rosscohellmans@catalyst-au.net>
 * @copyright  Catalyst IT, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Callback to get module name as a link
     *
     * @param mixed $value the field value
     * @param object $row the row data containing instanceid and courseid
     * @param string $module the module name
     * @return string
     */
    public static function get_module_name(mixed $value, object $row, string $module) {
        global $PAGE;

        $renderer = new \core\output\core_renderer($PAGE, RENDERER_TARGET_GENERAL);
        $modinfo = get_fast_modinfo($row->courseid);

        if (
            !empty($modinfo) &&
            !empty($modinfo->get_instances_of($module) &&
            !empty($modinfo->get_instances_of($module)[$row->instanceid]))
        ) {
            $cm = $modinfo->get_instances_of($module)[$row->instanceid];
            $modulename = get_string('modulename', $cm->modname);
            $activityicon = $renderer->pix_icon('monologo', $modulename, $cm->modname, ['class' => 'icon']);

            return $activityicon . \core\output\html_writer::link($cm->url, format_string($cm->name), []);
        } else {
            return (string) $row->instanceid;
        }
    }

    /**
     * Returns availiable course modules of a given module as an option array keyed by instanceid
     *
     * @param integer $courseid
     * @param string|null $module the module type i.e. 'assign'
     * @return array
     */
    public static function get_available_cm_instances(int $courseid, string|null $module): array {
        $options = [];
        $modinfo = get_fast_modinfo($courseid);
        $context = \core\context\course::instance($courseid);
        if (!empty($modinfo)) {
            $cms = $modinfo->get_instances_of($module);
            foreach ($cms as $instanceid => $cm) {
                $options[$instanceid] = format_string($cm->name, true, ['context' => $context]);
            }
        }
        return $options;
    }

    /**
     * Returns all course modules as an option array keyed by cmid
     *
     * @param integer $courseid
     * @return array
     */
    public static function get_available_cms(int $courseid): array {
        $options = [];
        $modinfo = get_fast_modinfo($courseid);
        $context = \core\context\course::instance($courseid);
        if (!empty($modinfo)) {
            $cminstances = $modinfo->get_instances();
            foreach ($cminstances as $cms) {
                foreach ($cms as $cm) {
                    $options[$cm->id] = format_string($cm->name, true, ['context' => $context]);
                }
            }
        }
        return $options;
    }

    /**
     * Callback to get the time period as a readable string
     *
     * @param mixed $value the field value
     * @param object $row the row data containing the timearchived and prevtimearchived
     * @return string
     */
    public static function get_time_period(mixed $value, object $row) {
        $format = get_string('strftimedate', 'langconfig');
        $start = !empty($row->prevtimearchived) ?
            userdate($row->prevtimearchived, $format) :
            get_string('report:enrolstart', 'local_recompletion');
        $end = userdate($row->timearchived, $format);
        return get_string('report:timeperiodstr', 'local_recompletion', ['start' => $start, 'end' => $end]);
    }
}
