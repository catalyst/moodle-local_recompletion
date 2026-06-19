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
 * Report builder entity for tool_certificate_issues archived records.
 *
 * @package    local_recompletion
 * @author     Rossco Hellmans <rosscohellmans@catalyst-au.net>
 * @copyright  Catalyst IT, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_certificate_issues extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[] Array of tables
     */
    protected function get_default_tables(): array {
        return [
            'local_recompletion_tci_archived',
            'tool_certificate_issues',
            'tool_certificate_templates',
            'coursecertificate',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity:local_recompletion_tci_archived', 'local_recompletion');
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
        $tablealias = $this->get_table_alias('tool_certificate_issues');
        $archivedalias = $this->get_table_alias('local_recompletion_tci_archived');
        $tempaltesalias = $this->get_table_alias('tool_certificate_templates');
        $coursecertalias = $this->get_table_alias('coursecertificate');

        // Add the required joins.
        $tciarchivedjoin = "JOIN {local_recompletion_tci_archived} {$archivedalias}
                             ON {$archivedalias}.certissueid = {$tablealias}.id";
        $templatejoin = "LEFT JOIN {tool_certificate_templates} {$tempaltesalias}
                                ON {$tempaltesalias}.id = {$tablealias}.templateid";
        $this->add_joins([$tciarchivedjoin, $templatejoin]);

        $coursecertificatejoin = "LEFT JOIN {coursecertificate} {$coursecertalias}
                                         ON {$coursecertalias}.course = {$tablealias}.courseid
                                        AND {$coursecertalias}.template = {$tablealias}.templateid
                                        AND {$tablealias}.component = 'coursecertificate'";
        $columns[] = (new column(
            'coursecertificate',
            new lang_string('pluginname', 'coursecertificate'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($coursecertificatejoin)
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$coursecertalias}.id", 'instanceid')
            ->add_field("{$coursecertalias}.course", 'courseid')
            ->set_is_sortable(true)
            ->add_callback([helper::class, 'get_module_name'], 'coursecertificate');

        $columns[] = (new column(
            'templatename',
            new lang_string('template', 'tool_certificate'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tempaltesalias}.name");

        $columns[] = (new column(
            'code',
            new lang_string('code', 'tool_certificate'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.code");

        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        $columns[] = (new column(
            'expirydate',
            new lang_string('expirydate', 'tool_certificate'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.expires")
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
