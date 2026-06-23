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

namespace local_recompletion\reportbuilder;

/**
 * Recompletion entity helper class.
 *
 * @package    local_recompletion
 * @author     Rossco Hellmans <rosscohellmans@catalyst-au.net>
 * @copyright  Catalyst IT, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * The key for the main report page that has all reports for a given student.
     * @var string
     */
    const MAIN_REPORT_PAGE = 'archived_user_records';

    /**
     * Returns an array of available reports.
     *
     * @return array
     */
    public static function get_available_reports() {
        $reports = [
            self::MAIN_REPORT_PAGE => get_string('report:archived_user_records', 'local_recompletion'),
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
        $reports = array_filter(
            $reports,
            function ($report) {
                if ($report == 'archived_user_records') {
                    // Not a real report, but is always available.
                    return true;
                }
                $class = self::get_report_class($report);
                return $class::report_visisble();
            },
            ARRAY_FILTER_USE_KEY
        );

        return $reports;
    }

    /**
     * Returns the full class with namespace for a given report.
     *
     * @param string $report
     * @return string
     */
    public static function get_report_class(string $report): string {
        return 'local_recompletion\reportbuilder\local\systemreports\\' . $report;
    }

    /**
     * Downloads bulk reports in a zip file
     *
     * @param array $reports an array of reports to bulk download in report class => report name format
     * @param string $downloadformat the format to export the reports
     * @param int $courseid the id of the course we are downloading reports for
     * @param array $params the params to pass to the reports
     */
    public static function bulk_download_reports(array $reports, string $downloadformat, int $courseid, array $params) {
        global $PAGE;

        // Just to be safe, remove the main report page as a 'report' to avoid accidental errors.
        unset($reports[self::MAIN_REPORT_PAGE]);

        $context = \core\context\course::instance($courseid);

        $zipfilename = 'archived_completions_' . $courseid . '_' . time() . '.zip';
        /** @var \core_files\local\archive_writer\zip_writer $zipwriter */
        $zipwriter = \core_files\archive_writer::get_file_writer($zipfilename, \core_files\archive_writer::ZIP_WRITER);
        $format = \core\dataformat::get_format_instance($downloadformat);

        foreach ($reports as $reportclass => $name) {
            $reportclass = self::get_report_class($reportclass);
            $report = \core_reportbuilder\system_report_factory::create(
                $reportclass,
                $context,
                parameters: $params
            );

            $table = \core_reportbuilder\table\system_report_table::create(
                $report->get_report_persistent()->get('id'),
                $report->get_parameters()
            );
            $table->define_baseurl($PAGE->url);
            $table->setup();
            $table->query_db(0, false);

            if (!$table->totalrows) {
                // Empty report nothing to export, continue to next report.
                continue;
            }

            // Set up table as if it were being downloaded, retrieve appropriate export class (ensure output buffer is
            // cleaned in order to instantiate export class without exception).
            ob_start();
            $table->download = $downloadformat;
            $exportclass = new \core_table\dataformat_export_format($table, $downloadformat);
            ob_end_clean();

            $filename = strtolower(str_replace(' ', '_', $name));
            $filepath = \core\dataformat::write_data(
                $filename,
                $downloadformat,
                $exportclass->format_data($table->headers),
                $table->rawdata,
                static function (object $record, bool $supportshtml) use ($table, $exportclass): array {
                    $record = array_map(fn ($value) => $value ?? '', (array) $record);
                    $rowwithkeys = $table->format_row($record);
                    $row = $table->get_row_from_keyed($rowwithkeys);
                    if (!$supportshtml) {
                        $row = $exportclass->format_data($row);
                    }
                    return $row;
                }
            );

            $zipwriter->add_file_from_filepath(basename($filepath), $filepath);
            $table->close_recordset();
        }

        // Finish the archive.
        $zipwriter->finish();
        $path = $zipwriter->get_path_to_zip();
        send_file($path, $zipfilename, 0);
    }

    /**
     * Callback to get module name as a link
     *
     * @param mixed $value the field value
     * @param object $row the row data containing instanceid and courseid
     * @param string $module the module name
     * @return string
     */
    public static function get_module_name(mixed $value, object $row, string $module) {
        global $PAGE;

        $renderer = new \core\output\core_renderer($PAGE, RENDERER_TARGET_GENERAL);
        $modinfo = get_fast_modinfo($row->courseid);

        if (
            !empty($modinfo) &&
            !empty($modinfo->get_instances_of($module) &&
            !empty($modinfo->get_instances_of($module)[$row->instanceid]))
        ) {
            $cm = $modinfo->get_instances_of($module)[$row->instanceid];
            $modulename = get_string('modulename', $cm->modname);
            $activityicon = $renderer->pix_icon('monologo', $modulename, $cm->modname, ['class' => 'icon']);

            return $activityicon . \core\output\html_writer::link($cm->url, format_string($cm->name), []);
        } else {
            return (string) $row->instanceid;
        }
    }

    /**
     * Returns availiable course modules of a given module as an option array keyed by instanceid
     *
     * @param integer $courseid
     * @param string|null $module the module type i.e. 'assign'
     * @return array
     */
    public static function get_available_cm_instances(int $courseid, string|null $module): array {
        $options = [];
        $modinfo = get_fast_modinfo($courseid);
        $context = \core\context\course::instance($courseid);
        if (!empty($modinfo)) {
            $cms = $modinfo->get_instances_of($module);
            foreach ($cms as $instanceid => $cm) {
                $options[$instanceid] = format_string($cm->name, true, ['context' => $context]);
            }
        }
        return $options;
    }

    /**
     * Returns all course modules as an option array keyed by cmid
     *
     * @param integer $courseid
     * @return array
     */
    public static function get_available_cms(int $courseid): array {
        $options = [];
        $modinfo = get_fast_modinfo($courseid);
        $context = \core\context\course::instance($courseid);
        if (!empty($modinfo)) {
            $cminstances = $modinfo->get_instances();
            foreach ($cminstances as $cms) {
                foreach ($cms as $cm) {
                    $options[$cm->id] = format_string($cm->name, true, ['context' => $context]);
                }
            }
        }
        return $options;
    }

    /**
     * Callback to get the time period as a readable string
     *
     * @param mixed $value the field value
     * @param object $row the row data containing the timearchived and prevtimearchived
     * @return string
     */
    public static function get_time_period(mixed $value, object $row) {
        $format = get_string('strftimedate', 'langconfig');
        $start = !empty($row->prevtimearchived) ?
            userdate($row->prevtimearchived, $format) :
            get_string('report:enrolstart', 'local_recompletion');
        $end = userdate($row->timearchived, $format);
        return get_string('report:timeperiodstr', 'local_recompletion', ['start' => $start, 'end' => $end]);
    }
}
