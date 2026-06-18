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
use local_recompletion\reportbuilder\entities\hvp_content_user_data;
use local_recompletion\reportbuilder\local\filters\user as user_filter;

/**
 * Archived HVP content user data report class implementation.
 *
 * @package    local_recompletion
 * @author     Rossco Hellmans <rosscohellmans@catalyst-au.net>
 * @copyright  Catalyst IT, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class archived_hvp_content_user_data extends system_report {
    /**
     * If this report should be visible as an option.
     *
     * @return bool
     */
    public static function report_visisble(): bool {
        $pluginman = \core\plugin_manager::instance();
        $enabled = $pluginman->get_enabled_plugins('mod');
        return array_key_exists('hvp', $enabled);
    }

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        $contentuserdata = new hvp_content_user_data();
        $tablealias = $contentuserdata->get_table_alias('local_recompletion_hvp');
        $this->add_entity($contentuserdata);

        $this->set_main_table('local_recompletion_hvp', $tablealias);

        // Join the user entity.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_join("JOIN {user} {$useralias} ON {$useralias}.id = {$tablealias}.user_id"));

        // Join the archived entity.
        $archivedentity = new archived();
        $archivedalias = $archivedentity->get_table_alias('local_recompletion_archived');
        $join = "JOIN {local_recompletion_archived} {$archivedalias}
                   ON {$archivedalias}.userid = {$tablealias}.user_id
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
            'hvp_content_user_data:hvp',
            'hvp_content_user_data:dataid',
            'hvp_content_user_data:data',
            'hvp_content_user_data:timearchived',
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

        $hvpentity = $this->get_entity('hvp_content_user_data');
        $tablealias = $hvpentity->get_table_alias('local_recompletion_hvp');
        $this->add_filter((new filter(
            select::class,
            'hvpid',
            new lang_string('pluginname', 'hvp'),
            $hvpentity->get_entity_name(),
            "{$tablealias}.hvp_id"
        ))
            ->add_joins($this->get_joins())
            ->set_options(helper::get_available_cm_instances($courseid, 'hvp')));
    }
}
