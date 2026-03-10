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
 * Admin settings for local_ai_course_assistant.
 *
 * @package    local_ai_course_assistant
 * @copyright  2025 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ai_course_assistant', get_string('pluginname', 'local_ai_course_assistant'));

    // Enable/disable.
    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/enabled',
        get_string('settings:enabled', 'local_ai_course_assistant'),
        get_string('settings:enabled_desc', 'local_ai_course_assistant'),
        0
    ));

    $settingtabs = [
        ['id' => 'provider', 'label' => 'AI Provider & Conversation', 'startrow' => 'admin-provider'],
        ['id' => 'rag', 'label' => 'RAG / Semantic Search', 'startrow' => 'admin-rag_enabled'],
        ['id' => 'token-analytics', 'label' => 'Token Analytics', 'startrow' => 'admin-token_analytics_link'],
        ['id' => 'plugin-updates', 'label' => 'Plugin Updates', 'startrow' => 'admin-github_token'],
        ['id' => 'integrity-monitoring', 'label' => 'Integrity Monitoring', 'startrow' => 'admin-integrity_enabled'],
        ['id' => 'offtopic-detection', 'label' => 'Off-topic Detection', 'startrow' => 'admin-offtopic_enabled'],
        ['id' => 'wellbeing-safety', 'label' => 'Wellbeing & Safety', 'startrow' => 'admin-wellbeing_enabled'],
        ['id' => 'study-planning', 'label' => 'Study Planning & Reminders', 'startrow' => 'admin-studyplan_enabled'],
        ['id' => 'branding', 'label' => 'Branding', 'startrow' => 'admin-display_name'],
        ['id' => 'faq-support', 'label' => 'FAQ & Support', 'startrow' => 'admin-faq_content'],
        ['id' => 'voice-mode', 'label' => 'Voice Mode (OpenAI Realtime)', 'startrow' => 'admin-realtime_enabled'],
        ['id' => 'debugging', 'label' => 'Debugging', 'startrow' => 'admin-context_debug_enabled'],
    ];

    $tabbuttons = '';
    foreach ($settingtabs as $index => $settingtab) {
        $tabbuttons .= html_writer::tag('button', s($settingtab['label']), [
            'type' => 'button',
            'id' => 'aica-settings-tab-' . $settingtab['id'],
            'class' => 'aica-settings-tab' . ($index === 0 ? ' is-active' : ''),
            'data-aica-tab' => $settingtab['id'],
            'role' => 'tab',
            'aria-selected' => $index === 0 ? 'true' : 'false',
            'aria-controls' => 'aica-settings-panel-' . $settingtab['id'],
            'tabindex' => $index === 0 ? '0' : '-1',
        ]);
    }

    $tabconfigjson = json_encode($settingtabs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $toptoolshtml = <<<HTML
<style>
.aica-settings-tools-row .form-label {
    display: none;
}

.aica-settings-tools-row .form-setting {
    flex: 0 0 100%;
    max-width: 100%;
}

.aica-settings-tools-row .form-shortname,
.aica-settings-tools-row .form-description {
    display: none;
}

#aica-settings-shell {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

#aica-top-buttons {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.5rem;
    margin: 0;
}

#aica-settings-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.aica-settings-tab {
    appearance: none;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background: #ffffff;
    color: #173140;
    cursor: pointer;
    font-weight: 600;
    line-height: 1.2;
    padding: 0.55rem 0.9rem;
    transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
}

.aica-settings-tab:hover {
    border-color: #173140;
}

.aica-settings-tab:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgba(23, 49, 64, 0.18);
}

.aica-settings-tab.is-active,
.aica-settings-tab[aria-selected="true"] {
    background: #173140;
    border-color: #173140;
    color: #ffffff;
}

.aica-settings-panel {
    padding-top: 0.25rem;
}

.aica-settings-panel[hidden] {
    display: none !important;
}

.aica-settings-panel > h3.main:first-child {
    margin-top: 0;
}
</style>
<div id="aica-settings-shell">
    <div id="aica-top-save"></div>
    <div id="aica-settings-tabs" role="tablist" aria-label="SOLA settings sections">
        {$tabbuttons}
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var sections = {$tabconfigjson};
    var storageKey = "local_ai_course_assistant_admin_tab";
    var settingsShell = document.getElementById("aica-settings-shell");
    var toolRow = settingsShell ? settingsShell.closest(".form-item.row") : null;
    var topSave = document.getElementById("aica-top-save");
    var bottomButtons = document.querySelector(".form-buttons");
    if (topSave && bottomButtons && !document.getElementById("aica-top-buttons")) {
        var clonedButtons = bottomButtons.cloneNode(true);
        clonedButtons.id = "aica-top-buttons";
        topSave.appendChild(clonedButtons);
    }
    if (!toolRow) {
        return;
    }
    toolRow.classList.add("aica-settings-tools-row");
    var fieldset = toolRow.closest("fieldset");
    var tabsRoot = document.getElementById("aica-settings-tabs");
    if (!fieldset || !tabsRoot || fieldset.querySelector(".aica-settings-panel")) {
        return;
    }
    var sectionBoundaries = sections.map(function(section) {
        var startRow = document.getElementById(section.startrow);
        if (!startRow || !fieldset.contains(startRow)) {
            return null;
        }
        var heading = startRow.previousElementSibling;
        while (heading && heading.parentElement === fieldset && !(heading.matches && heading.matches("h3.main"))) {
            heading = heading.previousElementSibling;
        }
        if (!heading || !fieldset.contains(heading)) {
            return null;
        }
        return {
            id: section.id,
            heading: heading
        };
    }).filter(function(section) {
        return !!section;
    });
    if (!sectionBoundaries.length) {
        return;
    }
    var marker = document.createElement("div");
    fieldset.insertBefore(marker, sectionBoundaries[0].heading);
    sectionBoundaries.forEach(function(section, index) {
        var panel = document.createElement("section");
        panel.className = "aica-settings-panel";
        panel.id = "aica-settings-panel-" + section.id;
        panel.dataset.aicaPanel = section.id;
        panel.setAttribute("role", "tabpanel");
        panel.setAttribute("aria-labelledby", "aica-settings-tab-" + section.id);
        if (index !== 0) {
            panel.hidden = true;
        }
        fieldset.insertBefore(panel, marker);
        var nextHeading = index + 1 < sectionBoundaries.length ? sectionBoundaries[index + 1].heading : null;
        var current = section.heading;
        while (current && current !== nextHeading) {
            var next = current.nextElementSibling;
            panel.appendChild(current);
            current = next;
        }
    });
    marker.remove();
    var availableIds = sectionBoundaries.map(function(section) {
        return section.id;
    });
    Array.prototype.slice.call(tabsRoot.querySelectorAll(".aica-settings-tab")).forEach(function(button) {
        if (availableIds.indexOf(button.getAttribute("data-aica-tab")) === -1) {
            button.hidden = true;
        }
    });
    var buttons = Array.prototype.slice.call(tabsRoot.querySelectorAll(".aica-settings-tab:not([hidden])"));
    var panels = Array.prototype.slice.call(fieldset.querySelectorAll(".aica-settings-panel"));
    var buttonIds = buttons.map(function(button) {
        return button.getAttribute("data-aica-tab");
    });
    function activateTab(tabId, persist) {
        if (buttonIds.indexOf(tabId) === -1) {
            return;
        }
        buttons.forEach(function(button) {
            var active = button.getAttribute("data-aica-tab") === tabId;
            button.classList.toggle("is-active", active);
            button.setAttribute("aria-selected", active ? "true" : "false");
            button.setAttribute("tabindex", active ? "0" : "-1");
        });
        panels.forEach(function(panel) {
            var active = panel.dataset.aicaPanel === tabId;
            panel.hidden = !active;
        });
        if (persist) {
            try {
                window.localStorage.setItem(storageKey, tabId);
            } catch (error) {
            }
        }
    }
    buttons.forEach(function(button, index) {
        button.addEventListener("click", function() {
            activateTab(button.getAttribute("data-aica-tab"), true);
        });
        button.addEventListener("keydown", function(event) {
            if (event.key !== "ArrowRight" && event.key !== "ArrowLeft") {
                return;
            }
            event.preventDefault();
            var nextIndex = event.key === "ArrowRight"
                ? (index + 1) % buttons.length
                : (index - 1 + buttons.length) % buttons.length;
            buttons[nextIndex].focus();
            activateTab(buttons[nextIndex].getAttribute("data-aica-tab"), true);
        });
    });
    var activeTab = null;
    panels.some(function(panel) {
        if (panel.querySelector(".error")) {
            activeTab = panel.dataset.aicaPanel;
            return true;
        }
        return false;
    });
    if (!activeTab) {
        try {
            activeTab = window.localStorage.getItem(storageKey);
        } catch (error) {
            activeTab = null;
        }
    }
    if (!activeTab || buttonIds.indexOf(activeTab) === -1) {
        activeTab = buttonIds.length ? buttonIds[0] : null;
    }
    if (activeTab) {
        activateTab(activeTab, false);
    }
});
</script>
HTML;

    // Top save button and tabbed settings shell.
    $settings->add(new admin_setting_description(
        'local_ai_course_assistant/top_save_btn',
        '',
        $toptoolshtml
    ));

    $settings->add(new admin_setting_heading(
        'local_ai_course_assistant/provider_heading',
        'AI Provider & Conversation',
        'Configure the model, system prompt, and core conversation behavior.'
    ));

    // AI Provider.
    $providers = [
        'claude' => get_string('settings:provider_claude', 'local_ai_course_assistant'),
        'openai' => get_string('settings:provider_openai', 'local_ai_course_assistant'),
        'deepseek' => get_string('settings:provider_deepseek', 'local_ai_course_assistant'),
        'ollama' => get_string('settings:provider_ollama', 'local_ai_course_assistant'),
        'minimax' => get_string('settings:provider_minimax', 'local_ai_course_assistant'),
        'custom' => get_string('settings:provider_custom', 'local_ai_course_assistant'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_ai_course_assistant/provider',
        get_string('settings:provider', 'local_ai_course_assistant'),
        get_string('settings:provider_desc', 'local_ai_course_assistant'),
        'openai',
        $providers
    ));

    // API Key.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_ai_course_assistant/apikey',
        get_string('settings:apikey', 'local_ai_course_assistant'),
        get_string('settings:apikey_desc', 'local_ai_course_assistant'),
        ''
    ));

    // Model name.
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/model',
        get_string('settings:model', 'local_ai_course_assistant'),
        get_string('settings:model_desc', 'local_ai_course_assistant'),
        ''
    ));

    // API Base URL.
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/apibaseurl',
        get_string('settings:apibaseurl', 'local_ai_course_assistant'),
        get_string('settings:apibaseurl_desc', 'local_ai_course_assistant'),
        ''
    ));

    // System prompt template.
    $settings->add(new admin_setting_configtextarea(
        'local_ai_course_assistant/systemprompt',
        get_string('settings:systemprompt', 'local_ai_course_assistant'),
        get_string('settings:systemprompt_desc', 'local_ai_course_assistant'),
        get_string('settings:systemprompt_default', 'local_ai_course_assistant')
    ));

    // Remote config URL.
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/remoteconfigurl',
        get_string('remoteconfigurl', 'local_ai_course_assistant'),
        get_string('remoteconfigurl_desc', 'local_ai_course_assistant'),
        \local_ai_course_assistant\remote_config_manager::DEFAULT_URL,
        PARAM_URL
    ));

    // Temperature.
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/temperature',
        get_string('settings:temperature', 'local_ai_course_assistant'),
        get_string('settings:temperature_desc', 'local_ai_course_assistant'),
        '0.7',
        PARAM_FLOAT
    ));

    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/max_tokens',
        'Max Response Length (tokens)',
        'Maximum number of tokens per AI response. Lower values produce shorter, faster responses. ' .
            '512 = ~2-3 sentences, 1024 = ~1-2 paragraphs, 2048 = longer explanations. ' .
            'Set to 0 for no limit (provider default).',
        '1024',
        PARAM_INT
    ));

    // Max conversation history.
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/maxhistory',
        get_string('settings:maxhistory', 'local_ai_course_assistant'),
        get_string('settings:maxhistory_desc', 'local_ai_course_assistant'),
        '20',
        PARAM_INT
    ));

    // Display mode.
    $displaymodes = [
        'widget' => get_string('settings:display_mode_widget', 'local_ai_course_assistant'),
        'drawer' => get_string('settings:display_mode_drawer', 'local_ai_course_assistant'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_ai_course_assistant/display_mode',
        get_string('settings:display_mode', 'local_ai_course_assistant'),
        get_string('settings:display_mode_desc', 'local_ai_course_assistant'),
        'drawer',
        $displaymodes
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/hide_on_quiz_for_students',
        get_string('settings:hide_on_quiz_for_students', 'local_ai_course_assistant'),
        get_string('settings:hide_on_quiz_for_students_desc', 'local_ai_course_assistant'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/hide_on_quiz_for_staff',
        get_string('settings:hide_on_quiz_for_staff', 'local_ai_course_assistant'),
        get_string('settings:hide_on_quiz_for_staff_desc', 'local_ai_course_assistant'),
        0
    ));

    $startersurl = new moodle_url('/local/ai_course_assistant/starter_settings.php');
    $settings->add(new admin_setting_description(
        'local_ai_course_assistant/starters_link',
        get_string('starters:admin_title', 'local_ai_course_assistant'),
        get_string('starters:admin_desc', 'local_ai_course_assistant') . '<br><br>' .
            html_writer::link(
                $startersurl,
                get_string('starters:admin_title', 'local_ai_course_assistant'),
                ['class' => 'btn btn-secondary btn-sm']
            )
    ));

    // --- RAG / Semantic Search Settings ---
    $settings->add(new admin_setting_heading(
        'local_ai_course_assistant/rag_heading',
        get_string('settings:rag_heading', 'local_ai_course_assistant'),
        get_string('settings:rag_heading_desc', 'local_ai_course_assistant')
    ));

    // RAG master toggle.
    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/rag_enabled',
        get_string('settings:rag_enabled', 'local_ai_course_assistant'),
        get_string('settings:rag_enabled_desc', 'local_ai_course_assistant'),
        1
    ));

    // Embedding provider.
    $embeddingproviders = [
        'openai' => get_string('settings:embed_provider_openai', 'local_ai_course_assistant'),
        'ollama' => get_string('settings:embed_provider_ollama', 'local_ai_course_assistant'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_ai_course_assistant/embed_provider',
        get_string('settings:embed_provider', 'local_ai_course_assistant'),
        get_string('settings:embed_provider_desc', 'local_ai_course_assistant'),
        'openai',
        $embeddingproviders
    ));

    // Embedding API key (separate from chat API key).
    $settings->add(new admin_setting_configpasswordunmask(
        'local_ai_course_assistant/embed_apikey',
        get_string('settings:embed_apikey', 'local_ai_course_assistant'),
        get_string('settings:embed_apikey_desc', 'local_ai_course_assistant'),
        ''
    ));

    // Embedding model name.
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/embed_model',
        get_string('settings:embed_model', 'local_ai_course_assistant'),
        get_string('settings:embed_model_desc', 'local_ai_course_assistant'),
        'text-embedding-3-small'
    ));

    // Embedding API base URL (for Ollama or custom endpoints).
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/embed_apibaseurl',
        get_string('settings:embed_apibaseurl', 'local_ai_course_assistant'),
        get_string('settings:embed_apibaseurl_desc', 'local_ai_course_assistant'),
        ''
    ));

    // Embedding dimensions.
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/embed_dimensions',
        get_string('settings:embed_dimensions', 'local_ai_course_assistant'),
        get_string('settings:embed_dimensions_desc', 'local_ai_course_assistant'),
        '1536',
        PARAM_INT
    ));

    // Top-k chunks retrieved per query.
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/rag_topk',
        get_string('settings:rag_topk', 'local_ai_course_assistant'),
        get_string('settings:rag_topk_desc', 'local_ai_course_assistant'),
        '5',
        PARAM_INT
    ));

    // Chunk size (target words per chunk).
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/rag_chunksize',
        get_string('settings:rag_chunksize', 'local_ai_course_assistant'),
        get_string('settings:rag_chunksize_desc', 'local_ai_course_assistant'),
        '400',
        PARAM_INT
    ));

    // Link to RAG index status / reindex admin page.
    $ragadminurl = new moodle_url('/local/ai_course_assistant/rag_admin.php');
    $settings->add(new admin_setting_description(
        'local_ai_course_assistant/rag_admin_link',
        get_string('ragadmin:title', 'local_ai_course_assistant'),
        html_writer::link($ragadminurl,
            get_string('ragadmin:view_status', 'local_ai_course_assistant'),
            ['class' => 'btn btn-secondary btn-sm'])
    ));

    // --- Token Analytics ---
    $settings->add(new admin_setting_heading(
        'local_ai_course_assistant/token_analytics_heading',
        'Token Analytics',
        'Review aggregate token usage and estimated costs across all courses.'
    ));

    $tokenanalyticsurl = new moodle_url('/local/ai_course_assistant/token_analytics.php');
    $settings->add(new admin_setting_description(
        'local_ai_course_assistant/token_analytics_link',
        get_string('coursesettings:token_usage', 'local_ai_course_assistant'),
        html_writer::link(
            $tokenanalyticsurl,
            get_string('coursesettings:token_usage', 'local_ai_course_assistant'),
            ['class' => 'btn btn-secondary btn-sm']
        )
    ));

    // --- Plugin Updates ---
    $settings->add(new admin_setting_heading(
        'local_ai_course_assistant/updates_heading',
        get_string('settings:updates_heading', 'local_ai_course_assistant'),
        get_string('settings:updates_heading_desc', 'local_ai_course_assistant')
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_ai_course_assistant/github_token',
        get_string('settings:github_token', 'local_ai_course_assistant'),
        get_string('settings:github_token_desc', 'local_ai_course_assistant'),
        ''
    ));

    $updateadminurl = new moodle_url('/local/ai_course_assistant/update_admin.php');
    $settings->add(new admin_setting_description(
        'local_ai_course_assistant/update_admin_link',
        get_string('updates:title', 'local_ai_course_assistant'),
        html_writer::link(
            $updateadminurl,
            get_string('updates:title', 'local_ai_course_assistant'),
            ['class' => 'btn btn-secondary btn-sm']
        )
    ));

    // --- Integrity Monitoring ---
    $settings->add(new admin_setting_heading(
        'local_ai_course_assistant/integrity_heading',
        get_string('settings:integrity_heading', 'local_ai_course_assistant'),
        get_string('settings:integrity_heading_desc', 'local_ai_course_assistant')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/integrity_enabled',
        get_string('settings:integrity_enabled', 'local_ai_course_assistant'),
        get_string('settings:integrity_enabled_desc', 'local_ai_course_assistant'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/integrity_email',
        get_string('settings:integrity_email', 'local_ai_course_assistant'),
        get_string('settings:integrity_email_desc', 'local_ai_course_assistant'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $integrityadminurl = new moodle_url('/local/ai_course_assistant/integrity_admin.php');
    $settings->add(new admin_setting_description(
        'local_ai_course_assistant/integrity_admin_link',
        get_string('integrity:title', 'local_ai_course_assistant'),
        html_writer::link(
            $integrityadminurl,
            get_string('integrity:title', 'local_ai_course_assistant'),
            ['class' => 'btn btn-secondary btn-sm']
        )
    ));

    // --- Off-topic Detection Settings ---
    $settings->add(new admin_setting_heading(
        'local_ai_course_assistant/offtopic_heading',
        get_string('settings:offtopic_heading', 'local_ai_course_assistant'),
        get_string('settings:offtopic_heading_desc', 'local_ai_course_assistant')
    ));

    // Off-topic detection enable.
    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/offtopic_enabled',
        get_string('settings:offtopic_enabled', 'local_ai_course_assistant'),
        get_string('settings:offtopic_enabled_desc', 'local_ai_course_assistant'),
        1
    ));

    // Max off-topic messages before action.
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/offtopic_max',
        get_string('settings:offtopic_max', 'local_ai_course_assistant'),
        get_string('settings:offtopic_max_desc', 'local_ai_course_assistant'),
        '3',
        PARAM_INT
    ));

    // Off-topic action.
    $offtopicactions = [
        'warn' => get_string('settings:offtopic_action_warn', 'local_ai_course_assistant'),
        'end' => get_string('settings:offtopic_action_end', 'local_ai_course_assistant'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_ai_course_assistant/offtopic_action',
        get_string('settings:offtopic_action', 'local_ai_course_assistant'),
        get_string('settings:offtopic_action_desc', 'local_ai_course_assistant'),
        'warn',
        $offtopicactions
    ));

    // Off-topic lockout duration (minutes).
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/offtopic_lockout_duration',
        get_string('settings:offtopic_lockout_duration', 'local_ai_course_assistant'),
        get_string('settings:offtopic_lockout_duration_desc', 'local_ai_course_assistant'),
        '30',
        PARAM_INT
    ));

    // --- Wellbeing & Safety Settings ---
    $settings->add(new admin_setting_heading(
        'local_ai_course_assistant/wellbeing_heading',
        get_string('settings:wellbeing_heading', 'local_ai_course_assistant'),
        get_string('settings:wellbeing_heading_desc', 'local_ai_course_assistant')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/wellbeing_enabled',
        get_string('settings:wellbeing_enabled', 'local_ai_course_assistant'),
        get_string('settings:wellbeing_enabled_desc', 'local_ai_course_assistant'),
        1
    ));

    // --- Study Planning & Reminders Settings ---
    $settings->add(new admin_setting_heading(
        'local_ai_course_assistant/studyplan_heading',
        get_string('settings:studyplan_heading', 'local_ai_course_assistant'),
        get_string('settings:studyplan_heading_desc', 'local_ai_course_assistant')
    ));

    // Study planning enable.
    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/studyplan_enabled',
        get_string('settings:studyplan_enabled', 'local_ai_course_assistant'),
        get_string('settings:studyplan_enabled_desc', 'local_ai_course_assistant'),
        1
    ));

    // Email reminders enable.
    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/reminders_email_enabled',
        get_string('settings:reminders_email_enabled', 'local_ai_course_assistant'),
        get_string('settings:reminders_email_enabled_desc', 'local_ai_course_assistant'),
        1
    ));

    // WhatsApp reminders enable.
    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/reminders_whatsapp_enabled',
        get_string('settings:reminders_whatsapp_enabled', 'local_ai_course_assistant'),
        get_string('settings:reminders_whatsapp_enabled_desc', 'local_ai_course_assistant'),
        0
    ));

    // WhatsApp API URL (Twilio, MessageBird, etc.).
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/whatsapp_api_url',
        get_string('settings:whatsapp_api_url', 'local_ai_course_assistant'),
        get_string('settings:whatsapp_api_url_desc', 'local_ai_course_assistant'),
        ''
    ));

    // WhatsApp API token.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_ai_course_assistant/whatsapp_api_token',
        get_string('settings:whatsapp_api_token', 'local_ai_course_assistant'),
        get_string('settings:whatsapp_api_token_desc', 'local_ai_course_assistant'),
        ''
    ));

    // WhatsApp sender number.
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/whatsapp_from_number',
        get_string('settings:whatsapp_from_number', 'local_ai_course_assistant'),
        get_string('settings:whatsapp_from_number_desc', 'local_ai_course_assistant'),
        ''
    ));

    // Countries where WhatsApp reminders are blocked (comma-separated ISO codes).
    $settings->add(new admin_setting_configtextarea(
        'local_ai_course_assistant/whatsapp_blocked_countries',
        get_string('settings:whatsapp_blocked_countries', 'local_ai_course_assistant'),
        get_string('settings:whatsapp_blocked_countries_desc', 'local_ai_course_assistant'),
        ''
    ));

    // Inactivity reminders.
    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/inactivity_reminder_enabled',
        'Inactivity Reminders',
        'Send a weekly email to students who have not accessed their course in the configured number of days.',
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/inactivity_threshold_days',
        'Inactivity Threshold (days)',
        'Number of days of inactivity before sending a reminder email.',
        '7',
        PARAM_INT
    ));

    // Branding.
    $settings->add(new admin_setting_heading(
        'local_ai_course_assistant/branding_heading',
        'Branding',
        'Customize the assistant name and appearance.'
    ));

    // Display name.
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/display_name',
        'Display Name',
        'The full name shown in greetings and the welcome screen (e.g. "SOLA").',
        'SOLA'
    ));

    // Short name.
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/short_name',
        'Short Name',
        'Short name shown in the header bar and compact UI elements.',
        'SOLA'
    ));

    // Welcome message (shown on the first-visit welcome screen).
    $settings->add(new admin_setting_configtextarea(
        'local_ai_course_assistant/welcome_message',
        'Welcome Screen Message',
        'Message shown on the first-visit welcome screen. Use <code>{{firstname}}</code> for the student\'s first name and <code>{{coursename}}</code> for the course name. Leave blank for the default.',
        ''
    ));

    // Chat greeting (shown when the chat opens).
    $settings->add(new admin_setting_configtextarea(
        'local_ai_course_assistant/chat_greeting',
        'Chat Greeting',
        'Greeting message shown when the chat window opens. Use <code>{{firstname}}</code> for the student\'s first name and <code>{{coursename}}</code> for the course name. Leave blank for the default.',
        ''
    ));

    // Position.
    $positions = [
        'bottom-right' => get_string('settings:position_br', 'local_ai_course_assistant'),
        'bottom-left' => get_string('settings:position_bl', 'local_ai_course_assistant'),
        'top-right' => get_string('settings:position_tr', 'local_ai_course_assistant'),
        'top-left' => get_string('settings:position_tl', 'local_ai_course_assistant'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_ai_course_assistant/position',
        get_string('settings:position', 'local_ai_course_assistant'),
        get_string('settings:position_desc', 'local_ai_course_assistant'),
        'bottom-right',
        $positions
    ));

    // Avatar selection.
    $avatarchoices = [
        'avatar_01' => get_string('settings:avatar_saylor', 'local_ai_course_assistant'),
    ];
    for ($i = 2; $i <= 10; $i++) {
        $num = str_pad($i, 2, '0', STR_PAD_LEFT);
        $avatarchoices["avatar_{$num}"] = "Avatar {$i}";
    }
    $settings->add(new admin_setting_configselect(
        'local_ai_course_assistant/avatar',
        get_string('settings:avatar', 'local_ai_course_assistant'),
        get_string('settings:avatar_desc', 'local_ai_course_assistant'),
        'avatar_01',
        $avatarchoices
    ));

    // Avatar border color.
    $settings->add(new admin_setting_configcolourpicker(
        'local_ai_course_assistant/avatar_color',
        get_string('settings:avatar_color', 'local_ai_course_assistant'),
        get_string('settings:avatar_color_desc', 'local_ai_course_assistant'),
        '#173140'
    ));

    // Avatar fill/background color.
    $settings->add(new admin_setting_configcolourpicker(
        'local_ai_course_assistant/avatar_fill',
        get_string('settings:avatar_fill', 'local_ai_course_assistant'),
        get_string('settings:avatar_fill_desc', 'local_ai_course_assistant'),
        '#ffffff'
    ));

    // --- FAQ & Support Settings ---
    $settings->add(new admin_setting_heading(
        'local_ai_course_assistant/faq_heading',
        get_string('settings:faq_heading', 'local_ai_course_assistant'),
        get_string('settings:faq_heading_desc', 'local_ai_course_assistant')
    ));

    // FAQ content.
    $settings->add(new admin_setting_configtextarea(
        'local_ai_course_assistant/faq_content',
        get_string('settings:faq_content', 'local_ai_course_assistant'),
        get_string('settings:faq_content_desc', 'local_ai_course_assistant'),
        ''
    ));

    // Zendesk integration enable.
    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/zendesk_enabled',
        get_string('settings:zendesk_enabled', 'local_ai_course_assistant'),
        get_string('settings:zendesk_enabled_desc', 'local_ai_course_assistant'),
        0
    ));

    // Zendesk subdomain.
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/zendesk_subdomain',
        get_string('settings:zendesk_subdomain', 'local_ai_course_assistant'),
        get_string('settings:zendesk_subdomain_desc', 'local_ai_course_assistant'),
        ''
    ));

    // Zendesk API email.
    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/zendesk_email',
        get_string('settings:zendesk_email', 'local_ai_course_assistant'),
        get_string('settings:zendesk_email_desc', 'local_ai_course_assistant'),
        ''
    ));

    // Zendesk API token.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_ai_course_assistant/zendesk_token',
        get_string('settings:zendesk_token', 'local_ai_course_assistant'),
        get_string('settings:zendesk_token_desc', 'local_ai_course_assistant'),
        ''
    ));

    // --- Voice Mode (OpenAI Realtime) Settings ---
    $settings->add(new admin_setting_heading(
        'local_ai_course_assistant/realtime_heading',
        get_string('settings:realtime_heading', 'local_ai_course_assistant'),
        get_string('settings:realtime_heading_desc', 'local_ai_course_assistant')
    ));

    // Realtime enable.
    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/realtime_enabled',
        get_string('settings:realtime_enabled', 'local_ai_course_assistant'),
        get_string('settings:realtime_enabled_desc', 'local_ai_course_assistant'),
        0
    ));

    // Realtime API key (separate from main key).
    $settings->add(new admin_setting_configpasswordunmask(
        'local_ai_course_assistant/realtime_apikey',
        get_string('settings:realtime_apikey', 'local_ai_course_assistant'),
        get_string('settings:realtime_apikey_desc', 'local_ai_course_assistant'),
        ''
    ));

    // Realtime voice.
    $realtimevoices = \local_ai_course_assistant\voice_catalog::display_voices();
    $settings->add(new admin_setting_configselect(
        'local_ai_course_assistant/realtime_voice',
        get_string('settings:realtime_voice', 'local_ai_course_assistant'),
        get_string('settings:realtime_voice_desc', 'local_ai_course_assistant'),
        \local_ai_course_assistant\voice_catalog::DEFAULT_VOICE,
        $realtimevoices
    ));

    // --- Debugging Settings ---
    $settings->add(new admin_setting_heading(
        'local_ai_course_assistant/debug_heading',
        get_string('settings:debug_heading', 'local_ai_course_assistant'),
        get_string('settings:debug_heading_desc', 'local_ai_course_assistant')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/context_debug_enabled',
        get_string('settings:context_debug_enabled', 'local_ai_course_assistant'),
        get_string('settings:context_debug_enabled_desc', 'local_ai_course_assistant'),
        0
    ));

    $ADMIN->add('localplugins', $settings);

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ai_course_assistant_starters',
        get_string('starters:admin_title', 'local_ai_course_assistant'),
        new moodle_url('/local/ai_course_assistant/starter_settings.php'),
        'moodle/site:config'
    ));

    // Register the RAG admin page as a separate entry in the admin menu.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ai_course_assistant_ragadmin',
        get_string('ragadmin:title', 'local_ai_course_assistant'),
        new moodle_url('/local/ai_course_assistant/rag_admin.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ai_course_assistant_updateadmin',
        get_string('updates:title', 'local_ai_course_assistant'),
        new moodle_url('/local/ai_course_assistant/update_admin.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ai_course_assistant_integrityadmin',
        get_string('integrity:title', 'local_ai_course_assistant'),
        new moodle_url('/local/ai_course_assistant/integrity_admin.php'),
        'moodle/site:config'
    ));
}
