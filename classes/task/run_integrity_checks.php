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

namespace local_ai_course_assistant\task;

use local_ai_course_assistant\integrity_checker;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task: run daily integrity checks.
 *
 * @package    local_ai_course_assistant
 * @copyright  2025 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_integrity_checks extends \core\task\scheduled_task {

    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:run_integrity_checks', 'local_ai_course_assistant');
    }

    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        if (!get_config('local_ai_course_assistant', 'integrity_enabled')) {
            mtrace('local_ai_course_assistant: Integrity monitoring disabled; skipping daily checks.');
            return;
        }

        $results = integrity_checker::run_all(true);
        mtrace('local_ai_course_assistant: Integrity checks complete. '
            . 'status=' . ($results['overall_status'] ?? 'unknown')
            . ', passed=' . (int)($results['passed'] ?? 0)
            . ', failed=' . (int)($results['failed'] ?? 0)
            . ', warnings=' . (int)($results['warnings'] ?? 0));
    }
}
