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
 * Plugin update admin page.
 *
 * @package    local_ai_course_assistant
 * @copyright  2025 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_ai_course_assistant\plugin_updater;

$syscontext = context_system::instance();
require_login();
require_capability('moodle/site:config', $syscontext);

$pageurl = new moodle_url('/local/ai_course_assistant/update_admin.php');
$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_ai_course_assistant']);

$PAGE->set_url($pageurl);
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('updates:title', 'local_ai_course_assistant'));
$PAGE->set_heading(get_string('updates:title', 'local_ai_course_assistant'));
$PAGE->set_pagelayout('admin');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = optional_param('action', '', PARAM_ALPHA);
    if ($action === 'install') {
        try {
            $result = plugin_updater::install_latest_release();
            redirect(
                new moodle_url('/admin/index.php'),
                get_string('updates:install_success', 'local_ai_course_assistant', (object)[
                    'old' => $result['previous_release'] ?? '',
                    'new' => $result['installed_release'] ?? '',
                ]),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$current = plugin_updater::get_current_release();
$latest = [];
try {
    $latest = plugin_updater::get_latest_release();
} catch (\Throwable $e) {
    $error = $e->getMessage();
}

$statuslabel = get_string('updates:status_current', 'local_ai_course_assistant');
$statusclass = 'success';
if (!empty($latest) && !empty($latest['has_update'])) {
    $statuslabel = get_string('updates:status_available', 'local_ai_course_assistant');
    $statusclass = 'warning';
} else if (empty($latest)) {
    $statuslabel = get_string('updates:status_unknown', 'local_ai_course_assistant');
    $statusclass = 'secondary';
}

$backupname = (string)(get_config('local_ai_course_assistant', 'update_last_backup') ?: '');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('updates:title', 'local_ai_course_assistant'));
echo html_writer::div(
    html_writer::link(
        $settingsurl,
        '&larr; ' . get_string('starters:back_settings', 'local_ai_course_assistant'),
        ['class' => 'btn btn-sm btn-outline-secondary mb-3']
    ),
    'mb-2'
);

if ($error !== '') {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}

?>
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="card-title"><?php echo s((string)($current['release'] ?? '')); ?></h3>
                <p class="card-text text-muted"><?php echo get_string('updates:current_version', 'local_ai_course_assistant'); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="card-title"><?php echo s((string)($latest['version'] ?? get_string('updates:unknown', 'local_ai_course_assistant'))); ?></h3>
                <p class="card-text text-muted"><?php echo get_string('updates:latest_version', 'local_ai_course_assistant'); ?></p>
                <?php if (!empty($latest['published_at'])) { ?>
                <small class="text-muted"><?php echo get_string('updates:published', 'local_ai_course_assistant'); ?>: <?php echo userdate(strtotime((string)$latest['published_at'])); ?></small>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center border-<?php echo $statusclass; ?>">
            <div class="card-body">
                <h3 class="card-title text-<?php echo $statusclass; ?>"><?php echo s($statuslabel); ?></h3>
                <p class="card-text text-muted"><?php echo get_string('updates:status', 'local_ai_course_assistant'); ?></p>
            </div>
        </div>
    </div>
</div>

<?php if ($backupname !== '') { ?>
<div class="alert alert-info">
    <?php echo get_string('updates:last_backup', 'local_ai_course_assistant', s($backupname)); ?>
</div>
<?php } ?>

<?php if (!empty($latest['has_update'])) { ?>
<form method="post" action="<?php echo $pageurl->out(false); ?>" class="mb-4"
      onsubmit="return confirm('<?php echo s(get_string('updates:install_confirm', 'local_ai_course_assistant')); ?>');">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
    <input type="hidden" name="action" value="install">
    <button type="submit" class="btn btn-primary">
        <?php echo get_string('updates:install', 'local_ai_course_assistant'); ?>
    </button>
    <small class="text-muted ml-2"><?php echo get_string('updates:backup_note', 'local_ai_course_assistant'); ?></small>
</form>
<?php } else { ?>
<div class="alert alert-success mb-4">
    <?php echo get_string('updates:no_update', 'local_ai_course_assistant'); ?>
</div>
<?php } ?>

<?php if (!empty($latest['html_url'])) { ?>
<p class="mb-4">
    <a href="<?php echo s((string)$latest['html_url']); ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
        <?php echo get_string('updates:view_release', 'local_ai_course_assistant'); ?>
    </a>
</p>
<?php } ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><?php echo get_string('updates:changelog', 'local_ai_course_assistant'); ?></h5>
    </div>
    <div class="card-body">
        <?php
        if (!empty($latest['body'])) {
            echo format_text((string)$latest['body'], FORMAT_MARKDOWN, ['overflowdiv' => true]);
        } else {
            echo html_writer::div(get_string('updates:no_changelog', 'local_ai_course_assistant'), 'text-muted');
        }
        ?>
    </div>
</div>

<?php
echo $OUTPUT->footer();
