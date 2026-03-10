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
 * Shared SOLA voice catalog and compatibility helpers.
 *
 * OpenAI's Realtime and speech endpoints do not expose an identical voice list,
 * so we keep one visible SOLA voice preference and translate it per endpoint.
 *
 * @package    local_ai_course_assistant
 * @copyright  2026 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class voice_catalog {

    /** Default SOLA voice preference. */
    public const DEFAULT_VOICE = 'marin';

    /** Voice options shown in admin and user settings. */
    private const DISPLAY_VOICES = [
        'marin'   => 'Marin',
        'cedar'   => 'Cedar',
        'alloy'   => 'Alloy',
        'ash'     => 'Ash',
        'ballad'  => 'Ballad',
        'coral'   => 'Coral',
        'echo'    => 'Echo',
        'fable'   => 'Fable',
        'nova'    => 'Nova',
        'onyx'    => 'Onyx',
        'sage'    => 'Sage',
        'shimmer' => 'Shimmer',
        'verse'   => 'Verse',
    ];

    /** Voices currently supported by the Realtime endpoint. */
    private const REALTIME_VOICES = [
        'marin',
        'cedar',
        'alloy',
        'ash',
        'ballad',
        'coral',
        'echo',
        'sage',
        'shimmer',
        'verse',
    ];

    /** Voices currently supported by the speech endpoint. */
    private const TTS_VOICES = [
        'alloy',
        'ash',
        'ballad',
        'coral',
        'echo',
        'fable',
        'nova',
        'onyx',
        'sage',
        'shimmer',
        'verse',
    ];

    /** Compatibility fallbacks when a saved preference is not available in Realtime. */
    private const REALTIME_FALLBACKS = [
        'fable' => 'ballad',
        'nova'  => 'coral',
        'onyx'  => 'cedar',
    ];

    /** Compatibility fallbacks when a saved preference is not available in the speech endpoint. */
    private const TTS_FALLBACKS = [
        'marin' => 'coral',
        'cedar' => 'onyx',
    ];

    /**
     * Voices exposed in UI settings and admin config.
     *
     * @return array<string, string>
     */
    public static function display_voices(): array {
        return self::DISPLAY_VOICES;
    }

    /**
     * Normalize any arbitrary voice value to a known SOLA voice.
     *
     * @param string $voice
     * @return string
     */
    public static function normalize(string $voice): string {
        $voice = trim(strtolower($voice));
        if ($voice !== '' && array_key_exists($voice, self::DISPLAY_VOICES)) {
            return $voice;
        }
        return self::DEFAULT_VOICE;
    }

    /**
     * Return a Realtime-compatible voice for the given preference.
     *
     * @param string $voice
     * @return string
     */
    public static function realtime_voice(string $voice): string {
        $voice = self::normalize($voice);
        if (in_array($voice, self::REALTIME_VOICES, true)) {
            return $voice;
        }
        if (array_key_exists($voice, self::REALTIME_FALLBACKS)) {
            return self::REALTIME_FALLBACKS[$voice];
        }
        return self::DEFAULT_VOICE;
    }

    /**
     * Return a speech-endpoint-compatible voice for the given preference.
     *
     * @param string $voice
     * @return string
     */
    public static function tts_voice(string $voice): string {
        $voice = self::normalize($voice);
        if (in_array($voice, self::TTS_VOICES, true)) {
            return $voice;
        }
        if (array_key_exists($voice, self::TTS_FALLBACKS)) {
            return self::TTS_FALLBACKS[$voice];
        }
        return 'coral';
    }
}
