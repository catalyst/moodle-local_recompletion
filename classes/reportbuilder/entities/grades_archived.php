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

namespace local_recompletion\reportbuilder\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use lang_string;

/**
 * Report builder entity for grade archived records.
 *
 * @package    local_recompletion
 * @author     Rossco Hellmans <rosscohellmans@catalyst-au.net>
 * @copyright  Catalyst IT, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grades_archived extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[] Array of tables
     */
    protected function get_default_tables(): array {
        return [
            'grade_grades_history',
            'local_recompletion_grade_archived',
            'grade_items',
            'user',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity:local_recompletion_grade_archived', 'local_recompletion');
    }

    /**
     * Initialise.
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        return $this;
    }

    /**
     * Returns list of available columns.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        global $DB;

        $tablealias = $this->get_table_alias('grade_grades_history');
        $archivedalias = $this->get_table_alias('local_recompletion_grade_archived');
        $itemalias = $this->get_table_alias('grade_items');
        $useralias = $this->get_table_alias('user');

        // Add mandatory join to archived table.
        $this->add_join(
            "JOIN {local_recompletion_grade_archived} $archivedalias ON $archivedalias.gradehistid = {$tablealias}.id"
        );

        $columns[] = (new column(
            'item',
            new lang_string('item', 'grades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {grade_items} {$itemalias} ON {$itemalias}.id = {$tablealias}.itemid")
            ->set_type(column::TYPE_INTEGER)
            ->add_fields("{$tablealias}.itemid, {$itemalias}.itemname, {$itemalias}.itemtype")
            ->set_is_sortable(true)
            ->set_callback(static function (int $value, object $row) {
                if ($row->itemname) {
                    return $row->itemname;
                }
                return match ($row->itemtype) {
                    'course' =>  get_string('course'),
                    'category' => get_string('category'),
                    default => $row->itemid
                };
            });

        $columns[] = (new column(
            'finalgrade',
            new lang_string('finalgrade', 'grades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$tablealias}.finalgrade")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'rawgrade',
            new lang_string('report:rawgrade', 'local_recompletion'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$tablealias}.rawgrade")
            ->set_is_sortable(true);

        $userfieldsapi = \core_user\fields::for_name();
        $allnames = $userfieldsapi->get_sql($useralias, false, '', '', false)->selects;
        $columns[] = (new column(
            'usermodified',
            new lang_string('report:modifiedby', 'local_recompletion'),
            $this->get_entity_name()
        ))
            ->add_join("LEFT JOIN {user} {$useralias} ON {$useralias}.id = {$tablealias}.usermodified")
            ->set_type(column::TYPE_INTEGER)
            ->add_fields("{$useralias}.id, $allnames, {$tablealias}.usermodified")
            ->set_is_sortable(true)
            ->add_callback(static function ($value, object $row): string {
                if (!$row) {
                    return '';
                }
                if (!$row->id) {
                    return $row->usermodified ?? '';
                }
                $viewfullnames = has_capability('moodle/site:viewfullnames', \core\context\system::instance());
                return \core\output\html_writer::link(
                    new \core\url('/user/profile.php', ['id' => $row->id]),
                    fullname($row, $viewfullnames)
                );
            });

        $columns[] = (new column(
            'locktime',
            new lang_string('locked', 'grades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.locktime")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        $columns[] = (new column(
            'overridden',
            new lang_string('overridden', 'grades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.overridden")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timemodified")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        $columns[] = (new column(
            'timearchived',
            new lang_string('report:timearchived', 'local_recompletion'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$archivedalias}.timearchived")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        return $columns;
    }
}
