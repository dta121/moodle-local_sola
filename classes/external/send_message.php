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

namespace local_ai_course_assistant\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_ai_course_assistant\conversation_manager;
use local_ai_course_assistant\context_builder;
use local_ai_course_assistant\provider\base_provider;
use local_ai_course_assistant\content_indexer;
use local_ai_course_assistant\rag_retriever;

/**
 * Send a message to the AI tutor (non-streaming fallback).
 *
 * @package    local_ai_course_assistant
 * @copyright  2025 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'message' => new external_value(PARAM_RAW, 'User message'),
            'provider' => new external_value(PARAM_ALPHANUMEXT, 'Requested provider ID', VALUE_DEFAULT, ''),
            'model' => new external_value(PARAM_RAW_TRIMMED, 'Requested model name', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $courseid
     * @param string $message
     * @param string $provider
     * @param string $model
     * @return array
     */
    public static function execute(int $courseid, string $message, string $provider = '', string $model = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'message' => $message,
            'provider' => $provider,
            'model' => $model,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/ai_course_assistant:use', $context);

        $userid = $USER->id;
        $conv = conversation_manager::get_or_create_conversation($userid, $params['courseid']);

        // Save user message.
        conversation_manager::add_message($conv->id, $userid, $params['courseid'], 'user', $params['message']);

        // RAG retrieval.
        $retrievedchunks = [];
        if (get_config('local_ai_course_assistant', 'rag_enabled')) {
            try {
                if (!content_indexer::is_course_indexed($params['courseid'])) {
                    content_indexer::index_course($params['courseid']);
                }
                $topk = (int) (get_config('local_ai_course_assistant', 'rag_topk') ?: 5);
                $retrievedchunks = rag_retriever::retrieve($params['courseid'], $params['message'], $topk);
            } catch (\Exception $e) {
                debugging('RAG retrieval failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $retrievedchunks = [];
            }
        }

        // Build context and get history.
        $lang = get_config('local_ai_course_assistant', 'english_lock_course_' . $params['courseid']) ? 'en' : '';
        $systemprompt = context_builder::build_system_prompt($params['courseid'], $userid, $lang, $retrievedchunks);
        $history = conversation_manager::get_history_for_api($conv->id);
        $responseoptions = [];
        $maxtokens = (int) get_config('local_ai_course_assistant', 'max_tokens');
        if ($maxtokens > 0) {
            $responseoptions['max_tokens'] = $maxtokens;
        }

        // Get AI response, retrying once on the system default provider/model if a custom selection fails.
        $runtimeconfig = base_provider::resolve_runtime_config(
            $params['courseid'],
            $params['provider'],
            $params['model']
        );
        $usedruntime = $runtimeconfig;

        try {
            $providerinstance = base_provider::create_from_runtime_config($runtimeconfig);
            $response = $providerinstance->chat_completion($systemprompt, $history, $responseoptions);
        } catch (\Throwable $e) {
            $fallbackconfig = base_provider::get_fallback_runtime_config($params['courseid'], $runtimeconfig);
            if ($fallbackconfig === null) {
                throw $e;
            }

            debugging(
                'LLM selection failed for send_message; retrying with the system default provider.',
                DEBUG_DEVELOPER
            );
            $providerinstance = base_provider::create_from_runtime_config($fallbackconfig);
            $response = $providerinstance->chat_completion($systemprompt, $history, $responseoptions);
            $usedruntime = $fallbackconfig;
        }

        // Save assistant response.
        conversation_manager::add_message(
            $conv->id,
            $userid,
            $params['courseid'],
            'assistant',
            $response,
            0,
            $usedruntime['provider'],
            null,
            null,
            $usedruntime['model']
        );

        return [
            'response' => $response,
            'success' => true,
        ];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'response' => new external_value(PARAM_RAW, 'AI response'),
            'success' => new external_value(PARAM_BOOL, 'Success flag'),
        ]);
    }
}
