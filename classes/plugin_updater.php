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
 * GitHub-powered self-updater for the SOLA plugin.
 *
 * @package    local_ai_course_assistant
 * @copyright  2025 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_updater {

    /** GitHub owner. */
    private const REPO_OWNER = 'dta121';

    /** GitHub repository. */
    private const REPO_NAME = 'moodle-local_sola';

    /** Latest release API endpoint. */
    private const RELEASES_API = 'https://api.github.com/repos/dta121/moodle-local_sola/releases/latest';

    /** Max redirects when talking to GitHub. */
    private const MAX_REDIRECTS = 3;

    /** Request timeout in seconds. */
    private const REQUEST_TIMEOUT = 30;

    /**
     * Get the current plugin directory.
     *
     * @return string
     */
    public static function plugin_root(): string {
        return dirname(__DIR__);
    }

    /**
     * Read version metadata from a version.php file.
     *
     * @param string $versionfile
     * @return array
     */
    public static function read_version_info(string $versionfile): array {
        $info = [
            'component' => '',
            'version' => 0,
            'release' => '',
        ];

        if (!is_readable($versionfile)) {
            return $info;
        }

        $content = (string)file_get_contents($versionfile);
        if (preg_match('/\\$plugin->component\\s*=\\s*\'([^\']+)\'\\s*;/', $content, $matches)) {
            $info['component'] = $matches[1];
        }
        if (preg_match('/\\$plugin->version\\s*=\\s*([0-9]+)\\s*;/', $content, $matches)) {
            $info['version'] = (int)$matches[1];
        }
        if (preg_match('/\\$plugin->release\\s*=\\s*\'([^\']+)\'\\s*;/', $content, $matches)) {
            $info['release'] = $matches[1];
        }

        return $info;
    }

    /**
     * Get local version metadata.
     *
     * @return array
     */
    public static function get_current_release(): array {
        return self::read_version_info(self::plugin_root() . DIRECTORY_SEPARATOR . 'version.php');
    }

    /**
     * Get the latest GitHub release metadata.
     *
     * @return array
     */
    public static function get_latest_release(): array {
        $response = self::http_get_json(self::RELEASES_API, self::build_github_headers(true));
        $tag = trim((string)($response['tag_name'] ?? ''));
        $version = self::normalize_release($tag);
        if ($version === '') {
            $version = self::normalize_release((string)($response['name'] ?? ''));
        }

        $zipurl = trim((string)($response['zipball_url'] ?? ''));
        $htmlurl = trim((string)($response['html_url'] ?? ''));
        self::assert_github_url($zipurl);
        self::assert_github_url($htmlurl);

        $current = self::get_current_release();

        return [
            'version' => $version,
            'tag' => $tag,
            'name' => (string)($response['name'] ?? $tag),
            'body' => (string)($response['body'] ?? ''),
            'published_at' => (string)($response['published_at'] ?? ''),
            'zipball_url' => $zipurl,
            'html_url' => $htmlurl,
            'has_update' => $version !== '' && !empty($current['release'])
                ? version_compare($version, (string)$current['release'], '>')
                : false,
        ];
    }

    /**
     * Download and install the latest GitHub release.
     *
     * @return array
     */
    public static function install_latest_release(): array {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/filestorage/zip_packer.php');

        $current = self::get_current_release();
        $latest = self::get_latest_release();

        if (empty($latest['has_update'])) {
            throw new \moodle_exception(
                'error',
                'local_ai_course_assistant',
                '',
                get_string('updates:no_update', 'local_ai_course_assistant')
            );
        }

        $plugindir = self::plugin_root();
        $parentdir = dirname($plugindir);
        $dirname = basename($plugindir);
        $nonce = date('Ymd_His') . '_' . random_int(1000, 9999);
        $workdir = $parentdir . DIRECTORY_SEPARATOR . $dirname . '_update_work_' . $nonce;
        $backupdir = $parentdir . DIRECTORY_SEPARATOR . $dirname . '_backup_' . $nonce;
        $stageddir = $parentdir . DIRECTORY_SEPARATOR . $dirname . '_staged_' . $nonce;

        self::create_directory($workdir);

        try {
            $zipfile = $workdir . DIRECTORY_SEPARATOR . 'release.zip';
            $extractdir = $workdir . DIRECTORY_SEPARATOR . 'extract';
            self::create_directory($extractdir);

            self::download_to_file((string)$latest['zipball_url'], $zipfile);

            $packer = new \zip_packer();
            if (!$packer->extract_to_pathname($zipfile, $extractdir)) {
                throw new \moodle_exception(
                    'error',
                    'local_ai_course_assistant',
                    '',
                    get_string('updates:error_extract', 'local_ai_course_assistant')
                );
            }

            $newroot = self::find_plugin_root($extractdir);
            $newversion = self::read_version_info($newroot . DIRECTORY_SEPARATOR . 'version.php');
            if (($newversion['component'] ?? '') !== 'local_ai_course_assistant') {
                throw new \moodle_exception(
                    'error',
                    'local_ai_course_assistant',
                    '',
                    get_string('updates:error_package', 'local_ai_course_assistant')
                );
            }

            if (!rename($newroot, $stageddir)) {
                throw new \moodle_exception(
                    'error',
                    'local_ai_course_assistant',
                    '',
                    get_string('updates:error_stage', 'local_ai_course_assistant')
                );
            }

            if (!rename($plugindir, $backupdir)) {
                throw new \moodle_exception(
                    'error',
                    'local_ai_course_assistant',
                    '',
                    get_string('updates:error_backup', 'local_ai_course_assistant')
                );
            }

            try {
                if (!rename($stageddir, $plugindir)) {
                    throw new \moodle_exception(
                        'error',
                        'local_ai_course_assistant',
                        '',
                        get_string('updates:error_swap', 'local_ai_course_assistant')
                    );
                }
            } catch (\Throwable $e) {
                if (is_dir($plugindir)) {
                    \fulldelete($plugindir);
                }
                @rename($backupdir, $plugindir);
                throw $e;
            }

            set_config('update_last_backup', basename($backupdir), 'local_ai_course_assistant');
            set_config('update_last_installed_release', (string)($newversion['release'] ?? ''), 'local_ai_course_assistant');

            return [
                'previous_release' => (string)($current['release'] ?? ''),
                'installed_release' => (string)($newversion['release'] ?? ''),
                'backup_dir' => $backupdir,
            ];
        } finally {
            if (is_dir($stageddir)) {
                \fulldelete($stageddir);
            }
            if (is_dir($workdir)) {
                \fulldelete($workdir);
            }
        }
    }

    /**
     * Find the extracted plugin root within a directory.
     *
     * @param string $root
     * @return string
     */
    private static function find_plugin_root(string $root): string {
        $rootversion = $root . DIRECTORY_SEPARATOR . 'version.php';
        if (is_file($rootversion)) {
            $info = self::read_version_info($rootversion);
            if (($info['component'] ?? '') === 'local_ai_course_assistant') {
                return $root;
            }
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getFilename() !== 'version.php') {
                continue;
            }

            $candidate = $file->getPath();
            $info = self::read_version_info($candidate . DIRECTORY_SEPARATOR . 'version.php');
            if (($info['component'] ?? '') === 'local_ai_course_assistant'
                    && is_file($candidate . DIRECTORY_SEPARATOR . 'settings.php')) {
                return $candidate;
            }
        }

        throw new \moodle_exception(
            'error',
            'local_ai_course_assistant',
            '',
            get_string('updates:error_package', 'local_ai_course_assistant')
        );
    }

    /**
     * Download a GitHub URL to a file.
     *
     * @param string $url
     * @param string $destination
     * @return void
     */
    private static function download_to_file(string $url, string $destination): void {
        $response = self::http_request($url, self::build_github_headers(false));
        if (($response['status'] ?? 0) !== 200) {
            throw new \moodle_exception(
                'error',
                'local_ai_course_assistant',
                '',
                get_string('updates:error_download', 'local_ai_course_assistant')
                    . ' HTTP ' . (int)($response['status'] ?? 0)
            );
        }

        if (@file_put_contents($destination, (string)$response['body']) === false) {
            throw new \moodle_exception(
                'error',
                'local_ai_course_assistant',
                '',
                get_string('updates:error_download', 'local_ai_course_assistant')
            );
        }
    }

    /**
     * Perform an HTTP GET request and decode JSON.
     *
     * @param string $url
     * @param array $headers
     * @return array
     */
    private static function http_get_json(string $url, array $headers): array {
        $response = self::http_request($url, $headers);
        if (($response['status'] ?? 0) !== 200) {
            throw new \moodle_exception(
                'error',
                'local_ai_course_assistant',
                '',
                get_string('updates:error_check', 'local_ai_course_assistant')
                    . ' HTTP ' . (int)($response['status'] ?? 0)
            );
        }

        $data = json_decode((string)$response['body'], true);
        if (!is_array($data)) {
            throw new \moodle_exception(
                'error',
                'local_ai_course_assistant',
                '',
                get_string('updates:error_check', 'local_ai_course_assistant')
            );
        }

        return $data;
    }

    /**
     * Make a GitHub request with manual redirect handling.
     *
     * @param string $url
     * @param array $headers
     * @param int $redirects
     * @return array
     */
    private static function http_request(string $url, array $headers = [], int $redirects = 0): array {
        if ($redirects > self::MAX_REDIRECTS) {
            throw new \moodle_exception(
                'error',
                'local_ai_course_assistant',
                '',
                get_string('updates:error_redirects', 'local_ai_course_assistant')
            );
        }

        self::assert_github_url($url);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        curl_helper::apply_moodle_defaults($ch);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \moodle_exception(
                'error',
                'local_ai_course_assistant',
                '',
                get_string('updates:error_network', 'local_ai_course_assistant') . ': ' . $error
            );
        }

        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headersize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headertext = substr($response, 0, $headersize);
        $body = substr($response, $headersize);

        if ($status >= 300 && $status < 400) {
            $location = self::extract_header($headertext, 'Location');
            if ($location === '') {
                throw new \moodle_exception(
                    'error',
                    'local_ai_course_assistant',
                    '',
                    get_string('updates:error_redirects', 'local_ai_course_assistant')
                );
            }
            return self::http_request(self::resolve_redirect_url($url, $location), $headers, $redirects + 1);
        }

        return [
            'status' => $status,
            'headers' => $headertext,
            'body' => $body,
        ];
    }

    /**
     * Build GitHub request headers.
     *
     * @param bool $json
     * @return array
     */
    private static function build_github_headers(bool $json): array {
        $headers = [
            'User-Agent: Moodle-SOLA-Updater',
        ];
        if ($json) {
            $headers[] = 'Accept: application/vnd.github+json';
        }

        $token = trim((string)get_config('local_ai_course_assistant', 'github_token'));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * Normalize a GitHub release tag to a dotted version number.
     *
     * @param string $release
     * @return string
     */
    private static function normalize_release(string $release): string {
        if (preg_match('/([0-9]+(?:\\.[0-9]+)+)/', trim($release), $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Validate that a URL points to GitHub over HTTPS.
     *
     * @param string $url
     * @return void
     */
    private static function assert_github_url(string $url): void {
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        $scheme = strtolower((string)($parts['scheme'] ?? ''));

        if ($scheme !== 'https' || $host === '' || !preg_match('/(^|\\.)github\\.com$/', $host)) {
            throw new \moodle_exception(
                'error',
                'local_ai_course_assistant',
                '',
                get_string('updates:error_url', 'local_ai_course_assistant')
            );
        }
    }

    /**
     * Extract a header value from a raw header block.
     *
     * @param string $headers
     * @param string $name
     * @return string
     */
    private static function extract_header(string $headers, string $name): string {
        if (preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/im', $headers, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    /**
     * Resolve a redirect location.
     *
     * @param string $currenturl
     * @param string $location
     * @return string
     */
    private static function resolve_redirect_url(string $currenturl, string $location): string {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($currenturl);
        $scheme = (string)($parts['scheme'] ?? 'https');
        $host = (string)($parts['host'] ?? '');
        if ($host === '') {
            return $location;
        }

        if (strpos($location, '/') === 0) {
            return $scheme . '://' . $host . $location;
        }

        $path = (string)($parts['path'] ?? '/');
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        return $scheme . '://' . $host . ($dir !== '' ? $dir . '/' : '/') . ltrim($location, '/');
    }

    /**
     * Create a directory if it does not exist.
     *
     * @param string $path
     * @return void
     */
    private static function create_directory(string $path): void {
        if (is_dir($path)) {
            return;
        }
        if (!@mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \moodle_exception(
                'error',
                'local_ai_course_assistant',
                '',
                get_string('updates:error_directory', 'local_ai_course_assistant') . ': ' . $path
            );
        }
    }
}
