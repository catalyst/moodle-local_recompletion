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
 * Report builder entity for mod_certificate archived records.
 *
 * @package    local_recompletion
 * @author     Rossco Hellmans <rosscohellmans@catalyst-au.net>
 * @copyright  Catalyst IT, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class archived extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[] Array of tables
     */
    protected function get_default_tables(): array {
        return [
            'local_recompletion_archived',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity:local_recompletion_archived', 'local_recompletion');
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
        $tablealias = $this->get_table_alias('local_recompletion_archived');

        $columns[] = (new column(
            'timearchived',
            new lang_string('report:timearchived', 'local_recompletion'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timearchived")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        $subsql = "(SELECT MAX(timearchived)
                      FROM {local_recompletion_archived}
                     WHERE userid = {$tablealias}.userid
                       AND courseid = {$tablealias}.courseid
                       AND timearchived < {$tablealias}.timearchived)";
        $columns[] = (new column(
            'completionperiod',
            new lang_string('report:completionperiod', 'local_recompletion'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timearchived")
            ->add_field($subsql, 'prevtimearchived')
            ->add_callback([helper::class, 'get_time_period']);

        return $columns;
    }
}
