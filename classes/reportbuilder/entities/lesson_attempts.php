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
 * Report builder entity for mod_lesson attempt archived records.
 *
 * @package    local_recompletion
 * @author     Rossco Hellmans <rosscohellmans@catalyst-au.net>
 * @copyright  Catalyst IT, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lesson_attempts extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[] Array of tables
     */
    protected function get_default_tables(): array {
        return [
            'local_recompletion_la',
            'lesson_pages',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity:local_recompletion_la', 'local_recompletion');
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

        $tablealias = $this->get_table_alias('local_recompletion_la');
        $pagealias = $this->get_table_alias('lesson_pages');

        $columns[] = (new column(
            'lesson',
            new lang_string('pluginname', 'lesson'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$tablealias}.lessonid", 'instanceid')
            ->add_field("{$tablealias}.course", 'courseid')
            ->set_is_sortable(true)
            ->add_callback([helper::class, 'get_module_name'], 'lesson');

        $pagetitle = $DB->sql_cast_to_char("{$pagealias}.title");
        $pageid = $DB->sql_cast_to_char("{$tablealias}.pageid");
        $columns[] = (new column(
            'pagetitle',
            new lang_string('report:pagetitle', 'local_recompletion'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {lesson_pages} {$pagealias} ON {$pagealias}.id = {$tablealias}.pageid")
            ->set_type(column::TYPE_TEXT)
            ->add_field("COALESCE({$pagetitle}, {$pageid})", 'pagetitle');

        $columns[] = (new column(
            'attempt',
            new lang_string('report:attempt', 'local_recompletion'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$tablealias}.retry");

        $columns[] = (new column(
            'answer',
            new lang_string('answer', 'lesson'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.useranswer");

        $columns[] = (new column(
            'correct',
            new lang_string('correctresponse', 'lesson'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$tablealias}.correct")
            ->add_callback(static fn(int $value) => $value ? get_string('yes') : get_string('no'));

        $columns[] = (new column(
            'timeseen',
            new lang_string('report:timeseen', 'local_recompletion'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timearchived")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

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

        return $columns;
    }
}
