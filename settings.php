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
    $helper = '\\local_ai_course_assistant\\admin_settings_helper';
    $llmmanagementsection = 'local_ai_course_assistant_llm_provider_management';
    $llmmanagementurl = new moodle_url('/admin/settings.php', ['section' => $llmmanagementsection]);
    $llmproviders = \local_ai_course_assistant\llm_provider_manager::get_catalog();
    $llmprovideroptions = \local_ai_course_assistant\llm_provider_manager::get_provider_options();
    $llmmodeloptions = \local_ai_course_assistant\llm_provider_manager::get_model_options();
    try {
        $activedefaultllm = \local_ai_course_assistant\llm_provider_manager::get_active_default_selection();
        $defaultllmprovider = $activedefaultllm['provider'];
        $defaultllmmodel = $activedefaultllm['model'];
    } catch (\Throwable $e) {
        $defaultllmprovider = \local_ai_course_assistant\llm_provider_manager::get_system_default_provider();
        $defaultllmmodel = array_key_exists('', $llmmodeloptions)
            ? ''
            : \local_ai_course_assistant\llm_provider_manager::get_system_default_model($defaultllmprovider);
    }

    $llmprovidersettings = new admin_settingpage(
        $llmmanagementsection,
        get_string('settings:llm_management_title', 'local_ai_course_assistant'),
        'moodle/site:config'
    );

    $llmprovidersettings->add(new admin_setting_heading(
        'local_ai_course_assistant/llm_provider_management_heading',
        get_string('settings:llm_management_heading', 'local_ai_course_assistant'),
        get_string('settings:llm_management_desc', 'local_ai_course_assistant')
    ));

    $llmprovidersettings->add(new admin_setting_configselect(
        'local_ai_course_assistant/llm_default_provider',
        get_string('settings:llm_default_provider', 'local_ai_course_assistant'),
        get_string('settings:llm_default_provider_desc', 'local_ai_course_assistant'),
        $defaultllmprovider,
        $llmprovideroptions
    ));

    $llmprovidersettings->add(new admin_setting_configselect(
        'local_ai_course_assistant/llm_default_model',
        get_string('settings:llm_default_model', 'local_ai_course_assistant'),
        get_string('settings:llm_default_model_desc', 'local_ai_course_assistant'),
        $defaultllmmodel,
        $llmmodeloptions
    ));

    foreach ($llmproviders as $providerid => $providerinfo) {
        $baseurlnote = $providerinfo['default_baseurl'] !== ''
            ? s($providerinfo['default_baseurl'])
            : get_string('settings:llm_provider_baseurl_blank', 'local_ai_course_assistant');
        $apikeynote = !empty($providerinfo['requires_apikey'])
            ? get_string('settings:llm_provider_requires_apikey', 'local_ai_course_assistant')
            : get_string('settings:llm_provider_optional_apikey', 'local_ai_course_assistant');

        $llmprovidersettings->add(new admin_setting_heading(
            'local_ai_course_assistant/llm_provider_heading_' . $providerid,
            $providerinfo['label'],
            get_string('settings:llm_provider_heading_desc', 'local_ai_course_assistant',
                (object)[
                    'baseurl' => $baseurlnote,
                    'apikey' => $apikeynote,
                ]
            )
        ));

        $llmprovidersettings->add(new admin_setting_configpasswordunmask(
            'local_ai_course_assistant/llm_' . $providerid . '_apikey',
            get_string('settings:apikey', 'local_ai_course_assistant'),
            get_string('settings:llm_provider_apikey_desc', 'local_ai_course_assistant'),
            ''
        ));

        $llmprovidersettings->add(new admin_setting_configtext(
            'local_ai_course_assistant/llm_' . $providerid . '_baseurl',
            get_string('settings:apibaseurl', 'local_ai_course_assistant'),
            get_string('settings:llm_provider_baseurl_desc', 'local_ai_course_assistant'),
            $providerinfo['default_baseurl'],
            PARAM_RAW_TRIMMED
        ));

        $llmprovidersettings->add(new admin_setting_configtext(
            'local_ai_course_assistant/llm_' . $providerid . '_models',
            get_string('settings:llm_provider_models', 'local_ai_course_assistant'),
            get_string('settings:llm_provider_models_desc', 'local_ai_course_assistant'),
            implode(', ', $providerinfo['default_models']),
            PARAM_RAW_TRIMMED
        ));
    }

    $ADMIN->add('localplugins', $llmprovidersettings);

    $helper::add_root_category($ADMIN);

    $startersurl = new moodle_url('/local/ai_course_assistant/starter_settings.php');
    $ragadminurl = new moodle_url('/local/ai_course_assistant/rag_admin.php');
    $tokenanalyticsurl = new moodle_url('/local/ai_course_assistant/token_analytics.php');
    $updateadminurl = new moodle_url('/local/ai_course_assistant/update_admin.php');
    $integrityadminurl = new moodle_url('/local/ai_course_assistant/integrity_admin.php');
    $whatsapptesturl = new moodle_url('/local/ai_course_assistant/whatsapp_test.php');

    $displaymodes = [
        'widget' => get_string('settings:display_mode_widget', 'local_ai_course_assistant'),
        'drawer' => get_string('settings:display_mode_drawer', 'local_ai_course_assistant'),
    ];

    $embeddingproviders = [
        'openai' => get_string('settings:embed_provider_openai', 'local_ai_course_assistant'),
        'ollama' => get_string('settings:embed_provider_ollama', 'local_ai_course_assistant'),
    ];

    $offtopicactions = [
        'warn' => get_string('settings:offtopic_action_warn', 'local_ai_course_assistant'),
        'end' => get_string('settings:offtopic_action_end', 'local_ai_course_assistant'),
    ];

    $positions = [
        'bottom-right' => get_string('settings:position_br', 'local_ai_course_assistant'),
        'bottom-left' => get_string('settings:position_bl', 'local_ai_course_assistant'),
        'top-right' => get_string('settings:position_tr', 'local_ai_course_assistant'),
        'top-left' => get_string('settings:position_tl', 'local_ai_course_assistant'),
    ];

    $avatarchoices = [
        'avatar_01' => get_string('settings:avatar_saylor', 'local_ai_course_assistant'),
    ];
    for ($i = 2; $i <= 10; $i++) {
        $num = str_pad($i, 2, '0', STR_PAD_LEFT);
        $avatarchoices["avatar_{$num}"] = "Avatar {$i}";
    }

    $realtimevoices = \local_ai_course_assistant\voice_catalog::display_voices();

    $settings = $helper::create_page($helper::SECTION_MAIN);
    $helper::add_page_chrome($settings, $helper::SECTION_MAIN);

    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/enabled',
        get_string('settings:enabled', 'local_ai_course_assistant'),
        get_string('settings:enabled_desc', 'local_ai_course_assistant'),
        0
    ));

    $settings->add(new admin_setting_heading(
        'local_ai_course_assistant/provider_heading',
        'AI Provider & Conversation',
        get_string('settings:provider_heading_desc_main', 'local_ai_course_assistant')
    ));

    $settings->add(new admin_setting_description(
        'local_ai_course_assistant/llm_management_link',
        get_string('settings:llm_management_title', 'local_ai_course_assistant'),
        html_writer::link(
            $llmmanagementurl,
            get_string('settings:llm_management_button', 'local_ai_course_assistant'),
            ['class' => 'btn btn-secondary btn-sm']
        )
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/allow_student_model_switching',
        get_string('settings:allow_student_model_switching', 'local_ai_course_assistant'),
        get_string('settings:allow_student_model_switching_desc', 'local_ai_course_assistant'),
        0
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_ai_course_assistant/systemprompt',
        get_string('settings:systemprompt', 'local_ai_course_assistant'),
        get_string('settings:systemprompt_desc', 'local_ai_course_assistant'),
        get_string('settings:systemprompt_default', 'local_ai_course_assistant')
    ));

    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/remoteconfigurl',
        get_string('remoteconfigurl', 'local_ai_course_assistant'),
        get_string('remoteconfigurl_desc', 'local_ai_course_assistant'),
        \local_ai_course_assistant\remote_config_manager::DEFAULT_URL,
        PARAM_URL
    ));

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

    $settings->add(new admin_setting_configtext(
        'local_ai_course_assistant/maxhistory',
        get_string('settings:maxhistory', 'local_ai_course_assistant'),
        get_string('settings:maxhistory_desc', 'local_ai_course_assistant'),
        '20',
        PARAM_INT
    ));

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

    $ADMIN->add($helper::CATEGORY_ROOT, $settings);
    $helper::add_group_categories($ADMIN);

    $ragsettings = $helper::create_page($helper::SECTION_RAG);
    $helper::add_page_chrome($ragsettings, $helper::SECTION_RAG);

    $ragsettings->add(new admin_setting_heading(
        'local_ai_course_assistant/rag_heading',
        get_string('settings:rag_heading', 'local_ai_course_assistant'),
        get_string('settings:rag_heading_desc', 'local_ai_course_assistant')
    ));

    $ragsettings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/rag_enabled',
        get_string('settings:rag_enabled', 'local_ai_course_assistant'),
        get_string('settings:rag_enabled_desc', 'local_ai_course_assistant'),
        1
    ));

    $ragsettings->add(new admin_setting_configselect(
        'local_ai_course_assistant/embed_provider',
        get_string('settings:embed_provider', 'local_ai_course_assistant'),
        get_string('settings:embed_provider_desc', 'local_ai_course_assistant'),
        'openai',
        $embeddingproviders
    ));

    $ragsettings->add(new admin_setting_configpasswordunmask(
        'local_ai_course_assistant/embed_apikey',
        get_string('settings:embed_apikey', 'local_ai_course_assistant'),
        get_string('settings:embed_apikey_desc', 'local_ai_course_assistant'),
        ''
    ));

    $ragsettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/embed_model',
        get_string('settings:embed_model', 'local_ai_course_assistant'),
        get_string('settings:embed_model_desc', 'local_ai_course_assistant'),
        'text-embedding-3-small'
    ));

    $ragsettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/embed_apibaseurl',
        get_string('settings:embed_apibaseurl', 'local_ai_course_assistant'),
        get_string('settings:embed_apibaseurl_desc', 'local_ai_course_assistant'),
        ''
    ));

    $ragsettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/embed_dimensions',
        get_string('settings:embed_dimensions', 'local_ai_course_assistant'),
        get_string('settings:embed_dimensions_desc', 'local_ai_course_assistant'),
        '1536',
        PARAM_INT
    ));

    $ragsettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/rag_topk',
        get_string('settings:rag_topk', 'local_ai_course_assistant'),
        get_string('settings:rag_topk_desc', 'local_ai_course_assistant'),
        '5',
        PARAM_INT
    ));

    $ragsettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/rag_chunksize',
        get_string('settings:rag_chunksize', 'local_ai_course_assistant'),
        get_string('settings:rag_chunksize_desc', 'local_ai_course_assistant'),
        '400',
        PARAM_INT
    ));

    $ragsettings->add(new admin_setting_description(
        'local_ai_course_assistant/rag_admin_link',
        get_string('ragadmin:title', 'local_ai_course_assistant'),
        html_writer::link(
            $ragadminurl,
            get_string('ragadmin:view_status', 'local_ai_course_assistant'),
            ['class' => 'btn btn-secondary btn-sm']
        )
    ));

    $ADMIN->add($helper::CATEGORY_SEARCH_AI, $ragsettings);

    $tokenanalyticssettings = $helper::create_page($helper::SECTION_TOKEN_ANALYTICS);
    $helper::add_page_chrome($tokenanalyticssettings, $helper::SECTION_TOKEN_ANALYTICS, false);

    $tokenanalyticssettings->add(new admin_setting_heading(
        'local_ai_course_assistant/token_analytics_heading',
        'Token Analytics',
        'Review aggregate token usage and estimated costs across all courses.'
    ));

    $tokenanalyticssettings->add(new admin_setting_description(
        'local_ai_course_assistant/token_analytics_link',
        get_string('coursesettings:token_usage', 'local_ai_course_assistant'),
        html_writer::link(
            $tokenanalyticsurl,
            get_string('coursesettings:token_usage', 'local_ai_course_assistant'),
            ['class' => 'btn btn-secondary btn-sm']
        )
    ));

    $ADMIN->add($helper::CATEGORY_SEARCH_AI, $tokenanalyticssettings);

    $updatesettings = $helper::create_page($helper::SECTION_UPDATES);
    $helper::add_page_chrome($updatesettings, $helper::SECTION_UPDATES);

    $updatesettings->add(new admin_setting_heading(
        'local_ai_course_assistant/updates_heading',
        get_string('settings:updates_heading', 'local_ai_course_assistant'),
        get_string('settings:updates_heading_desc', 'local_ai_course_assistant')
    ));

    $updatesettings->add(new admin_setting_configpasswordunmask(
        'local_ai_course_assistant/github_token',
        get_string('settings:github_token', 'local_ai_course_assistant'),
        get_string('settings:github_token_desc', 'local_ai_course_assistant'),
        ''
    ));

    $updatesettings->add(new admin_setting_description(
        'local_ai_course_assistant/update_admin_link',
        get_string('updates:title', 'local_ai_course_assistant'),
        html_writer::link(
            $updateadminurl,
            get_string('updates:title', 'local_ai_course_assistant'),
            ['class' => 'btn btn-secondary btn-sm']
        )
    ));

    $ADMIN->add($helper::CATEGORY_MAINTENANCE, $updatesettings);

    $integritysettings = $helper::create_page($helper::SECTION_INTEGRITY);
    $helper::add_page_chrome($integritysettings, $helper::SECTION_INTEGRITY);

    $integritysettings->add(new admin_setting_heading(
        'local_ai_course_assistant/integrity_heading',
        get_string('settings:integrity_heading', 'local_ai_course_assistant'),
        get_string('settings:integrity_heading_desc', 'local_ai_course_assistant')
    ));

    $integritysettings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/integrity_enabled',
        get_string('settings:integrity_enabled', 'local_ai_course_assistant'),
        get_string('settings:integrity_enabled_desc', 'local_ai_course_assistant'),
        1
    ));

    $integritysettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/integrity_email',
        get_string('settings:integrity_email', 'local_ai_course_assistant'),
        get_string('settings:integrity_email_desc', 'local_ai_course_assistant'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $integritysettings->add(new admin_setting_description(
        'local_ai_course_assistant/integrity_admin_link',
        get_string('integrity:title', 'local_ai_course_assistant'),
        html_writer::link(
            $integrityadminurl,
            get_string('integrity:title', 'local_ai_course_assistant'),
            ['class' => 'btn btn-secondary btn-sm']
        )
    ));

    $ADMIN->add($helper::CATEGORY_MODERATION, $integritysettings);

    $offtopicsettings = $helper::create_page($helper::SECTION_OFFTOPIC);
    $helper::add_page_chrome($offtopicsettings, $helper::SECTION_OFFTOPIC);

    $offtopicsettings->add(new admin_setting_heading(
        'local_ai_course_assistant/offtopic_heading',
        get_string('settings:offtopic_heading', 'local_ai_course_assistant'),
        get_string('settings:offtopic_heading_desc', 'local_ai_course_assistant')
    ));

    $offtopicsettings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/offtopic_enabled',
        get_string('settings:offtopic_enabled', 'local_ai_course_assistant'),
        get_string('settings:offtopic_enabled_desc', 'local_ai_course_assistant'),
        1
    ));

    $offtopicsettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/offtopic_max',
        get_string('settings:offtopic_max', 'local_ai_course_assistant'),
        get_string('settings:offtopic_max_desc', 'local_ai_course_assistant'),
        '3',
        PARAM_INT
    ));

    $offtopicsettings->add(new admin_setting_configselect(
        'local_ai_course_assistant/offtopic_action',
        get_string('settings:offtopic_action', 'local_ai_course_assistant'),
        get_string('settings:offtopic_action_desc', 'local_ai_course_assistant'),
        'warn',
        $offtopicactions
    ));

    $offtopicsettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/offtopic_lockout_duration',
        get_string('settings:offtopic_lockout_duration', 'local_ai_course_assistant'),
        get_string('settings:offtopic_lockout_duration_desc', 'local_ai_course_assistant'),
        '30',
        PARAM_INT
    ));

    $ADMIN->add($helper::CATEGORY_MODERATION, $offtopicsettings);

    $wellbeingsettings = $helper::create_page($helper::SECTION_WELLBEING);
    $helper::add_page_chrome($wellbeingsettings, $helper::SECTION_WELLBEING);

    $wellbeingsettings->add(new admin_setting_heading(
        'local_ai_course_assistant/wellbeing_heading',
        get_string('settings:wellbeing_heading', 'local_ai_course_assistant'),
        get_string('settings:wellbeing_heading_desc', 'local_ai_course_assistant')
    ));

    $wellbeingsettings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/wellbeing_enabled',
        get_string('settings:wellbeing_enabled', 'local_ai_course_assistant'),
        get_string('settings:wellbeing_enabled_desc', 'local_ai_course_assistant'),
        1
    ));

    $ADMIN->add($helper::CATEGORY_MODERATION, $wellbeingsettings);

    $studyplansettings = $helper::create_page($helper::SECTION_STUDYPLAN);
    $helper::add_page_chrome($studyplansettings, $helper::SECTION_STUDYPLAN);

    $studyplansettings->add(new admin_setting_heading(
        'local_ai_course_assistant/studyplan_heading',
        get_string('settings:studyplan_heading', 'local_ai_course_assistant'),
        get_string('settings:studyplan_heading_desc', 'local_ai_course_assistant')
    ));

    $studyplansettings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/studyplan_enabled',
        get_string('settings:studyplan_enabled', 'local_ai_course_assistant'),
        get_string('settings:studyplan_enabled_desc', 'local_ai_course_assistant'),
        1
    ));

    $studyplansettings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/reminders_email_enabled',
        get_string('settings:reminders_email_enabled', 'local_ai_course_assistant'),
        get_string('settings:reminders_email_enabled_desc', 'local_ai_course_assistant'),
        1
    ));

    $studyplansettings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/reminders_whatsapp_enabled',
        get_string('settings:reminders_whatsapp_enabled', 'local_ai_course_assistant'),
        get_string('settings:reminders_whatsapp_enabled_desc', 'local_ai_course_assistant'),
        0
    ));

    $studyplansettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/whatsapp_api_url',
        get_string('settings:whatsapp_api_url', 'local_ai_course_assistant'),
        get_string('settings:whatsapp_api_url_desc', 'local_ai_course_assistant'),
        ''
    ));

    $studyplansettings->add(new admin_setting_configpasswordunmask(
        'local_ai_course_assistant/whatsapp_api_token',
        get_string('settings:whatsapp_api_token', 'local_ai_course_assistant'),
        get_string('settings:whatsapp_api_token_desc', 'local_ai_course_assistant'),
        ''
    ));

    $studyplansettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/whatsapp_from_number',
        get_string('settings:whatsapp_from_number', 'local_ai_course_assistant'),
        get_string('settings:whatsapp_from_number_desc', 'local_ai_course_assistant'),
        ''
    ));

    $studyplansettings->add(new admin_setting_configtextarea(
        'local_ai_course_assistant/whatsapp_blocked_countries',
        get_string('settings:whatsapp_blocked_countries', 'local_ai_course_assistant'),
        get_string('settings:whatsapp_blocked_countries_desc', 'local_ai_course_assistant'),
        ''
    ));

    $studyplansettings->add(new admin_setting_description(
        'local_ai_course_assistant/whatsapp_test_link',
        get_string('settings:whatsapp_test_title', 'local_ai_course_assistant'),
        get_string('settings:whatsapp_test_desc', 'local_ai_course_assistant') . '<br><br>' .
            html_writer::link(
                $whatsapptesturl,
                get_string('settings:whatsapp_test_button', 'local_ai_course_assistant'),
                ['class' => 'btn btn-secondary btn-sm']
            )
    ));

    $studyplansettings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/inactivity_reminder_enabled',
        'Inactivity Reminders',
        'Send a weekly email to students who have not accessed their course in the configured number of days.',
        1
    ));

    $studyplansettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/inactivity_threshold_days',
        'Inactivity Threshold (days)',
        'Number of days of inactivity before sending a reminder email.',
        '7',
        PARAM_INT
    ));

    $ADMIN->add($helper::CATEGORY_GENERAL, $studyplansettings);

    $brandingsettings = $helper::create_page($helper::SECTION_BRANDING);
    $helper::add_page_chrome($brandingsettings, $helper::SECTION_BRANDING);

    $brandingsettings->add(new admin_setting_heading(
        'local_ai_course_assistant/branding_heading',
        'Branding',
        'Customize the assistant name and appearance.'
    ));

    $brandingsettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/display_name',
        'Display Name',
        'The full name shown in greetings and the welcome screen (e.g. "SOLA").',
        'SOLA'
    ));

    $brandingsettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/short_name',
        'Short Name',
        'Short name shown in the header bar and compact UI elements.',
        'SOLA'
    ));

    $brandingsettings->add(new admin_setting_configtextarea(
        'local_ai_course_assistant/welcome_message',
        'Welcome Screen Message',
        'Message shown on the first-visit welcome screen. Use <code>{{firstname}}</code> for the student\'s first name and <code>{{coursename}}</code> for the course name. Leave blank for the default.',
        ''
    ));

    $brandingsettings->add(new admin_setting_configtextarea(
        'local_ai_course_assistant/chat_greeting',
        'Chat Greeting',
        'Greeting message shown when the chat window opens. Use <code>{{firstname}}</code> for the student\'s first name and <code>{{coursename}}</code> for the course name. Leave blank for the default.',
        ''
    ));

    $brandingsettings->add(new admin_setting_configselect(
        'local_ai_course_assistant/position',
        get_string('settings:position', 'local_ai_course_assistant'),
        get_string('settings:position_desc', 'local_ai_course_assistant'),
        'bottom-right',
        $positions
    ));

    $brandingsettings->add(new admin_setting_configselect(
        'local_ai_course_assistant/avatar',
        get_string('settings:avatar', 'local_ai_course_assistant'),
        get_string('settings:avatar_desc', 'local_ai_course_assistant'),
        'avatar_01',
        $avatarchoices
    ));

    $brandingsettings->add(new admin_setting_configcolourpicker(
        'local_ai_course_assistant/avatar_color',
        get_string('settings:avatar_color', 'local_ai_course_assistant'),
        get_string('settings:avatar_color_desc', 'local_ai_course_assistant'),
        '#173140'
    ));

    $brandingsettings->add(new admin_setting_configcolourpicker(
        'local_ai_course_assistant/avatar_fill',
        get_string('settings:avatar_fill', 'local_ai_course_assistant'),
        get_string('settings:avatar_fill_desc', 'local_ai_course_assistant'),
        '#ffffff'
    ));

    $brandingsettings->add(new admin_setting_configcolourpicker(
        'local_ai_course_assistant/starter_icon_color',
        get_string('settings:starter_icon_color', 'local_ai_course_assistant'),
        get_string('settings:starter_icon_color_desc', 'local_ai_course_assistant'),
        '#173140'
    ));

    $ADMIN->add($helper::CATEGORY_GENERAL, $brandingsettings);

    $faqsettings = $helper::create_page($helper::SECTION_FAQ);
    $helper::add_page_chrome($faqsettings, $helper::SECTION_FAQ);

    $faqsettings->add(new admin_setting_heading(
        'local_ai_course_assistant/faq_heading',
        get_string('settings:faq_heading', 'local_ai_course_assistant'),
        get_string('settings:faq_heading_desc', 'local_ai_course_assistant')
    ));

    $faqsettings->add(new admin_setting_configtextarea(
        'local_ai_course_assistant/faq_content',
        get_string('settings:faq_content', 'local_ai_course_assistant'),
        get_string('settings:faq_content_desc', 'local_ai_course_assistant'),
        ''
    ));

    $faqsettings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/zendesk_enabled',
        get_string('settings:zendesk_enabled', 'local_ai_course_assistant'),
        get_string('settings:zendesk_enabled_desc', 'local_ai_course_assistant'),
        0
    ));

    $faqsettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/zendesk_subdomain',
        get_string('settings:zendesk_subdomain', 'local_ai_course_assistant'),
        get_string('settings:zendesk_subdomain_desc', 'local_ai_course_assistant'),
        ''
    ));

    $faqsettings->add(new admin_setting_configtext(
        'local_ai_course_assistant/zendesk_email',
        get_string('settings:zendesk_email', 'local_ai_course_assistant'),
        get_string('settings:zendesk_email_desc', 'local_ai_course_assistant'),
        ''
    ));

    $faqsettings->add(new admin_setting_configpasswordunmask(
        'local_ai_course_assistant/zendesk_token',
        get_string('settings:zendesk_token', 'local_ai_course_assistant'),
        get_string('settings:zendesk_token_desc', 'local_ai_course_assistant'),
        ''
    ));

    $ADMIN->add($helper::CATEGORY_GENERAL, $faqsettings);

    $voicesettings = $helper::create_page($helper::SECTION_VOICE);
    $helper::add_page_chrome($voicesettings, $helper::SECTION_VOICE);

    $voicesettings->add(new admin_setting_heading(
        'local_ai_course_assistant/realtime_heading',
        get_string('settings:realtime_heading', 'local_ai_course_assistant'),
        get_string('settings:realtime_heading_desc', 'local_ai_course_assistant')
    ));

    $voicesettings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/realtime_enabled',
        get_string('settings:realtime_enabled', 'local_ai_course_assistant'),
        get_string('settings:realtime_enabled_desc', 'local_ai_course_assistant'),
        0
    ));

    $voicesettings->add(new admin_setting_configpasswordunmask(
        'local_ai_course_assistant/realtime_apikey',
        get_string('settings:realtime_apikey', 'local_ai_course_assistant'),
        get_string('settings:realtime_apikey_desc', 'local_ai_course_assistant'),
        ''
    ));

    $voicesettings->add(new admin_setting_configselect(
        'local_ai_course_assistant/realtime_voice',
        get_string('settings:realtime_voice', 'local_ai_course_assistant'),
        get_string('settings:realtime_voice_desc', 'local_ai_course_assistant'),
        \local_ai_course_assistant\voice_catalog::DEFAULT_VOICE,
        $realtimevoices
    ));

    $ADMIN->add($helper::CATEGORY_SEARCH_AI, $voicesettings);

    $debuggingsettings = $helper::create_page($helper::SECTION_DEBUGGING);
    $helper::add_page_chrome($debuggingsettings, $helper::SECTION_DEBUGGING);

    $debuggingsettings->add(new admin_setting_heading(
        'local_ai_course_assistant/debug_heading',
        get_string('settings:debug_heading', 'local_ai_course_assistant'),
        get_string('settings:debug_heading_desc', 'local_ai_course_assistant')
    ));

    $debuggingsettings->add(new admin_setting_configcheckbox(
        'local_ai_course_assistant/context_debug_enabled',
        get_string('settings:context_debug_enabled', 'local_ai_course_assistant'),
        get_string('settings:context_debug_enabled_desc', 'local_ai_course_assistant'),
        0
    ));

    $ADMIN->add($helper::CATEGORY_GENERAL, $debuggingsettings);

    $ADMIN->add($helper::CATEGORY_SEARCH_AI, new admin_externalpage(
        'local_ai_course_assistant_starters',
        get_string('starters:admin_title', 'local_ai_course_assistant'),
        $startersurl,
        'moodle/site:config'
    ));

    $ADMIN->add($helper::CATEGORY_SEARCH_AI, new admin_externalpage(
        'local_ai_course_assistant_ragadmin',
        get_string('ragadmin:title', 'local_ai_course_assistant'),
        $ragadminurl,
        'moodle/site:config'
    ));

    $ADMIN->add($helper::CATEGORY_MAINTENANCE, new admin_externalpage(
        'local_ai_course_assistant_updateadmin',
        get_string('updates:title', 'local_ai_course_assistant'),
        $updateadminurl,
        'moodle/site:config'
    ));

    $ADMIN->add($helper::CATEGORY_MODERATION, new admin_externalpage(
        'local_ai_course_assistant_integrityadmin',
        get_string('integrity:title', 'local_ai_course_assistant'),
        $integrityadminurl,
        'moodle/site:config'
    ));

    $ADMIN->add($helper::CATEGORY_MAINTENANCE, new admin_externalpage(
        'local_ai_course_assistant_whatsapptest',
        get_string('whatsapptest:title', 'local_ai_course_assistant'),
        $whatsapptesturl,
        'moodle/site:config'
    ));
}
