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
 * Admin page for managing conversation starter chips.
 *
 * @package    local_ai_course_assistant
 * @copyright  2025 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_ai_course_assistant\starter_manager;

require_login();
require_capability('moodle/site:config', \context_system::instance());

$context = \context_system::instance();
$pageurl = new moodle_url('/local/ai_course_assistant/starter_settings.php');

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('starters:admin_title', 'local_ai_course_assistant'));
$PAGE->set_heading(get_string('starters:admin_title', 'local_ai_course_assistant'));
$PAGE->set_pagelayout('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $action = required_param('action', PARAM_ALPHA);
    if ($action === 'save') {
        $raw = required_param('starters_json', PARAM_RAW);
        $starters = json_decode($raw, true);
        if (is_array($starters)) {
            starter_manager::save_global_starters($starters);
            redirect(
                $pageurl,
                get_string('starters:saved', 'local_ai_course_assistant'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }

    if ($action === 'reset') {
        starter_manager::reset_to_defaults();
        redirect(
            $pageurl,
            get_string('starters:reset_done', 'local_ai_course_assistant'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    redirect($pageurl);
}

$starters = starter_manager::get_global_starters();
$icons = [];
foreach (starter_manager::get_icon_keys() as $key) {
    $icons[$key] = starter_manager::get_icon_svg($key);
}

$jsonflags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$adminsettingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_ai_course_assistant']);

echo $OUTPUT->header();
?>

<style>
.aica-starters-admin {
    max-width: 960px;
    margin: 0 auto;
}

.aica-starter-card {
    margin-bottom: 12px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #fff;
    transition: box-shadow 0.2s ease, opacity 0.2s ease;
}

.aica-starter-card.dragging {
    opacity: 0.6;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.16);
}

.aica-starter-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    cursor: pointer;
    user-select: none;
}

.aica-starter-card-header:hover {
    background: #f8f9fa;
    border-radius: 8px;
}

.aica-drag-handle {
    cursor: grab;
    color: #adb5bd;
    font-size: 18px;
}

.aica-drag-handle:active {
    cursor: grabbing;
}

.aica-starter-icon-preview {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #495057;
}

.aica-starter-icon-preview svg {
    width: 18px;
    height: 18px;
}

.aica-starter-name {
    flex: 1;
    font-weight: 600;
}

.aica-starter-type-badge {
    padding: 2px 8px;
    border-radius: 999px;
    background: #e9ecef;
    color: #495057;
    font-size: 11px;
    text-transform: uppercase;
}

.aica-starter-type-badge.type-quiz {
    background: #fff3cd;
    color: #856404;
}

.aica-starter-type-badge.type-voice,
.aica-starter-type-badge.type-pronunciation {
    background: #d4edda;
    color: #155724;
}

.aica-starter-toggle {
    transform: scale(1.15);
}

.aica-starter-expand-arrow {
    color: #adb5bd;
    font-size: 14px;
    transition: transform 0.2s ease;
}

.aica-starter-card.expanded .aica-starter-expand-arrow {
    transform: rotate(180deg);
}

.aica-starter-card-body {
    display: none;
    padding: 0 16px 16px 52px;
}

.aica-starter-card.expanded .aica-starter-card-body {
    display: block;
}

.aica-field {
    margin-bottom: 12px;
}

.aica-field label {
    display: block;
    margin-bottom: 4px;
    font-size: 13px;
    font-weight: 600;
    color: #495057;
}

.aica-field input[type="text"],
.aica-field textarea,
.aica-field select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
}

.aica-field textarea {
    min-height: 72px;
    resize: vertical;
}

.aica-help {
    margin-top: 4px;
    color: #6c757d;
    font-size: 12px;
}

.aica-icon-picker {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.aica-icon-option {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    background: #fff;
    color: #495057;
    cursor: pointer;
    transition: all 0.15s ease;
}

.aica-icon-option:hover {
    border-color: #0d6efd;
    background: #e7f1ff;
}

.aica-icon-option.selected {
    border-color: #0d6efd;
    background: #0d6efd;
    color: #fff;
}

.aica-icon-option svg {
    width: 18px;
    height: 18px;
}

.aica-starter-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}

.aica-btn-delete {
    padding: 4px 12px;
    border: 1px solid #dc3545;
    border-radius: 6px;
    background: none;
    color: #dc3545;
    cursor: pointer;
    font-size: 13px;
}

.aica-btn-delete:hover {
    background: #dc3545;
    color: #fff;
}

.aica-btn-add {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 16px;
    padding: 12px;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    background: #fff;
    color: #6c757d;
    cursor: pointer;
    font-size: 14px;
}

.aica-btn-add:hover {
    border-color: #0d6efd;
    background: #f8f9ff;
    color: #0d6efd;
}

.aica-admin-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 16px;
}

.aica-admin-actions .btn {
    min-width: 140px;
}

.aica-admin-help {
    border-left: 4px solid #0d6efd;
}
</style>

<div class="aica-starters-admin">
    <p><?php echo get_string('starters:admin_desc', 'local_ai_course_assistant'); ?></p>

    <div class="aica-admin-actions mb-3">
        <form method="post" style="display:inline;">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="starters_json" class="aica-starters-json" value="">
            <button type="submit" class="btn btn-primary aica-save-btn">
                <?php echo get_string('starters:save', 'local_ai_course_assistant'); ?>
            </button>
        </form>
        <form method="post" style="display:inline;"
              onsubmit="return confirm('<?php echo s(get_string('starters:reset_confirm', 'local_ai_course_assistant')); ?>');">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <input type="hidden" name="action" value="reset">
            <button type="submit" class="btn btn-outline-secondary">
                <?php echo get_string('starters:reset_defaults', 'local_ai_course_assistant'); ?>
            </button>
        </form>
        <a href="<?php echo $adminsettingsurl->out(false); ?>" class="btn btn-outline-secondary">
            <?php echo get_string('starters:back_settings', 'local_ai_course_assistant'); ?>
        </a>
    </div>

    <div class="card mb-3 aica-admin-help">
        <div class="card-body">
            <h6 style="cursor:pointer;margin:0;" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none'; this.querySelector('span').textContent=this.nextElementSibling.style.display==='none'?'[+]':'[-]';">
                <span>[+]</span> How to use this page
            </h6>
            <div style="display:none;margin-top:12px;font-size:14px;line-height:1.6;">
                <p><strong>Built-in starters</strong> stay in the system. You can reorder them, enable or disable them, change their icon, and edit their prompt copy where that makes sense, but you cannot delete them.</p>
                <p><strong>Custom starters</strong> are prompt-based chips you create. You can set the visible name, description, icon, prompt, and optional visibility condition.</p>
                <p><strong>Prompt placeholders:</strong> use <code>{page}</code> to insert the current page title automatically when a student clicks the chip.</p>
                <p><strong>Per-course overrides:</strong> after saving changes here, use each course settings page to show or hide individual starters for that course.</p>
            </div>
        </div>
    </div>

    <div id="aica-starters-list"></div>

    <button type="button" class="aica-btn-add" id="aica-add-starter">
        <span style="font-size:20px;">+</span>
        <?php echo get_string('starters:add_new', 'local_ai_course_assistant'); ?>
    </button>

    <div class="aica-admin-actions">
        <form method="post" style="display:inline;">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="starters_json" class="aica-starters-json" value="">
            <button type="submit" class="btn btn-primary aica-save-btn">
                <?php echo get_string('starters:save', 'local_ai_course_assistant'); ?>
            </button>
        </form>
        <form method="post" style="display:inline;"
              onsubmit="return confirm('<?php echo s(get_string('starters:reset_confirm', 'local_ai_course_assistant')); ?>');">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <input type="hidden" name="action" value="reset">
            <button type="submit" class="btn btn-outline-secondary">
                <?php echo get_string('starters:reset_defaults', 'local_ai_course_assistant'); ?>
            </button>
        </form>
        <a href="<?php echo $adminsettingsurl->out(false); ?>" class="btn btn-outline-secondary">
            <?php echo get_string('starters:back_settings', 'local_ai_course_assistant'); ?>
        </a>
    </div>
</div>

<script>
(function() {
    var ICONS = <?php echo json_encode($icons, $jsonflags); ?>;
    var starters = <?php echo json_encode($starters, $jsonflags); ?>;
    var list = document.getElementById('aica-starters-list');
    var iconLabels = {
        book: 'Book',
        lightning: 'Quiz',
        calendar: 'Plan',
        chat: 'Chat',
        refresh: 'Review',
        mic: 'Speaking',
        speaker: 'Pronunciation',
        lightbulb: 'Idea',
        star: 'Star',
        graduation: 'Learning',
        pencil: 'Write',
        compass: 'Explore',
        brain: 'Thinking',
        target: 'Goal',
        search: 'Search',
        heart: 'Support'
    };

    function slug(name) {
        return String(name || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '')
            .substring(0, 30) || ('custom-' + Date.now());
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = String(str || '');
        return div.innerHTML;
    }

    function escAttr(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function renderCard(starter, index) {
        var card = document.createElement('div');
        card.className = 'aica-starter-card';
        card.draggable = true;
        card.dataset.index = index;

        var typeBadge = starter.type !== 'prompt'
            ? '<span class="aica-starter-type-badge type-' + escAttr(starter.type) + '">' +
                escHtml(starter.type) + '</span>'
            : '';
        var conditionalBadge = starter.conditional
            ? '<span class="aica-starter-type-badge">' + escHtml(starter.conditional) + '</span>'
            : '';

        card.innerHTML =
            '<div class="aica-starter-card-header">' +
                '<span class="aica-drag-handle" title="Drag to reorder">&#9776;</span>' +
                '<span class="aica-starter-icon-preview">' + (ICONS[starter.icon] || ICONS.chat) + '</span>' +
                '<span class="aica-starter-name">' + escHtml(starter.name) + '</span>' +
                typeBadge +
                conditionalBadge +
                '<label style="margin:0;display:flex;align-items:center;gap:4px;" onclick="event.stopPropagation()">' +
                    '<input type="checkbox" class="aica-starter-toggle" ' + (starter.enabled ? 'checked' : '') + '>' +
                    '<span style="font-size:12px;color:#6c757d;">On</span>' +
                '</label>' +
                '<span class="aica-starter-expand-arrow">&#9660;</span>' +
            '</div>' +
            '<div class="aica-starter-card-body">' +
                '<div class="aica-field">' +
                    '<label>Name</label>' +
                    '<input type="text" class="aica-f-name" value="' + escAttr(starter.name) + '" placeholder="Chip display name">' +
                '</div>' +
                '<div class="aica-field">' +
                    '<label>Description</label>' +
                    '<input type="text" class="aica-f-desc" value="' + escAttr(starter.description || '') + '" placeholder="Admin-only description">' +
                    '<div class="aica-help">Shown only in this admin panel for reference.</div>' +
                '</div>' +
                (starter.type === 'prompt'
                    ? '<div class="aica-field">' +
                        '<label>AI Prompt</label>' +
                        '<textarea class="aica-f-prompt" placeholder="The message sent to the AI when this chip is clicked...">' +
                            escHtml(starter.prompt || '') +
                        '</textarea>' +
                        '<div class="aica-help">Use <code>{page}</code> to insert the current page title.</div>' +
                    '</div>'
                    : '') +
                '<div class="aica-field">' +
                    '<label>Icon</label>' +
                    '<div class="aica-icon-picker">' +
                        Object.keys(ICONS).map(function(key) {
                            return '<span class="aica-icon-option' + (key === starter.icon ? ' selected' : '') +
                                '" data-icon="' + escAttr(key) + '" title="' + escAttr(iconLabels[key] || key) + '">' +
                                ICONS[key] +
                                '</span>';
                        }).join('') +
                    '</div>' +
                '</div>' +
                (starter.type === 'prompt' && !starter.builtin
                    ? '<div class="aica-field">' +
                        '<label>Conditional Visibility</label>' +
                        '<select class="aica-f-conditional">' +
                            '<option value=""' + (!starter.conditional ? ' selected' : '') + '>Always shown</option>' +
                            '<option value="tts"' + (starter.conditional === 'tts' ? ' selected' : '') + '>Only when TTS enabled</option>' +
                            '<option value="realtime"' + (starter.conditional === 'realtime' ? ' selected' : '') + '>Only when Realtime enabled</option>' +
                        '</select>' +
                    '</div>'
                    : '') +
                '<div class="aica-starter-actions">' +
                    (!starter.builtin
                        ? '<button type="button" class="aica-btn-delete">Delete this starter</button>'
                        : '<span style="font-size:12px;color:#6c757d;">Built-in starter (cannot be deleted)</span>') +
                '</div>' +
            '</div>';

        card.querySelector('.aica-starter-card-header').addEventListener('click', function(e) {
            if (e.target.closest('.aica-starter-toggle') || e.target.closest('label')) {
                return;
            }
            card.classList.toggle('expanded');
        });

        card.querySelector('.aica-starter-toggle').addEventListener('change', function() {
            starters[card.dataset.index].enabled = this.checked;
        });

        var nameInput = card.querySelector('.aica-f-name');
        if (nameInput) {
            nameInput.addEventListener('input', function() {
                starters[card.dataset.index].name = this.value;
                card.querySelector('.aica-starter-name').textContent = this.value;
            });
        }

        var descInput = card.querySelector('.aica-f-desc');
        if (descInput) {
            descInput.addEventListener('input', function() {
                starters[card.dataset.index].description = this.value;
            });
        }

        var promptInput = card.querySelector('.aica-f-prompt');
        if (promptInput) {
            promptInput.addEventListener('input', function() {
                starters[card.dataset.index].prompt = this.value;
            });
        }

        var conditionalSelect = card.querySelector('.aica-f-conditional');
        if (conditionalSelect) {
            conditionalSelect.addEventListener('change', function() {
                starters[card.dataset.index].conditional = this.value;
            });
        }

        card.querySelectorAll('.aica-icon-option').forEach(function(option) {
            option.addEventListener('click', function() {
                card.querySelectorAll('.aica-icon-option').forEach(function(el) {
                    el.classList.remove('selected');
                });
                option.classList.add('selected');
                starters[card.dataset.index].icon = option.dataset.icon;
                card.querySelector('.aica-starter-icon-preview').innerHTML = ICONS[option.dataset.icon] || ICONS.chat;
            });
        });

        var deleteBtn = card.querySelector('.aica-btn-delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                if (window.confirm('Delete "' + (starters[card.dataset.index].name || 'this starter') + '"?')) {
                    starters.splice(card.dataset.index, 1);
                    renderAll();
                }
            });
        }

        card.addEventListener('dragstart', function(e) {
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', card.dataset.index);
        });

        card.addEventListener('dragend', function() {
            card.classList.remove('dragging');
        });

        card.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        card.addEventListener('drop', function(e) {
            e.preventDefault();
            var fromIndex = parseInt(e.dataTransfer.getData('text/plain'), 10);
            var toIndex = parseInt(card.dataset.index, 10);
            if (isNaN(fromIndex) || isNaN(toIndex) || fromIndex === toIndex) {
                return;
            }
            var moved = starters.splice(fromIndex, 1)[0];
            starters.splice(toIndex, 0, moved);
            renderAll();
        });

        return card;
    }

    function renderAll() {
        list.innerHTML = '';
        starters.forEach(function(starter, index) {
            starter.sort_order = index + 1;
            list.appendChild(renderCard(starter, index));
        });
    }

    document.getElementById('aica-add-starter').addEventListener('click', function() {
        starters.push({
            key: 'custom-' + Date.now(),
            name: 'New Starter',
            description: '',
            prompt: '',
            icon: 'chat',
            type: 'prompt',
            enabled: true,
            sort_order: starters.length + 1,
            builtin: false,
            conditional: ''
        });
        renderAll();

        var cards = list.querySelectorAll('.aica-starter-card');
        var lastCard = cards[cards.length - 1];
        if (lastCard) {
            lastCard.classList.add('expanded');
            var firstInput = lastCard.querySelector('.aica-f-name');
            if (firstInput) {
                firstInput.focus();
            }
            lastCard.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
    });

    document.querySelectorAll('.aica-save-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            starters.forEach(function(starter) {
                if (!starter.builtin && String(starter.key || '').indexOf('custom-') === 0) {
                    starter.key = slug(starter.name);
                }
            });
            var json = JSON.stringify(starters);
            document.querySelectorAll('.aica-starters-json').forEach(function(input) {
                input.value = json;
            });
        });
    });

    renderAll();
})();
</script>

<?php
echo $OUTPUT->footer();
