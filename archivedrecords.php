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
 * Archived records report.
 *
 * @package    local_recompletion
 * @author     Rossco Hellmans <rosscohellmans@catalyst-au.net>
 * @copyright  Catalyst IT, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_recompletion\reportbuilder\helper;

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$selectedreport = optional_param('report', helper::MAIN_REPORT_PAGE, PARAM_TEXT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$context = core\context\course::instance($course->id);
require_capability('local/recompletion:manage', $context);

$currenturl = new core\url('/local/recompletion/archivedrecords.php', ['id' => $course->id, 'report' => $selectedreport]);

$pagetitle = get_string('archivedrecords', 'local_recompletion');
$PAGE->set_url($currenturl);
$PAGE->set_context($context);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

$reportnamespace = 'local_recompletion\reportbuilder\local\systemreports\\';
$reports = helper::get_available_reports();

$url = clone($currenturl);
$options = [];
foreach ($reports as $type => $name) {
    $url->param('report', $type);
    $options[$url->out(false)] = $name;
}

// Are we bulk downloading selected users reports?
$downloadformat = optional_param('bulkdownloadformat', '', core\param::ALPHANUM->value);
if ($selectedreport === helper::MAIN_REPORT_PAGE && $downloadformat && sesskey()) {
    $selectedusers = required_param('selectedusers', core\param::TEXT->value);
    $selectedusers = $selectedusers !== '0' ? explode(',', $selectedusers) : 0;
    $timearchivedconditions = required_param('timearchivedconditions', core\param::TEXT->value);
    $timearchivedparams = required_param('timearchivedparams', core\param::TEXT->value);
    $timearchivedparams = (array) json_decode($timearchivedparams);
    $params = [
        'courseid' => $course->id,
        'userids' => $selectedusers,
        'includefilters' => false,
        'timearchivedconditions' => $timearchivedconditions,
        'timearchivedparams' => $timearchivedparams,
    ];

    helper::bulk_download_reports($reports, $downloadformat, $course->id, $params);
}

echo $OUTPUT->header();

$selectmenu = new core\output\select_menu('reporttype', $options, $currenturl->out(false));
$selectmenu->set_label(get_string('report'), ['class' => 'sr-only']);
$tertiarynav = core\output\html_writer::tag(
    'div',
    $OUTPUT->render_from_template('core/tertiary_navigation_selector', $selectmenu->export_for_template($OUTPUT)),
    ['class' => 'navitem']
);
echo core\output\html_writer::div(
    $tertiarynav,
    'tertiary-navigation full-width-bottom-border ms-0 d-flex',
    ['id' => 'tertiary-navigation']
);

if ($selectedreport === helper::MAIN_REPORT_PAGE) {
    // Remove archived_user_records as a 'report'.
    unset($reports[helper::MAIN_REPORT_PAGE]);

    // Add filters form.
    $filterform = new local_recompletion\archived_records_filter_form(
        $currenturl,
        ['courseid' => $course->id],
        attributes: ['class' => 'mform full-width-labels']
    );

    // Only show the reports after users have been selected.
    $data = $filterform->get_data();
    if ($data) {
        $operator = (int) ($data->selectedusers_operator ?? local_recompletion\reportbuilder\local\filters\user::USER_SELECT);
        $selectedusers = $operator === local_recompletion\reportbuilder\local\filters\user::USER_SELECT ?
            $data->selectedusers_value : 0;
        $timearchivedfilter = helper::get_timearchived_filter();
        [$timearchivedconditions, $timearchivedparams] = $timearchivedfilter->get_sql_filter((array) $data);
        $params = [
            'courseid' => $course->id,
            'userids' => $selectedusers,
            'includefilters' => false,
            'timearchivedconditions' => $timearchivedconditions,
            'timearchivedparams' => $timearchivedparams,
        ];

        // First display the filters and download form.
        echo core\output\html_writer::start_div('d-flex align-items-end');
        echo core\output\html_writer::start_div('filter-form', ['style' => 'flex: 1;']);
        $filterform->display();
        echo core\output\html_writer::end_div();
        $downloadform = $OUTPUT->download_dataformat_selector(
            get_string('report:bulkdownload_user_records', 'local_recompletion'),
            new core\url('/local/recompletion/archivedrecords.php'),
            'bulkdownloadformat',
            [
                'id' => $course->id,
                'report' => 'archived_user_records',
                'selectedusers' => is_array($selectedusers) ? implode(',', $selectedusers) : $selectedusers,
                'timearchivedconditions' => $params['timearchivedconditions'],
                'timearchivedparams' => json_encode($params['timearchivedparams']),
            ]
        );
        echo core\output\html_writer::div($downloadform, 'mb-3');
        echo core\output\html_writer::end_div();

        foreach ($reports as $type => $name) {
            $reportclass = helper::get_report_class($type);
            $report = core_reportbuilder\system_report_factory::create(
                $reportclass,
                $context,
                parameters: $params
            );

            // Don't show the filters and download options per report, we have custom ones
            // for the entire page to filter and bulk export all reports.
            $report->set_downloadable(false);
            $report->set_filter_form_default(false);

            $html = '';
            $reportid = 'report_' . $type;
            $headerhtml = core\output\html_writer::tag(
                'span',
                $OUTPUT->pix_icon('t/expandedchevron', get_string('collapse')),
                ['class' => 'expanded-icon']
            );
            $headerhtml .= core\output\html_writer::tag(
                'span',
                $OUTPUT->pix_icon('t/collapsedchevron', get_string('expand')),
                ['class' => 'collapsed-icon']
            );
            $headerhtml .= core\output\html_writer::tag('h3', $name, ['class' => 'm-0']);
            $html .= core\output\html_writer::tag(
                'a',
                $headerhtml,
                [
                    'class' => 'btn icons-collapse-expand mt-3 justify-content-start',
                    'data-toggle' => 'collapse',
                    'data-target' => '#' . $reportid,
                    'aria-expanded' => 'true',
                    'aria-controls' => $reportid,
                    'href' => '#',
                ]
            );
            $html .= core\output\html_writer::div(
                $report->output(),
                'collapse show',
                ['id' => $reportid]
            );
            echo $html;
        }
    } else {
        // We aren't showing the reports and download options yet but we still want to show the filters.
        $filterform->display();
    }
} else {
    $reportclass = helper::get_report_class($selectedreport);
    $report = core_reportbuilder\system_report_factory::create(
        $reportclass,
        $context,
        parameters: ['courseid' => $course->id]
    );
    echo $report->output();
}

echo $OUTPUT->footer();
