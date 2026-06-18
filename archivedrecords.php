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

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$selectedreport = optional_param('report', 'archived_choice_answers', PARAM_TEXT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$context = core\context\course::instance($course->id);
require_capability('local/recompletion:manage', $context);

$activeurl = new core\url('/local/recompletion/archivedrecords.php', ['courseid' => $course->id, 'report' => $selectedreport]);
$pagetitle = get_string('archivedrecords', 'local_recompletion');

$PAGE->set_url($activeurl);
$PAGE->set_context($context);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

$reportnamespace = 'local_recompletion\reportbuilder\local\systemreports\\';
$reportclass = $reportnamespace . $selectedreport;
$report = core_reportbuilder\system_report_factory::create(
    $reportclass,
    $context,
    parameters: ['courseid' => $course->id]
);

echo $OUTPUT->header();

$reports = [
    'archived_course_completions' => get_string('report:archived_course_completions', 'local_recompletion'),
    'archived_course_modules_completions' => get_string('report:archived_course_modules_completions', 'local_recompletion'),
    'archived_grades' => get_string('report:archived_grades', 'local_recompletion'),
    'archived_choice_answers' => get_string('report:archived_choice_answers', 'local_recompletion'),
    'archived_coursecertificate_issues' => get_string('report:archived_coursecertificate_issues', 'local_recompletion'),
    'archived_certificate_issues' => get_string('report:archived_certificate_issues', 'local_recompletion'),
    'archived_customcert_issues' => get_string('report:archived_customcert_issues', 'local_recompletion'),
    'archived_enrol_lti_users' => get_string('report:archived_enrol_lti_users', 'local_recompletion'),
    'archived_h5pactivity_attempts' => get_string('report:archived_h5pactivity_attempts', 'local_recompletion'),
    'archived_hotpot_attempts' => get_string('report:archived_hotpot_attempts', 'local_recompletion'),
    'archived_hvp_content_user_data' => get_string('report:archived_hvp_content_user_data', 'local_recompletion'),
    'archived_lesson_attempts' => get_string('report:archived_lesson_attempts', 'local_recompletion'),
    'archived_lesson_grades' => get_string('report:archived_lesson_grades', 'local_recompletion'),
    'archived_lesson_timers' => get_string('report:archived_lesson_timers', 'local_recompletion'),
    'archived_lesson_overrides' => get_string('report:archived_lesson_overrides', 'local_recompletion'),
    'archived_questionnaire_responses' => get_string('report:archived_questionnaire_responses', 'local_recompletion'),
    'archived_quiz_attempts' => get_string('report:archived_quiz_attempts', 'local_recompletion'),
    'archived_quiz_grades' => get_string('report:archived_quiz_grades', 'local_recompletion'),
];
$url = clone($activeurl);
$options = [];

foreach ($reports as $type => $name) {
    $class = $reportnamespace . $type;
    if (!$class::report_visisble()) {
        // Not visible, skip.
        continue;
    }
    $url->param('report', $type);
    $options[$url->out(false)] = $name;
}

$selectmenu = new core\output\select_menu('reporttype', $options, $activeurl->out(false));
$selectmenu->set_label(get_string('report'), ['class' => 'sr-only']);
echo html_writer::tag(
    'div',
    $OUTPUT->render_from_template('core/tertiary_navigation_selector', $selectmenu->export_for_template($OUTPUT)),
    ['class' => 'navitem']
);

echo $report->output();
echo $OUTPUT->footer();
