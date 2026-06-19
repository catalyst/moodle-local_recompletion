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
use user_add_filter_form;

/**
 * Report builder entity for questionnaire response archived records.
 *
 * @package    local_recompletion
 * @author     Rossco Hellmans <rosscohellmans@catalyst-au.net>
 * @copyright  Catalyst IT, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionnaire_responses extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[] Array of tables
     */
    protected function get_default_tables(): array {
        return [
            'local_recompletion_qr',
            'local_recompletion_qr_bool',
            'local_recompletion_qr_date',
            'local_recompletion_qr_m',
            'local_recompletion_qr_other',
            'local_recompletion_qr_rank',
            'local_recompletion_qr_single',
            'local_recompletion_qr_text',
            'questionnaire_question',
            'questionnaire_quest_choice',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity:local_recompletion_qr', 'local_recompletion');
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

        $tablealias = $this->get_table_alias('local_recompletion_qr');

        $unionalias = 'unionresponses';
        $questionalias = $this->get_table_alias('questionnaire_question');
        $coalescechoice = "COALESCE(" . $DB->sql_cast_to_char('content') . " , " . $DB->sql_cast_to_char('choice_id') . ")";
        $multichoice = $DB->sql_group_concat($coalescechoice, ',');
        $multirank = $DB->sql_group_concat('rankvalue', ',');
        $responsesql = "(SELECT response_id, question_id, choice_id AS response, '' AS otherdata, 'bool' AS responsetype
                           FROM {local_recompletion_qr_bool}
                          UNION
                         SELECT response_id, question_id, response, '' AS otherdata, 'date' AS responsetype
                           FROM {local_recompletion_qr_date}
                          UNION
                         SELECT response_id, m.question_id, {$multichoice} AS response, '' AS otherdata, 'multiple' AS responsetype
                           FROM {local_recompletion_qr_m} m
                      LEFT JOIN {questionnaire_quest_choice} c ON c.id = m.choice_id
                       GROUP BY response_id, m.question_id
                          UNION
                         SELECT response_id, question_id, response, '' AS otherdata, 'other' AS responsetype
                           FROM {local_recompletion_qr_other}
                          UNION
                         SELECT response_id, r.question_id, {$multirank} AS response, {$multichoice} AS otherdata,
                                'rank' AS responsetype
                           FROM {local_recompletion_qr_rank} r
                      LEFT JOIN {questionnaire_quest_choice} c ON c.id = r.choice_id
                       GROUP BY response_id, r.question_id
                          UNION
                         SELECT response_id, s.question_id, {$coalescechoice} AS response, '' AS otherdata, 'single' AS responsetype
                           FROM {local_recompletion_qr_single} s
                      LEFT JOIN {questionnaire_quest_choice} c ON c.id = s.choice_id
                          UNION
                         SELECT response_id, question_id, response, '' AS otherdata, 'text' AS responsetype
                           FROM {local_recompletion_qr_text})";
        $this->add_join("JOIN {$responsesql} AS {$unionalias}
                           ON {$unionalias}.response_id = {$tablealias}.originalresponseid");
        $this->add_join("LEFT JOIN {questionnaire_question} {$questionalias}
                                ON {$questionalias}.id = {$unionalias}.question_id");

        $columns[] = (new column(
            'questionnaire',
            new lang_string('pluginname', 'questionnaire'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$tablealias}.questionnaireid", 'instanceid')
            ->add_field("{$tablealias}.course", 'courseid')
            ->set_is_sortable(true)
            ->add_callback([helper::class, 'get_module_name'], 'questionnaire');

        $columns[] = (new column(
            'responseid',
            new lang_string('report:responseid', 'local_recompletion'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$unionalias}.response_id")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'question',
            new lang_string('report:question', 'local_recompletion'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$questionalias}.content, {$unionalias}.question_id")
            ->set_is_sortable(true)
            ->add_callback(static function (string $value, object $row) {
                return $value ?? $row->question_id;
            });

        $columns[] = (new column(
            'response',
            new lang_string('response', 'questionnaire'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$unionalias}.response, {$unionalias}.otherdata, {$unionalias}.responsetype")
            ->add_callback(static function (string $value, object $row) {
                switch ($row->responsetype) {
                    case 'bool':
                        return $value == 'y' ?
                            get_string('yes') :
                            get_string('no');
                    case 'multiple':
                        $responses = explode(',', $value);
                        return implode('<br>', $responses);
                    case 'rank':
                        $choices = explode(',', $row->otherdata);
                        $ranks = explode(',', $value);
                        foreach ($choices as $index => &$choice) {
                            $rank = $ranks[$index] ?? '';
                            $choice = "{$choice}: {$rank}";
                        }
                        return implode('<br>', $choices);
                    default:
                        return $value;
                }
            });

        $columns[] = (new column(
            'status',
            new lang_string('report:status', 'local_recompletion'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$tablealias}.complete, {$tablealias}.submitted")
            ->set_is_sortable(true)
            ->add_callback(static function (string $value, object $row) {
                $submitted = ' ' . userdate($row->submitted);
                return $value == 'y' ?
                    get_string('submitted', 'questionnaire') . $submitted :
                    get_string('attemptstillinprogress', 'questionnaire') . $submitted;
            });

        $columns[] = (new column(
            'grade',
            new lang_string('grade', 'questionnaire'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$tablealias}.grade")
            ->set_is_sortable(true);

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
