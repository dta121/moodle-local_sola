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

namespace local_ai_course_assistant\provider;

/**
 * Base provider with shared configuration and cURL helpers.
 *
 * @package    local_ai_course_assistant
 * @copyright  2025 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_provider implements provider_interface {

    /** @var int Maximum error response excerpt to keep from streaming requests */
    private const STREAM_ERROR_EXCERPT_LIMIT = 8192;

    /** @var string API key */
    protected string $apikey;

    /** @var string Model name */
    protected string $model;

    /** @var string Base URL */
    protected string $baseurl;

    /** @var float Temperature */
    protected float $temperature;

    /**
     * Constructor. Reads provider config, with optional runtime overrides.
     *
     * @param array $overrides Optional config overrides.
     */
    public function __construct(array $overrides = []) {
        $rawkey = !empty($overrides['apikey'])
            ? $overrides['apikey']
            : (get_config('local_ai_course_assistant', 'apikey') ?: '');
        // Strip any descriptive label accidentally saved before the key
        // e.g. "OpenAI API Key sk-proj-..." -> "sk-proj-...".
        $rawkey = trim($rawkey);
        $parts = preg_split('/\s+/', $rawkey);
        $this->apikey = count($parts) > 1 ? trim((string)end($parts)) : $rawkey;

        $adminmodel = get_config('local_ai_course_assistant', 'model');
        $this->model = !empty($overrides['model'])
            ? $overrides['model']
            : (!empty($adminmodel)
                ? $adminmodel
                : (\local_ai_course_assistant\remote_config_manager::get_value('model_default') ?: $this->get_default_model()));

        $this->temperature = isset($overrides['temperature']) && $overrides['temperature'] !== ''
            ? (float)$overrides['temperature']
            : (float)(get_config('local_ai_course_assistant', 'temperature') ?: 0.7);

        $configurl = !empty($overrides['apibaseurl'])
            ? $overrides['apibaseurl']
            : get_config('local_ai_course_assistant', 'apibaseurl');
        $this->baseurl = !empty($configurl) ? rtrim((string)$configurl, '/') : $this->get_default_base_url();
    }

    /**
     * Get the default model for this provider.
     *
     * @return string
     */
    abstract protected function get_default_model(): string;

    /**
     * Get the default base URL for this provider.
     *
     * @return string
     */
    abstract protected function get_default_base_url(): string;

    /**
     * Make a non-streaming HTTP POST request using Moodle's curl class.
     *
     * @param string $url Full URL.
     * @param array $headers HTTP headers.
     * @param string $body JSON body.
     * @return string Response body.
     * @throws \moodle_exception On HTTP errors.
     */
    protected function http_post(string $url, array $headers, string $body): string {
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_HTTPHEADER' => $headers,
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 120,
        ]);

        $response = $curl->post($url, $body);
        $httpcode = $curl->get_info()['http_code'] ?? 0;

        $this->check_http_error($httpcode, $response);

        return $response;
    }

    /**
     * Make a streaming HTTP POST request using raw curl with WRITEFUNCTION.
     *
     * @param string $url Full URL.
     * @param array $headers HTTP headers.
     * @param string $body JSON body.
     * @param callable $writecallback Called with each chunk of response data.
     * @throws \moodle_exception On HTTP errors.
     */
    protected function http_post_stream(string $url, array $headers, string $body, callable $writecallback): void {
        $ch = curl_init();
        $responseexcerpt = '';

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_WRITEFUNCTION => function($ch, $data) use ($writecallback, &$responseexcerpt) {
                if (strlen($responseexcerpt) < self::STREAM_ERROR_EXCERPT_LIMIT) {
                    $remaining = self::STREAM_ERROR_EXCERPT_LIMIT - strlen($responseexcerpt);
                    $responseexcerpt .= substr($data, 0, $remaining);
                }
                $writecallback($data);
                return strlen($data);
            },
        ]);

        \local_ai_course_assistant\curl_helper::apply_moodle_defaults($ch);

        curl_exec($ch);

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \moodle_exception('chat:error', 'local_ai_course_assistant', '', null, $error);
        }

        if ($httpcode >= 400) {
            $this->check_http_error($httpcode, trim($responseexcerpt));
        }
    }

    /**
     * Check HTTP status code and throw appropriate exception.
     *
     * @param int $httpcode
     * @param string $response
     * @throws \moodle_exception
     */
    protected function check_http_error(int $httpcode, string $response): void {
        if ($httpcode >= 200 && $httpcode < 300) {
            return;
        }

        $errordetail = $this->extract_error_detail($response);

        if ($httpcode === 401 || $httpcode === 403) {
            throw new \moodle_exception('chat:error_auth', 'local_ai_course_assistant');
        }

        if ($httpcode === 429) {
            throw new \moodle_exception('chat:error_ratelimit', 'local_ai_course_assistant');
        }

        if ($httpcode >= 500) {
            throw new \moodle_exception('chat:error_unavailable', 'local_ai_course_assistant');
        }

        throw new \moodle_exception(
            'chat:error',
            'local_ai_course_assistant',
            '',
            null,
            "HTTP {$httpcode}: {$errordetail}"
        );
    }

    /**
     * Extract a readable error detail from an API response body.
     *
     * @param string $response
     * @return string
     */
    protected function extract_error_detail(string $response): string {
        $response = trim($response);
        if ($response === '') {
            return '';
        }

        $data = json_decode($response, true);
        if (is_array($data)) {
            $message = trim((string)($data['error']['message'] ?? $data['message'] ?? ''));
            if ($message !== '') {
                return $message;
            }
        }

        return $response;
    }

    /**
     * Get token usage from the last streaming call.
     *
     * Default implementation returns null. Providers that support usage reporting
     * (OpenAI-compatible with stream_options, Claude) override this.
     *
     * @return array|null
     */
    public function get_last_token_usage(): ?array {
        return null;
    }

    /**
     * Resolve the runtime provider config for a course request.
     *
     * @param int $courseid
     * @param string|null $requestedprovider
     * @param string|null $requestedmodel
     * @return array
     */
    public static function resolve_runtime_config(
        int $courseid = 0,
        ?string $requestedprovider = null,
        ?string $requestedmodel = null
    ): array {
        $courseconfig = \local_ai_course_assistant\course_config_manager::get_effective_config($courseid);
        $selection = \local_ai_course_assistant\llm_provider_manager::resolve_selection(
            $requestedprovider,
            $requestedmodel
        );

        return [
            'provider' => $selection['provider'],
            'apikey' => $selection['apikey'],
            'model' => $selection['model'],
            'apibaseurl' => $selection['apibaseurl'],
            'temperature' => $courseconfig['temperature'] ?? (get_config('local_ai_course_assistant', 'temperature') ?: '0.7'),
            'default_provider' => $selection['default_provider'],
            'default_model' => $selection['default_model'],
            'using_fallback' => $selection['using_fallback'],
            'fallback_reason' => $selection['fallback_reason'],
        ];
    }

    /**
     * Return the runtime config for the system default provider/model, when different.
     *
     * @param int $courseid
     * @param array $runtimeconfig
     * @return array|null
     */
    public static function get_fallback_runtime_config(int $courseid, array $runtimeconfig): ?array {
        $defaultprovider = trim((string)($runtimeconfig['default_provider'] ?? ''));
        $defaultmodel = trim((string)($runtimeconfig['default_model'] ?? ''));
        $provider = trim((string)($runtimeconfig['provider'] ?? ''));
        $model = trim((string)($runtimeconfig['model'] ?? ''));

        if ($defaultprovider === '' || ($provider === $defaultprovider && $model === $defaultmodel)) {
            return null;
        }

        return self::resolve_runtime_config($courseid, $defaultprovider, $defaultmodel);
    }

    /**
     * Create a provider instance from a resolved runtime config array.
     *
     * @param array $runtimeconfig
     * @return provider_interface
     */
    public static function create_from_runtime_config(array $runtimeconfig): provider_interface {
        $provider = trim((string)($runtimeconfig['provider'] ?? ''));

        switch ($provider) {
            case 'claude':
                return new claude_provider($runtimeconfig);
            case 'openai':
                return new openai_provider($runtimeconfig);
            case 'ollama':
                return new ollama_provider($runtimeconfig);
            case 'minimax':
                return new minimax_provider($runtimeconfig);
            case 'deepseek':
                return new deepseek_provider($runtimeconfig);
            case 'custom':
                return new custom_provider($runtimeconfig);
            default:
                throw new \moodle_exception('chat:error_notconfigured', 'local_ai_course_assistant');
        }
    }

    /**
     * Factory method to create a provider from plugin config, with optional user selection.
     *
     * @param int $courseid Course ID to look up per-course overrides (0 = use global only).
     * @param string|null $requestedprovider
     * @param string|null $requestedmodel
     * @return provider_interface
     * @throws \moodle_exception If provider is not configured.
     */
    public static function create_from_config(
        int $courseid = 0,
        ?string $requestedprovider = null,
        ?string $requestedmodel = null
    ): provider_interface {
        $runtimeconfig = self::resolve_runtime_config($courseid, $requestedprovider, $requestedmodel);
        return self::create_from_runtime_config($runtimeconfig);
    }
}
