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
 * Daily integrity checks for SOLA.
 *
 * @package    local_ai_course_assistant
 * @copyright  2025 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class integrity_checker {

    /** Config key for last integrity results. */
    private const CONFIG_RESULTS = 'integrity_last_results';

    /**
     * Run all integrity checks.
     *
     * @param bool $sendalerts
     * @return array
     */
    public static function run_all(bool $sendalerts = false): array {
        $definitions = [
            ['key' => 'php_syntax', 'label' => 'PHP syntax', 'method' => 'check_php_syntax'],
            ['key' => 'js_builds', 'label' => 'JS builds', 'method' => 'check_js_builds'],
            ['key' => 'lang_files', 'label' => 'Language files', 'method' => 'check_lang_files'],
            ['key' => 'api_keys', 'label' => 'API configuration', 'method' => 'check_api_configuration'],
            ['key' => 'rag_tables', 'label' => 'RAG configuration', 'method' => 'check_rag_tables'],
            ['key' => 'version_consistency', 'label' => 'Version consistency', 'method' => 'check_version_consistency'],
            ['key' => 'key_classes', 'label' => 'Key classes', 'method' => 'check_key_classes'],
            ['key' => 'templates', 'label' => 'Templates', 'method' => 'check_templates'],
            ['key' => 'db_tables', 'label' => 'Database tables', 'method' => 'check_db_tables'],
            ['key' => 'sse_endpoint', 'label' => 'SSE endpoint', 'method' => 'check_sse_endpoint'],
        ];

        $checks = [];
        foreach ($definitions as $definition) {
            try {
                $checks[] = self::{$definition['method']}();
            } catch (\Throwable $e) {
                $checks[] = self::make_check(
                    $definition['key'],
                    $definition['label'],
                    'fail',
                    $e->getMessage()
                );
            }
        }

        $passed = count(array_filter($checks, function(array $check): bool {
            return $check['status'] === 'pass';
        }));
        $failed = count(array_filter($checks, function(array $check): bool {
            return $check['status'] === 'fail';
        }));
        $warnings = count(array_filter($checks, function(array $check): bool {
            return $check['status'] === 'warn';
        }));

        $results = [
            'run_at' => time(),
            'overall_status' => $failed > 0 ? 'fail' : ($warnings > 0 ? 'warn' : 'pass'),
            'passed' => $passed,
            'failed' => $failed,
            'warnings' => $warnings,
            'checks' => $checks,
        ];

        set_config(self::CONFIG_RESULTS, json_encode($results), 'local_ai_course_assistant');

        if ($sendalerts && $failed > 0) {
            self::send_failure_alert($results);
        }

        return $results;
    }

    /**
     * Get the last stored integrity results.
     *
     * @return array
     */
    public static function get_last_results(): array {
        $json = get_config('local_ai_course_assistant', self::CONFIG_RESULTS);
        if (empty($json)) {
            return [];
        }

        $results = json_decode((string)$json, true);
        return is_array($results) ? $results : [];
    }

    /**
     * Check that plugin PHP files pass CLI linting.
     *
     * @return array
     */
    private static function check_php_syntax(): array {
        $files = self::list_files_by_extension(plugin_updater::plugin_root(), 'php');
        $lint = self::lint_files($files);
        return self::make_check('php_syntax', 'PHP syntax', $lint['status'], $lint['details']);
    }

    /**
     * Check AMD build artifacts.
     *
     * @return array
     */
    private static function check_js_builds(): array {
        $srcdir = plugin_updater::plugin_root() . DIRECTORY_SEPARATOR . 'amd' . DIRECTORY_SEPARATOR . 'src';
        $builddir = plugin_updater::plugin_root() . DIRECTORY_SEPARATOR . 'amd' . DIRECTORY_SEPARATOR . 'build';
        $srcfiles = glob($srcdir . DIRECTORY_SEPARATOR . '*.js') ?: [];

        if (empty($srcfiles)) {
            return self::make_check('js_builds', 'JS builds', 'warn', 'No AMD source files were found.');
        }

        $missing = [];
        $stale = [];
        foreach ($srcfiles as $srcfile) {
            $buildfile = $builddir . DIRECTORY_SEPARATOR . basename($srcfile, '.js') . '.min.js';
            if (!is_file($buildfile)) {
                $missing[] = basename($buildfile);
                continue;
            }
            if (filemtime($buildfile) < filemtime($srcfile)) {
                $stale[] = basename($buildfile);
            }
        }

        if (!empty($missing) || !empty($stale)) {
            $parts = [];
            if (!empty($missing)) {
                $parts[] = 'Missing: ' . implode(', ', array_slice($missing, 0, 5));
            }
            if (!empty($stale)) {
                $parts[] = 'Outdated: ' . implode(', ', array_slice($stale, 0, 5));
            }
            return self::make_check('js_builds', 'JS builds', 'fail', implode(' | ', $parts));
        }

        return self::make_check('js_builds', 'JS builds', 'pass',
            'Verified ' . count($srcfiles) . ' AMD source/build pair(s).');
    }

    /**
     * Check language pack PHP syntax.
     *
     * @return array
     */
    private static function check_lang_files(): array {
        $files = glob(plugin_updater::plugin_root() . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR
            . '*' . DIRECTORY_SEPARATOR . 'local_ai_course_assistant.php') ?: [];
        $lint = self::lint_files($files);
        return self::make_check('lang_files', 'Language files', $lint['status'], $lint['details']);
    }

    /**
     * Check core API key configuration.
     *
     * @return array
     */
    private static function check_api_configuration(): array {
        $provider = (string)(get_config('local_ai_course_assistant', 'provider') ?: 'openai');
        $apikey = trim((string)get_config('local_ai_course_assistant', 'apikey'));
        $issues = [];

        if ($provider !== 'ollama' && $apikey === '') {
            $issues[] = 'Main API key is missing for provider "' . $provider . '".';
        }

        if (get_config('local_ai_course_assistant', 'realtime_enabled')) {
            $realtimekey = trim((string)get_config('local_ai_course_assistant', 'realtime_apikey'));
            if ($realtimekey === '' && $provider !== 'openai') {
                $issues[] = 'Voice Mode is enabled but no dedicated OpenAI voice/TTS key is configured.';
            }
        }

        if (get_config('local_ai_course_assistant', 'rag_enabled')) {
            $embedprovider = (string)(get_config('local_ai_course_assistant', 'embed_provider') ?: 'openai');
            $embedkey = trim((string)get_config('local_ai_course_assistant', 'embed_apikey'));
            if ($embedprovider !== 'ollama' && $embedkey === '') {
                $issues[] = 'RAG is enabled but the embedding API key is missing.';
            }
        }

        if (!empty($issues)) {
            return self::make_check('api_keys', 'API configuration', 'fail', implode(' ', $issues));
        }

        return self::make_check('api_keys', 'API configuration', 'pass',
            'Provider "' . $provider . '" and optional voice/RAG keys are configured correctly.');
    }

    /**
     * Check RAG-related configuration and tables.
     *
     * @return array
     */
    private static function check_rag_tables(): array {
        global $CFG;
        global $DB;

        require_once($CFG->libdir . '/xmldb/xmldb_object.php');
        require_once($CFG->libdir . '/xmldb/xmldb_table.php');

        $dbman = $DB->get_manager();
        $chunkstable = new \xmldb_table('local_ai_course_assistant_chunks');
        if (!$dbman->table_exists($chunkstable)) {
            return self::make_check('rag_tables', 'RAG configuration', 'fail',
                'The RAG chunks table is missing.');
        }

        if (!get_config('local_ai_course_assistant', 'rag_enabled')) {
            return self::make_check('rag_tables', 'RAG configuration', 'pass',
                'RAG is disabled globally; required table exists.');
        }

        $embedprovider = (string)(get_config('local_ai_course_assistant', 'embed_provider') ?: 'openai');
        $embedkey = trim((string)get_config('local_ai_course_assistant', 'embed_apikey'));
        if ($embedprovider !== 'ollama' && $embedkey === '') {
            return self::make_check('rag_tables', 'RAG configuration', 'fail',
                'RAG is enabled but the embedding API key is missing.');
        }

        return self::make_check('rag_tables', 'RAG configuration', 'pass',
            'RAG tables and embedding configuration look valid.');
    }

    /**
     * Check README/SECURITY version consistency.
     *
     * @return array
     */
    private static function check_version_consistency(): array {
        $root = plugin_updater::plugin_root();
        $versioninfo = plugin_updater::read_version_info($root . DIRECTORY_SEPARATOR . 'version.php');
        $readme = (string)@file_get_contents($root . DIRECTORY_SEPARATOR . 'README.md');
        $security = (string)@file_get_contents($root . DIRECTORY_SEPARATOR . 'SECURITY.md');

        $issues = [];
        if (!preg_match('/^## Version ([0-9]+(?:\\.[0-9]+)+)$/m', $readme, $readmematches)
                || $readmematches[1] !== (string)$versioninfo['release']) {
            $issues[] = 'README.md release does not match version.php.';
        }

        if (!preg_match('/Plugin Version:\\*\\*\\s*([0-9]+(?:\\.[0-9]+)+) \\(([0-9]+)\\)/', $security, $securitymatches)
                || $securitymatches[1] !== (string)$versioninfo['release']
                || (int)$securitymatches[2] !== (int)$versioninfo['version']) {
            $issues[] = 'SECURITY.md release/build does not match version.php.';
        }

        if (!empty($issues)) {
            return self::make_check('version_consistency', 'Version consistency', 'fail', implode(' ', $issues));
        }

        return self::make_check('version_consistency', 'Version consistency', 'pass',
            'version.php, README.md, and SECURITY.md are aligned.');
    }

    /**
     * Check required class files.
     *
     * @return array
     */
    private static function check_key_classes(): array {
        $root = plugin_updater::plugin_root();
        $required = [
            $root . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'context_builder.php'
                => '\\local_ai_course_assistant\\context_builder',
            $root . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'conversation_manager.php'
                => '\\local_ai_course_assistant\\conversation_manager',
            $root . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'starter_manager.php'
                => '\\local_ai_course_assistant\\starter_manager',
            $root . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'provider'
                . DIRECTORY_SEPARATOR . 'base_provider.php'
                => '\\local_ai_course_assistant\\provider\\base_provider',
            $root . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'plugin_updater.php'
                => '\\local_ai_course_assistant\\plugin_updater',
            $root . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'integrity_checker.php'
                => '\\local_ai_course_assistant\\integrity_checker',
        ];

        $issues = [];
        foreach ($required as $file => $classname) {
            if (!is_file($file)) {
                $issues[] = basename($file) . ' is missing.';
                continue;
            }
            if (!class_exists($classname)) {
                $issues[] = $classname . ' could not be autoloaded.';
            }
        }

        if (!empty($issues)) {
            return self::make_check('key_classes', 'Key classes', 'fail', implode(' ', $issues));
        }

        return self::make_check('key_classes', 'Key classes', 'pass',
            'Verified ' . count($required) . ' required class file(s).');
    }

    /**
     * Check key templates.
     *
     * @return array
     */
    private static function check_templates(): array {
        $root = plugin_updater::plugin_root() . DIRECTORY_SEPARATOR . 'templates';
        $required = [
            'chat_widget.mustache',
            'chat_message.mustache',
            'mobile_chat.mustache',
            'token_analytics.mustache',
            'analytics_dashboard.mustache',
        ];

        $missing = [];
        foreach ($required as $template) {
            if (!is_readable($root . DIRECTORY_SEPARATOR . $template)) {
                $missing[] = $template;
            }
        }

        if (!empty($missing)) {
            return self::make_check('templates', 'Templates', 'fail',
                'Missing or unreadable templates: ' . implode(', ', $missing));
        }

        return self::make_check('templates', 'Templates', 'pass',
            'Verified ' . count($required) . ' template file(s).');
    }

    /**
     * Check that installed DB tables match install.xml.
     *
     * @return array
     */
    private static function check_db_tables(): array {
        global $CFG;
        global $DB;

        require_once($CFG->libdir . '/xmldb/xmldb_object.php');
        require_once($CFG->libdir . '/xmldb/xmldb_table.php');

        $installxml = plugin_updater::plugin_root() . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'install.xml';
        if (!is_readable($installxml)) {
            return self::make_check('db_tables', 'Database tables', 'fail', 'db/install.xml is missing.');
        }

        $xml = @simplexml_load_file($installxml);
        if ($xml === false || empty($xml->TABLES)) {
            return self::make_check('db_tables', 'Database tables', 'fail', 'Could not parse db/install.xml.');
        }

        $dbman = $DB->get_manager();
        $missing = [];
        $count = 0;
        foreach ($xml->TABLES->TABLE as $tablexml) {
            $tablename = (string)$tablexml['NAME'];
            if ($tablename === '') {
                continue;
            }
            $count++;
            if (!$dbman->table_exists(new \xmldb_table($tablename))) {
                $missing[] = $tablename;
            }
        }

        if (!empty($missing)) {
            return self::make_check('db_tables', 'Database tables', 'fail',
                'Missing table(s): ' . implode(', ', $missing));
        }

        return self::make_check('db_tables', 'Database tables', 'pass',
            'Verified ' . $count . ' install.xml table(s).');
    }

    /**
     * Check the SSE endpoint file.
     *
     * @return array
     */
    private static function check_sse_endpoint(): array {
        $ssefile = plugin_updater::plugin_root() . DIRECTORY_SEPARATOR . 'sse.php';
        if (!is_readable($ssefile)) {
            return self::make_check('sse_endpoint', 'SSE endpoint', 'fail', 'sse.php is missing or unreadable.');
        }

        $content = (string)file_get_contents($ssefile);
        $required = [
            'text/event-stream',
            'require_sesskey',
            'X-Accel-Buffering',
        ];

        $missing = [];
        foreach ($required as $needle) {
            if (strpos($content, $needle) === false) {
                $missing[] = $needle;
            }
        }

        if (!empty($missing)) {
            return self::make_check('sse_endpoint', 'SSE endpoint', 'fail',
                'sse.php is missing expected markers: ' . implode(', ', $missing));
        }

        return self::make_check('sse_endpoint', 'SSE endpoint', 'pass',
            'sse.php advertises SSE headers and session protections.');
    }

    /**
     * Create a standard check result.
     *
     * @param string $key
     * @param string $label
     * @param string $status
     * @param string $details
     * @return array
     */
    private static function make_check(string $key, string $label, string $status, string $details): array {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'details' => $details,
        ];
    }

    /**
     * Recursively list files by extension.
     *
     * @param string $root
     * @param string $extension
     * @return array
     */
    private static function list_files_by_extension(string $root, string $extension): array {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === strtolower($extension)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);
        return $files;
    }

    /**
     * Lint a list of PHP files using the CLI binary.
     *
     * @param array $files
     * @return array
     */
    private static function lint_files(array $files): array {
        if (empty($files)) {
            return ['status' => 'warn', 'details' => 'No files were found to lint.'];
        }

        if (!function_exists('exec')) {
            return ['status' => 'warn', 'details' => 'PHP CLI lint is not available on this server.'];
        }

        $phpbinary = self::resolve_php_lint_binary();
        if ($phpbinary === '') {
            $currentbinary = PHP_BINARY !== '' ? basename(PHP_BINARY) : 'unknown';
            return [
                'status' => 'warn',
                'details' => 'PHP CLI lint is not available on this server. The current PHP binary "'
                    . $currentbinary . '" does not support file linting.',
            ];
        }

        $errors = [];
        $checked = 0;
        foreach ($files as $file) {
            $checked++;
            $output = [];
            $returncode = 0;
            $command = escapeshellarg($phpbinary) . ' -l ' . escapeshellarg($file) . ' 2>&1';
            @exec($command, $output, $returncode);
            if ($returncode !== 0) {
                $errors[] = basename($file) . ': ' . trim(implode(' ', $output));
                if (count($errors) >= 5) {
                    break;
                }
            }
        }

        if (!empty($errors)) {
            return [
                'status' => 'fail',
                'details' => 'Syntax errors found. ' . implode(' | ', $errors),
            ];
        }

        return [
            'status' => 'pass',
            'details' => 'Checked ' . $checked . ' file(s) with PHP lint via "' . basename($phpbinary) . '".',
        ];
    }

    /**
     * Resolve a PHP executable that supports CLI linting.
     *
     * @return string
     */
    private static function resolve_php_lint_binary(): string {
        $candidates = [];

        if (PHP_BINARY !== '') {
            $candidates[] = PHP_BINARY;
            $bindir = dirname(PHP_BINARY);
            foreach (self::get_php_cli_candidate_names() as $name) {
                $candidates[] = $bindir . DIRECTORY_SEPARATOR . $name;
            }
        }

        if (defined('PHP_BINDIR') && PHP_BINDIR !== '') {
            foreach (self::get_php_cli_candidate_names() as $name) {
                $candidates[] = PHP_BINDIR . DIRECTORY_SEPARATOR . $name;
            }
        }

        foreach (self::get_php_cli_candidate_names() as $name) {
            $candidates[] = $name;
        }

        $candidates = array_values(array_unique(array_filter(array_map('trim', $candidates))));
        foreach ($candidates as $candidate) {
            if (self::binary_supports_php_lint($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Build likely PHP CLI binary names for the current platform/version.
     *
     * @return array
     */
    private static function get_php_cli_candidate_names(): array {
        $suffix = DIRECTORY_SEPARATOR === '\\' ? '.exe' : '';
        $majorminor = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $major = (string)PHP_MAJOR_VERSION;

        return array_values(array_unique([
            'php' . $suffix,
            'php-cli' . $suffix,
            'php' . $majorminor . $suffix,
            'php' . str_replace('.', '', $majorminor) . $suffix,
            'php' . $major . $suffix,
        ]));
    }

    /**
     * Determine whether a PHP executable can lint a file via `php -l`.
     *
     * @param string $binary
     * @return bool
     */
    private static function binary_supports_php_lint(string $binary): bool {
        if ($binary === '' || preg_match('/[\r\n]/', $binary)) {
            return false;
        }

        $output = [];
        $returncode = 0;
        $command = escapeshellarg($binary) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1';
        @exec($command, $output, $returncode);
        if ($returncode !== 0) {
            return false;
        }

        $text = strtolower(trim(implode(' ', $output)));
        return $text === '' || str_contains($text, 'no syntax errors detected');
    }

    /**
     * Send an alert email for failed checks.
     *
     * @param array $results
     * @return void
     */
    private static function send_failure_alert(array $results): void {
        global $SITE;

        $failedchecks = array_filter($results['checks'] ?? [], function(array $check): bool {
            return ($check['status'] ?? '') === 'fail';
        });

        if (empty($failedchecks)) {
            return;
        }

        $recipient = self::get_alert_recipient();
        $sender = \core_user::get_support_user();
        $subject = get_string('integrity:email_subject', 'local_ai_course_assistant',
            format_string($SITE->fullname ?? 'Moodle'));

        $lines = [
            'SOLA integrity monitoring detected one or more failures.',
            '',
            'Site: ' . format_string($SITE->fullname ?? 'Moodle'),
            'Run time: ' . userdate((int)($results['run_at'] ?? time())),
            '',
            'Failed checks:',
        ];

        foreach ($failedchecks as $check) {
            $lines[] = '- ' . (string)$check['label'] . ': ' . (string)$check['details'];
        }

        $message = implode("\n", $lines);
        @email_to_user($recipient, $sender, $subject, $message);
    }

    /**
     * Get the recipient for integrity alerts.
     *
     * @return \stdClass
     */
    private static function get_alert_recipient(): \stdClass {
        $email = trim((string)get_config('local_ai_course_assistant', 'integrity_email'));
        $admin = get_admin();

        if ($email !== '' && validate_email($email)) {
            $recipient = clone $admin;
            $recipient->email = $email;
            $recipient->firstname = 'SOLA';
            $recipient->lastname = 'Integrity';
            return $recipient;
        }

        return $admin;
    }
}
