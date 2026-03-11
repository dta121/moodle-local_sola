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

namespace local_ai_course_assistant;

defined('MOODLE_INTERNAL') || die();

/**
 * Central manager for SOLA LLM provider configuration.
 *
 * @package    local_ai_course_assistant
 * @copyright  2025 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class llm_provider_manager {

    /**
     * Provider metadata and defaults.
     *
     * @return array
     */
    public static function get_catalog(): array {
        return [
            'openai' => [
                'label' => get_string('settings:provider_openai', 'local_ai_course_assistant'),
                'default_baseurl' => 'https://api.openai.com/v1',
                'default_models' => ['gpt-4o-mini'],
                'requires_apikey' => true,
            ],
            'claude' => [
                'label' => get_string('settings:provider_claude', 'local_ai_course_assistant'),
                'default_baseurl' => 'https://api.anthropic.com/v1',
                'default_models' => ['claude-haiku-4-5-20251001'],
                'requires_apikey' => true,
            ],
            'deepseek' => [
                'label' => get_string('settings:provider_deepseek', 'local_ai_course_assistant'),
                'default_baseurl' => 'https://api.deepseek.com/v1',
                'default_models' => ['deepseek-chat'],
                'requires_apikey' => true,
            ],
            'ollama' => [
                'label' => get_string('settings:provider_ollama', 'local_ai_course_assistant'),
                'default_baseurl' => 'http://localhost:11434',
                'default_models' => ['llama3'],
                'requires_apikey' => false,
            ],
            'minimax' => [
                'label' => get_string('settings:provider_minimax', 'local_ai_course_assistant'),
                'default_baseurl' => 'https://api.minimax.io/v1',
                'default_models' => ['MiniMax-Text-01'],
                'requires_apikey' => true,
            ],
            'custom' => [
                'label' => get_string('settings:provider_custom', 'local_ai_course_assistant'),
                'default_baseurl' => '',
                'default_models' => [],
                'requires_apikey' => true,
            ],
        ];
    }

    /**
     * Build a plugin config key for a provider-specific setting.
     *
     * @param string $provider
     * @param string $field
     * @return string
     */
    private static function provider_key(string $provider, string $field): string {
        return 'llm_' . $provider . '_' . $field;
    }

    /**
     * Parse a comma-separated model list.
     *
     * @param string $raw
     * @param array $defaults
     * @return array
     */
    private static function parse_models(string $raw, array $defaults = []): array {
        $items = preg_split('/[\r\n,]+/', $raw) ?: [];
        $models = [];
        foreach ($items as $item) {
            $model = trim((string)$item);
            if ($model === '' || in_array($model, $models, true)) {
                continue;
            }
            $models[] = $model;
        }
        if (!empty($models)) {
            return $models;
        }
        return array_values(array_filter(array_map('trim', $defaults)));
    }

    /**
     * Normalize a provider base URL.
     *
     * @param string $provider
     * @param string $baseurl
     * @return string
     */
    private static function normalize_baseurl(string $provider, string $baseurl): string {
        $baseurl = rtrim(trim($baseurl), '/');
        if ($baseurl === '') {
            return '';
        }

        if (in_array($provider, ['openai', 'claude', 'deepseek', 'minimax'], true)) {
            if (!preg_match('#/v[0-9]+$#', $baseurl)) {
                $baseurl .= '/v1';
            }
        }

        return $baseurl;
    }

    /**
     * Legacy provider ID.
     *
     * @return string
     */
    private static function get_legacy_provider(): string {
        $legacyprovider = trim((string)(get_config('local_ai_course_assistant', 'provider') ?: ''));
        return array_key_exists($legacyprovider, self::get_catalog()) ? $legacyprovider : 'openai';
    }

    /**
     * Get saved configuration for a provider.
     *
     * Falls back to the legacy single-provider settings for the current legacy provider
     * so existing installations keep working after the refactor.
     *
     * @param string $provider
     * @return array
     */
    public static function get_provider_config(string $provider): array {
        $catalog = self::get_catalog();
        if (!isset($catalog[$provider])) {
            throw new \coding_exception('Unknown LLM provider: ' . $provider);
        }

        $meta = $catalog[$provider];
        $legacyprovider = self::get_legacy_provider();
        $islegacyprovider = $legacyprovider === $provider;

        $apikey = trim((string)(get_config('local_ai_course_assistant', self::provider_key($provider, 'apikey')) ?: ''));
        if ($apikey === '' && $islegacyprovider) {
            $apikey = trim((string)(get_config('local_ai_course_assistant', 'apikey') ?: ''));
        }

        $baseurl = trim((string)(get_config('local_ai_course_assistant', self::provider_key($provider, 'baseurl')) ?: ''));
        if ($baseurl === '' && $islegacyprovider) {
            $baseurl = trim((string)(get_config('local_ai_course_assistant', 'apibaseurl') ?: ''));
        }
        if ($baseurl === '') {
            $baseurl = $meta['default_baseurl'];
        }
        $baseurl = self::normalize_baseurl($provider, $baseurl);

        $modelsraw = trim((string)(get_config('local_ai_course_assistant', self::provider_key($provider, 'models')) ?: ''));
        if ($modelsraw === '' && $islegacyprovider) {
            $modelsraw = trim((string)(get_config('local_ai_course_assistant', 'model') ?: ''));
        }
        $models = self::parse_models($modelsraw, $meta['default_models']);

        return [
            'id' => $provider,
            'label' => $meta['label'],
            'apikey' => $apikey,
            'baseurl' => $baseurl,
            'models' => $models,
            'requires_apikey' => !empty($meta['requires_apikey']),
            'default_baseurl' => $meta['default_baseurl'],
            'default_models' => $meta['default_models'],
        ];
    }

    /**
     * Return whether a provider configuration is active.
     *
     * @param array $config
     * @return bool
     */
    private static function is_active_config(array $config): bool {
        if (empty($config['baseurl']) || empty($config['models'])) {
            return false;
        }
        if (!empty($config['requires_apikey']) && trim((string)($config['apikey'] ?? '')) === '') {
            return false;
        }
        return true;
    }

    /**
     * Return all active provider configs.
     *
     * @return array
     */
    public static function get_active_provider_configs(): array {
        $active = [];
        foreach (array_keys(self::get_catalog()) as $provider) {
            $config = self::get_provider_config($provider);
            if (self::is_active_config($config)) {
                $active[$provider] = $config;
            }
        }
        return $active;
    }

    /**
     * Return options for the provider default dropdown.
     *
     * @return array
     */
    public static function get_provider_options(): array {
        $active = self::get_active_provider_configs();
        $source = !empty($active) ? $active : self::get_catalog();
        $options = [];
        foreach ($source as $provider => $meta) {
            $options[$provider] = $meta['label'];
        }
        return $options;
    }

    /**
     * Return options for the supported-model default dropdown.
     *
     * @return array
     */
    public static function get_model_options(): array {
        $options = [];
        foreach (self::get_active_provider_configs() as $config) {
            foreach ($config['models'] as $model) {
                $options[$model] = $config['label'] . ' - ' . $model;
            }
        }

        if (empty($options)) {
            $options[''] = get_string('settings:llm_default_model_none', 'local_ai_course_assistant');
        }

        return $options;
    }

    /**
     * System default provider.
     *
     * @return string
     */
    public static function get_system_default_provider(): string {
        $provider = trim((string)(get_config('local_ai_course_assistant', 'llm_default_provider') ?: ''));
        if (array_key_exists($provider, self::get_catalog())) {
            return $provider;
        }
        return self::get_legacy_provider();
    }

    /**
     * System default model for a provider.
     *
     * @param string|null $provider
     * @return string
     */
    public static function get_system_default_model(?string $provider = null): string {
        $provider = $provider ?: self::get_system_default_provider();
        $config = self::get_provider_config($provider);
        $storedmodel = trim((string)(get_config('local_ai_course_assistant', 'llm_default_model') ?: ''));
        if ($storedmodel !== '' && in_array($storedmodel, $config['models'], true)) {
            return $storedmodel;
        }

        $legacyprovider = self::get_legacy_provider();
        $legacymodel = trim((string)(get_config('local_ai_course_assistant', 'model') ?: ''));
        if ($legacyprovider === $provider && $legacymodel !== '' && in_array($legacymodel, $config['models'], true)) {
            return $legacymodel;
        }

        if (!empty($config['models'])) {
            return $config['models'][0];
        }

        return $config['default_models'][0] ?? '';
    }

    /**
     * Resolve the default provider/model against currently active configurations.
     *
     * @return array
     */
    public static function get_active_default_selection(): array {
        $active = self::get_active_provider_configs();
        if (empty($active)) {
            throw new \moodle_exception('chat:error_notconfigured', 'local_ai_course_assistant');
        }

        $provider = self::get_system_default_provider();
        if (!isset($active[$provider])) {
            $provider = array_key_first($active);
        }

        $model = self::get_system_default_model($provider);
        if ($model === '' || !in_array($model, $active[$provider]['models'], true)) {
            $model = $active[$provider]['models'][0];
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'config' => $active[$provider],
        ];
    }

    /**
     * Resolve a requested provider/model pair with automatic fallback to the system default.
     *
     * @param string|null $requestedprovider
     * @param string|null $requestedmodel
     * @return array
     */
    public static function resolve_selection(?string $requestedprovider = null, ?string $requestedmodel = null): array {
        $active = self::get_active_provider_configs();
        if (empty($active)) {
            throw new \moodle_exception('chat:error_notconfigured', 'local_ai_course_assistant');
        }

        $default = self::get_active_default_selection();
        $provider = trim((string)$requestedprovider);
        $model = trim((string)$requestedmodel);
        $usingfallback = false;
        $fallbackreason = '';

        if ($provider === '' || !isset($active[$provider])) {
            $provider = $default['provider'];
            $model = $default['model'];
            $usingfallback = trim((string)$requestedprovider) !== '' || trim((string)$requestedmodel) !== '';
            $fallbackreason = 'provider_unavailable';
        } else if ($model === '' || !in_array($model, $active[$provider]['models'], true)) {
            $model = ($provider === $default['provider'] && in_array($default['model'], $active[$provider]['models'], true))
                ? $default['model']
                : $active[$provider]['models'][0];
            $usingfallback = trim((string)$requestedmodel) !== '';
            $fallbackreason = 'model_unavailable';
        }

        $config = $active[$provider];

        return [
            'provider' => $provider,
            'model' => $model,
            'apikey' => $config['apikey'],
            'apibaseurl' => $config['baseurl'],
            'default_provider' => $default['provider'],
            'default_model' => $default['model'],
            'using_fallback' => $usingfallback,
            'fallback_reason' => $fallbackreason,
        ];
    }

    /**
     * Frontend model-switch configuration.
     *
     * @param bool $enabled
     * @param string $userrole
     * @return array
     */
    public static function get_frontend_options(bool $enabled, string $userrole): array {
        $switchenabled = $enabled && $userrole === 'student';
        $options = [
            'enabled' => $switchenabled,
            'providers' => [],
            'defaultProvider' => '',
            'defaultModel' => '',
        ];

        $active = self::get_active_provider_configs();
        if ($switchenabled) {
            foreach ($active as $provider => $config) {
                $options['providers'][] = [
                    'id' => $provider,
                    'label' => $config['label'],
                    'models' => array_values($config['models']),
                ];
            }
        }

        if (!empty($active)) {
            $default = self::get_active_default_selection();
            $options['defaultProvider'] = $default['provider'];
            $options['defaultModel'] = $default['model'];
        }

        return $options;
    }

    /**
     * Get an OpenAI API key suitable for TTS/transcription/realtime voice.
     *
     * @return string
     */
    public static function get_openai_voice_key(): string {
        $realtimekey = trim((string)(get_config('local_ai_course_assistant', 'realtime_apikey') ?: ''));
        if ($realtimekey !== '') {
            return $realtimekey;
        }

        $openaiconfig = self::get_provider_config('openai');
        return trim((string)($openaiconfig['apikey'] ?? ''));
    }
}
