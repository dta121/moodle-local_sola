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

/**
 * Shared helpers for raw cURL requests.
 *
 * Raw curl_init() calls do not automatically inherit Moodle's CA bundle lookup,
 * which causes HTTPS requests to fail on Windows/WAMP when curl.cainfo is unset.
 *
 * @package    local_ai_course_assistant
 * @copyright  2025 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class curl_helper {

    /**
     * Apply Moodle-compatible SSL and proxy settings to a raw cURL handle.
     *
     * @param resource|\CurlHandle $ch
     * @return void
     */
    public static function apply_moodle_defaults($ch): void {
        global $CFG;

        $options = [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        $cacert = \curl::get_cacert();
        if (!empty($cacert)) {
            $options[CURLOPT_CAINFO] = $cacert;
        }

        if (!empty($CFG->proxyhost)) {
            $options[CURLOPT_PROXY] = $CFG->proxyhost;

            if (!empty($CFG->proxyport)) {
                $options[CURLOPT_PROXYPORT] = (int) $CFG->proxyport;
            }

            if (!empty($CFG->proxyuser)) {
                $options[CURLOPT_PROXYUSERPWD] = $CFG->proxyuser . ':' . ($CFG->proxypassword ?? '');
            }
        }

        curl_setopt_array($ch, $options);
    }
}
