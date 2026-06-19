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

namespace local_recompletion\reportbuilder\local\systemreports;

use core\lang_string;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\system_report;
use local_recompletion\reportbuilder\entities\archived;
use local_recompletion\reportbuilder\entities\helper;
use local_recompletion\reportbuilder\entities\lesson_attempts;
use local_recompletion\reportbuilder\local\filters\user as user_filter;

/**
 * Archived lesson attempts report class implementation.
 *
 * @package    local_recompletion
 * @author     Rossco Hellmans <rosscohellmans@catalyst-au.net>
 * @copyright  Catalyst IT, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class archived_lesson_attempts extends system_report {
    /**
     * If this report should be visible as an option.
     *
     * @return bool
     */
    public static function report_visisble(): bool {
        $pluginman = \core\plugin_manager::instance();
        $enabled = $pluginman->get_enabled_plugins('mod');
        return array_key_exists('lesson', $enabled);
    }

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        $laentitiy = new lesson_attempts();
        $tablealias = $laentitiy->get_table_alias('local_recompletion_la');
        $this->add_entity($laentitiy);

        $this->set_main_table('local_recompletion_la', $tablealias);

        // Join the user entity.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_join("JOIN {user} {$useralias} ON {$useralias}.id = {$tablealias}.userid"));

        // Join the archived entity.
        $archivedentity = new archived();
        $archivedalias = $archivedentity->get_table_alias('local_recompletion_archived');
        $join = "JOIN {local_recompletion_archived} {$archivedalias}
                   ON {$archivedalias}.userid = {$tablealias}.userid
                  AND {$archivedalias}.courseid = {$tablealias}.course
                  AND {$archivedalias}.timearchived = {$tablealias}.timearchived";
        $this->add_entity($archivedentity
            ->add_join($join));

        // Now we can call our helper methods to add the content we want to include in the report.
        $this->add_columns();
        $this->add_filters();

        // Add course id clause.
        $courseid = $this->get_parameter('courseid', 0, \core\param::INT->value);
        if ($courseid) {
            $paramcourseid = database::generate_param_name('courseid');
            $this->add_base_condition_sql("{$tablealias}.course = :{$paramcourseid}", [$paramcourseid => $courseid]);
        }

        // Set if report can be downloaded.
        $this->set_downloadable(true);
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        $courseid = $this->get_parameter('courseid', 0, \core\param::INT->value);
        $context = $courseid ?
            \core\context\course::instance($courseid) :
            \core\context\system::instance();
        return has_capability('local/recompletion:manage', $context);
    }

    /**
     * Adds the columns we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    public function add_columns(): void {
        $columns = [
            'user:fullnamewithlink',
            'lesson_attempts:lesson',
            'lesson_attempts:pagetitle',
            'lesson_attempts:attempt',
            'lesson_attempts:answer',
            'lesson_attempts:correct',
            'lesson_attempts:timeseen',
            'lesson_attempts:timearchived',
            'archived:completionperiod',
        ];
        $this->add_columns_from_entities($columns);

        $this->set_initial_sort_column('user:fullnamewithlink', SORT_ASC);
        $this->set_default_no_results_notice(new lang_string('noarchivedrecordsfound', 'local_recompletion'));
    }

    /**
     * Adds the filters we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    protected function add_filters(): void {
        $courseid = $this->get_parameter('courseid', 0, \core\param::INT->value);
        $userentity = $this->get_entity('user');
        $useralias = $userentity->get_table_alias('user');
        $this->add_filter((new filter(
            user_filter::class,
            'userselect',
            new lang_string('userselect', 'core_reportbuilder'),
            $userentity->get_entity_name(),
            "{$useralias}.id",
            ['courseid' => $courseid]
        ))
            ->add_joins($this->get_joins()));

        $laentity = $this->get_entity('lesson_attempts');
        $tablealias = $laentity->get_table_alias('local_recompletion_la');
        $this->add_filter((new filter(
            select::class,
            'lessonid',
            new lang_string('pluginname', 'lesson'),
            $laentity->get_entity_name(),
            "{$tablealias}.lessonid"
        ))
            ->add_joins($this->get_joins())
            ->set_options(helper::get_available_cm_instances($courseid, 'lesson')));
    }
}
