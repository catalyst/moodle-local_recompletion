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

namespace local_recompletion\reportbuilder\local\filters;

use core\context\system;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\filters\base;
use MoodleQuickForm;

/**
 * User filter
 *
 * This filter expects field SQL referring to a user ID (e.g. "{$tableuser}.id")
 *
 * @package    local_recompletion
 * @author     Rossco Hellmans <rosscohellmans@catalyst-au.net>
 * @copyright  Catalyst IT, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user extends base {
    /**
     * Setup form
     *
     * @param MoodleQuickForm $mform
     */
    public function setup_form(MoodleQuickForm $mform): void {
        global $DB;

        // Specific user selection.
        $params = $this->filter->get_field_params();
        $label = get_string('filterfieldvalue', 'core_reportbuilder', $this->get_header());
        self::add_user_select(
            $mform,
            $params['courseid'],
            "{$this->name}_value",
            $label,
            ['multiple' => true]
        );
    }

    /**
     * Return filter SQL
     *
     * @param array $values
     * @return array
     */
    public function get_sql_filter(array $values): array {
        global $DB;

        $fieldsql = $this->filter->get_field_sql();
        $params = $this->filter->get_field_params();
        $userids = $values["{$this->name}_value"] ?? [];

        if (!$userids) {
            // No selected users, do not apply this filter.
            return ['', []];
        }

        [$useridselect, $useridparams] = $DB->get_in_or_equal(
            $userids,
            SQL_PARAMS_NAMED,
            database::generate_param_name('_'),
            true,
            null,
        );

        $sql = "{$fieldsql} {$useridselect}";
        $params = array_merge($params, $useridparams);

        return [$sql, $params];
    }

    /**
     * Return sample filter values
     *
     * @return array
     */
    public function get_sample_values(): array {
        return [
            "{$this->name}_value" => [1],
        ];
    }

    /**
     * Adds the user autocomplete selector to the given form.
     *
     * @param MoodleQuickForm $mform
     * @param int $courseid the course id to limit the select options for
     * @param string $name the name to use for the field
     * @param string $label the label for the field
     * @param array $options any extra options to be applied to the field
     */
    public static function add_user_select(
        MoodleQuickForm $mform,
        int $courseid,
        string $name,
        string $label,
        array $options
    ) {
        global $DB;

        $userfieldsapi = \core_user\fields::for_name();
        $allnames = $userfieldsapi->get_sql('u', false, '', '', false)->selects;
        $sql = "SELECT DISTINCT u.id, $allnames
                  FROM {local_recompletion_archived} a
                  JOIN {user} u ON u.id = a.userid
                 WHERE a.courseid = :courseid";
        $params = ['courseid' => $courseid];
        $records = $DB->get_records_sql($sql, $params);
        $users = [];
        foreach ($records as $user) {
            $users[$user->id] = fullname($user, has_capability('moodle/site:viewfullnames', system::instance()));
        }

        $mform->addElement('autocomplete', $name, $label, $users, $options)->setHiddenLabel(true);
    }
}
