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
 * Integrity monitoring admin page.
 *
 * @package    local_ai_course_assistant
 * @copyright  2025 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_ai_course_assistant\integrity_checker;

$syscontext = context_system::instance();
require_login();
require_capability('moodle/site:config', $syscontext);

$pageurl = new moodle_url('/local/ai_course_assistant/integrity_admin.php');
$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_ai_course_assistant']);

$PAGE->set_url($pageurl);
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('integrity:title', 'local_ai_course_assistant'));
$PAGE->set_heading(get_string('integrity:title', 'local_ai_course_assistant'));
$PAGE->set_pagelayout('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = optional_param('action', '', PARAM_ALPHA);
    if ($action === 'run') {
        integrity_checker::run_all(false);
        redirect(
            $pageurl,
            get_string('integrity:run_success', 'local_ai_course_assistant'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$results = integrity_checker::get_last_results();
$alertemail = trim((string)get_config('local_ai_course_assistant', 'integrity_email'));
if ($alertemail === '') {
    $admin = get_admin();
    $alertemail = (string)($admin->email ?? '');
}

$statusclassmap = [
    'pass' => 'success',
    'fail' => 'danger',
    'warn' => 'warning',
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('integrity:title', 'local_ai_course_assistant'));
echo html_writer::div(
    html_writer::link(
        $settingsurl,
        '&larr; ' . get_string('starters:back_settings', 'local_ai_course_assistant'),
        ['class' => 'btn btn-sm btn-outline-secondary mb-3']
    ),
    'mb-2'
);

?>
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
    <div class="text-muted mb-2">
        <?php echo get_string('integrity:alert_email', 'local_ai_course_assistant', s($alertemail)); ?>
    </div>
    <form method="post" action="<?php echo $pageurl->out(false); ?>">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="action" value="run">
        <button type="submit" class="btn btn-primary"><?php echo get_string('integrity:run_now', 'local_ai_course_assistant'); ?></button>
    </form>
</div>

<?php if (empty($results)) { ?>
    <?php echo $OUTPUT->notification(get_string('integrity:no_results', 'local_ai_course_assistant'), \core\output\notification::NOTIFY_INFO); ?>
<?php } else { ?>
    <?php
    $overall = (string)($results['overall_status'] ?? 'warn');
    $overallclass = $statusclassmap[$overall] ?? 'secondary';
    ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center border-<?php echo $overallclass; ?>">
                <div class="card-body">
                    <h3 class="card-title text-<?php echo $overallclass; ?>">
                        <?php echo s(get_string('integrity:status_' . $overall, 'local_ai_course_assistant')); ?>
                    </h3>
                    <p class="card-text text-muted"><?php echo get_string('integrity:status', 'local_ai_course_assistant'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="card-title"><?php echo (int)($results['passed'] ?? 0); ?></h3>
                    <p class="card-text text-muted"><?php echo get_string('integrity:passed', 'local_ai_course_assistant'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="card-title"><?php echo (int)($results['failed'] ?? 0); ?></h3>
                    <p class="card-text text-muted"><?php echo get_string('integrity:failed', 'local_ai_course_assistant'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="card-title"><?php echo (int)($results['warnings'] ?? 0); ?></h3>
                    <p class="card-text text-muted"><?php echo get_string('integrity:warnings', 'local_ai_course_assistant'); ?></p>
                    <small class="text-muted">
                        <?php echo get_string('integrity:last_run', 'local_ai_course_assistant'); ?>:
                        <?php echo userdate((int)($results['run_at'] ?? time())); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><?php echo get_string('integrity:results', 'local_ai_course_assistant'); ?></h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th><?php echo get_string('integrity:check', 'local_ai_course_assistant'); ?></th>
                        <th><?php echo get_string('integrity:status', 'local_ai_course_assistant'); ?></th>
                        <th><?php echo get_string('integrity:details', 'local_ai_course_assistant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (($results['checks'] ?? []) as $check) { ?>
                    <?php $badgeclass = $statusclassmap[$check['status']] ?? 'secondary'; ?>
                    <tr>
                        <td><?php echo s((string)$check['label']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $badgeclass; ?>">
                                <?php echo s(get_string('integrity:status_' . $check['status'], 'local_ai_course_assistant')); ?>
                            </span>
                        </td>
                        <td class="text-muted"><?php echo s((string)$check['details']); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
<?php } ?>

<?php
echo $OUTPUT->footer();
