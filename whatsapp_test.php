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
 * Admin page to test the WhatsApp reminder integration.
 *
 * @package    local_ai_course_assistant
 * @copyright  2025 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_ai_course_assistant\admin_settings_helper;
use local_ai_course_assistant\reminder_manager;

$pageurl = new moodle_url('/local/ai_course_assistant/whatsapp_test.php');
admin_externalpage_setup('local_ai_course_assistant_whatsapptest', '', null, $pageurl);

$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('whatsapptest:title', 'local_ai_course_assistant'));
$PAGE->set_heading(get_string('whatsapptest:title', 'local_ai_course_assistant'));

$settingsurl = new moodle_url('/admin/settings.php', ['section' => admin_settings_helper::SECTION_STUDYPLAN]);

$config = reminder_manager::get_whatsapp_config();
$missingfields = [];
if ($config['apiurl'] === '') {
    $missingfields[] = get_string('whatsapptest:config_api_url', 'local_ai_course_assistant');
}
if ($config['apitoken'] === '') {
    $missingfields[] = get_string('whatsapptest:config_api_token', 'local_ai_course_assistant');
}
if ($config['fromnumber'] === '') {
    $missingfields[] = get_string('whatsapptest:config_from_number', 'local_ai_course_assistant');
}
$configready = empty($missingfields);
$configstatus = $configready
    ? get_string('whatsapptest:config_ready', 'local_ai_course_assistant')
    : get_string('whatsapptest:config_incomplete', 'local_ai_course_assistant');

$destination = optional_param('destination', '', PARAM_RAW_TRIMMED);
$message = optional_param('message', '', PARAM_RAW_TRIMMED);
$result = null;
$resultmessage = '';
$resulttype = \core\output\notification::NOTIFY_INFO;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if ($destination === '') {
        $result = [
            'success' => false,
            'httpcode' => 0,
            'response' => '',
            'error' => get_string('whatsapptest:recipient_required', 'local_ai_course_assistant'),
        ];
    } else {
        $body = $message;
        if ($body === '') {
            $sitename = format_string($SITE->shortname ?: $SITE->fullname, true, ['context' => context_system::instance()]);
            $body = get_string('whatsapptest:default_message', 'local_ai_course_assistant', (object)[
                'site' => $sitename,
                'time' => userdate(time()),
            ]);
            $message = $body;
        }
        $result = reminder_manager::send_whatsapp_message($destination, $body);
    }

    if (!empty($result['success'])) {
        $resultmessage = get_string('whatsapptest:success', 'local_ai_course_assistant');
        $resulttype = \core\output\notification::NOTIFY_SUCCESS;
    } else {
        $resultmessage = get_string('whatsapptest:failure', 'local_ai_course_assistant');
        if (!empty($result['error'])) {
            $resultmessage .= ' ' . s((string)$result['error']);
        }
        $resulttype = \core\output\notification::NOTIFY_ERROR;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('whatsapptest:title', 'local_ai_course_assistant'));
echo html_writer::div(
    html_writer::link(
        $settingsurl,
        '&larr; ' . get_string('whatsapptest:back_to_settings', 'local_ai_course_assistant'),
        ['class' => 'btn btn-sm btn-outline-secondary mb-3']
    ),
    'mb-2'
);
echo $OUTPUT->notification(
    get_string('whatsapptest:config_notice', 'local_ai_course_assistant'),
    \core\output\notification::NOTIFY_INFO
);

if ($result !== null) {
    echo $OUTPUT->notification($resultmessage, $resulttype);
}

?>
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><?php echo s($configstatus); ?></h5>
                <p class="card-text text-muted mb-2"><?php echo get_string('whatsapptest:title', 'local_ai_course_assistant'); ?></p>
                <?php if (!$configready) { ?>
                    <small class="text-danger">
                        <?php echo get_string('whatsapptest:config_missing', 'local_ai_course_assistant', s(implode(', ', $missingfields))); ?>
                    </small>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><?php echo $config['fromnumber'] !== '' ? s($config['fromnumber']) : get_string('whatsapptest:config_not_set', 'local_ai_course_assistant'); ?></h5>
                <p class="card-text text-muted mb-0"><?php echo get_string('whatsapptest:config_from_number', 'local_ai_course_assistant'); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">
                    <?php
                    echo $config['enabled']
                        ? get_string('whatsapptest:config_enabled_on', 'local_ai_course_assistant')
                        : get_string('whatsapptest:config_enabled_off', 'local_ai_course_assistant');
                    ?>
                </h5>
                <p class="card-text text-muted mb-0"><?php echo get_string('whatsapptest:config_enabled', 'local_ai_course_assistant'); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><?php echo get_string('settings:whatsapp_test_title', 'local_ai_course_assistant'); ?></h5>
    </div>
    <div class="card-body">
        <div class="mb-3 text-muted">
            <div><?php echo get_string('whatsapptest:config_api_url', 'local_ai_course_assistant'); ?>:
                <?php echo $config['apiurl'] !== '' ? s($config['apiurl']) : get_string('whatsapptest:config_not_set', 'local_ai_course_assistant'); ?>
            </div>
            <div><?php echo get_string('whatsapptest:config_api_token', 'local_ai_course_assistant'); ?>:
                <?php echo $config['apitoken'] !== '' ? get_string('whatsapptest:config_saved', 'local_ai_course_assistant') : get_string('whatsapptest:config_not_set', 'local_ai_course_assistant'); ?>
            </div>
        </div>

        <form method="post" action="<?php echo $pageurl->out(false); ?>">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

            <div class="form-group">
                <label for="destination"><?php echo get_string('whatsapptest:recipient', 'local_ai_course_assistant'); ?></label>
                <input type="text" class="form-control" id="destination" name="destination"
                       value="<?php echo s($destination); ?>" placeholder="+14155550123">
                <small class="form-text text-muted"><?php echo get_string('whatsapptest:recipient_desc', 'local_ai_course_assistant'); ?></small>
            </div>

            <div class="form-group">
                <label for="message"><?php echo get_string('whatsapptest:message', 'local_ai_course_assistant'); ?></label>
                <textarea class="form-control" id="message" name="message" rows="4"><?php echo s($message); ?></textarea>
                <small class="form-text text-muted"><?php echo get_string('whatsapptest:message_desc', 'local_ai_course_assistant'); ?></small>
            </div>

            <button type="submit" class="btn btn-primary" <?php echo $configready ? '' : 'disabled'; ?>>
                <?php echo get_string('whatsapptest:send', 'local_ai_course_assistant'); ?>
            </button>
            <?php if (!$configready) { ?>
                <small class="text-muted ml-2"><?php echo get_string('whatsapptest:config_missing', 'local_ai_course_assistant', s(implode(', ', $missingfields))); ?></small>
            <?php } ?>
        </form>
    </div>
</div>

<?php if ($result !== null) { ?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><?php echo get_string('whatsapptest:result', 'local_ai_course_assistant'); ?></h5>
    </div>
    <div class="card-body">
        <div class="mb-2">
            <strong><?php echo get_string('whatsapptest:httpcode', 'local_ai_course_assistant'); ?>:</strong>
            <?php echo (int)($result['httpcode'] ?? 0); ?>
        </div>
        <?php if (!empty($result['error'])) { ?>
            <div class="mb-2">
                <strong><?php echo get_string('whatsapptest:error', 'local_ai_course_assistant'); ?>:</strong>
                <?php echo s((string)$result['error']); ?>
            </div>
        <?php } ?>
        <div>
            <strong><?php echo get_string('whatsapptest:response', 'local_ai_course_assistant'); ?>:</strong>
            <pre class="mt-2 mb-0 p-3 bg-light border rounded"><?php echo s((string)($result['response'] ?? '')); ?></pre>
        </div>
    </div>
</div>
<?php } ?>
<?php
echo $OUTPUT->footer();
