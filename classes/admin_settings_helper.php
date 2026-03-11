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
 * Shared helpers for SOLA admin settings pages.
 *
 * @package    local_ai_course_assistant
 * @copyright  2025 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_settings_helper {
    public const CATEGORY_ROOT = 'local_ai_course_assistant_admin';
    public const CATEGORY_GENERAL = 'local_ai_course_assistant_admin_general';
    public const CATEGORY_SEARCH_AI = 'local_ai_course_assistant_admin_search_ai';
    public const CATEGORY_MODERATION = 'local_ai_course_assistant_admin_moderation';
    public const CATEGORY_MAINTENANCE = 'local_ai_course_assistant_admin_maintenance';

    public const SECTION_MAIN = 'local_ai_course_assistant';
    public const SECTION_RAG = 'local_ai_course_assistant_rag';
    public const SECTION_TOKEN_ANALYTICS = 'local_ai_course_assistant_token_analytics';
    public const SECTION_UPDATES = 'local_ai_course_assistant_updates';
    public const SECTION_INTEGRITY = 'local_ai_course_assistant_integrity';
    public const SECTION_OFFTOPIC = 'local_ai_course_assistant_offtopic';
    public const SECTION_WELLBEING = 'local_ai_course_assistant_wellbeing';
    public const SECTION_STUDYPLAN = 'local_ai_course_assistant_studyplan';
    public const SECTION_BRANDING = 'local_ai_course_assistant_branding';
    public const SECTION_FAQ = 'local_ai_course_assistant_faq';
    public const SECTION_VOICE = 'local_ai_course_assistant_voice';
    public const SECTION_DEBUGGING = 'local_ai_course_assistant_debugging';

    /**
     * Return top-level SOLA submenu categories in display order.
     *
     * @return array
     */
    public static function get_categories(): array {
        return [
            self::CATEGORY_GENERAL => get_string('settingsgroup:general', 'local_ai_course_assistant'),
            self::CATEGORY_SEARCH_AI => get_string('settingsgroup:search_ai', 'local_ai_course_assistant'),
            self::CATEGORY_MODERATION => get_string('settingsgroup:moderation', 'local_ai_course_assistant'),
            self::CATEGORY_MAINTENANCE => get_string('settingsgroup:maintenance', 'local_ai_course_assistant'),
        ];
    }

    /**
     * Register the root SOLA admin category.
     *
     * @param \admin_root $admin
     * @return void
     */
    public static function add_root_category(\admin_root $admin): void {
        $admin->add('localplugins', new \admin_category(
            self::CATEGORY_ROOT,
            get_string('pluginname', 'local_ai_course_assistant'),
            false
        ));
    }

    /**
     * Register the SOLA submenu group categories.
     *
     * @param \admin_root $admin
     * @return void
     */
    public static function add_group_categories(\admin_root $admin): void {
        foreach (self::get_categories() as $categoryid => $label) {
            $admin->add(self::CATEGORY_ROOT, new \admin_category($categoryid, $label, false));
        }
    }

    /**
     * Return section metadata for the tabbed settings pages.
     *
     * @return array
     */
    public static function get_sections(): array {
        return [
            self::SECTION_MAIN => [
                'label' => get_string('settings:mainpage', 'local_ai_course_assistant'),
                'visiblename' => get_string('settings:mainpage', 'local_ai_course_assistant'),
                'hidden' => false,
            ],
            self::SECTION_STUDYPLAN => [
                'label' => get_string('settings:studyplan_heading', 'local_ai_course_assistant'),
                'visiblename' => get_string('settings:studyplan_heading', 'local_ai_course_assistant'),
                'hidden' => false,
            ],
            self::SECTION_BRANDING => [
                'label' => 'Branding',
                'visiblename' => 'Branding',
                'hidden' => false,
            ],
            self::SECTION_FAQ => [
                'label' => get_string('settings:faq_heading', 'local_ai_course_assistant'),
                'visiblename' => get_string('settings:faq_heading', 'local_ai_course_assistant'),
                'hidden' => false,
            ],
            self::SECTION_DEBUGGING => [
                'label' => get_string('settings:debug_heading', 'local_ai_course_assistant'),
                'visiblename' => get_string('settings:debug_heading', 'local_ai_course_assistant'),
                'hidden' => false,
            ],
            self::SECTION_RAG => [
                'label' => get_string('settings:rag_heading', 'local_ai_course_assistant'),
                'visiblename' => get_string('settings:rag_heading', 'local_ai_course_assistant'),
                'hidden' => false,
            ],
            self::SECTION_TOKEN_ANALYTICS => [
                'label' => 'Token Analytics',
                'visiblename' => 'Token Analytics',
                'hidden' => false,
            ],
            self::SECTION_VOICE => [
                'label' => get_string('settings:realtime_heading', 'local_ai_course_assistant'),
                'visiblename' => get_string('settings:realtime_heading', 'local_ai_course_assistant'),
                'hidden' => false,
            ],
            self::SECTION_OFFTOPIC => [
                'label' => get_string('settings:offtopic_heading', 'local_ai_course_assistant'),
                'visiblename' => get_string('settings:offtopic_heading', 'local_ai_course_assistant'),
                'hidden' => false,
            ],
            self::SECTION_WELLBEING => [
                'label' => get_string('settings:wellbeing_heading', 'local_ai_course_assistant'),
                'visiblename' => get_string('settings:wellbeing_heading', 'local_ai_course_assistant'),
                'hidden' => false,
            ],
            self::SECTION_UPDATES => [
                'label' => get_string('settings:updates_heading', 'local_ai_course_assistant'),
                'visiblename' => get_string('settings:updates_heading', 'local_ai_course_assistant') . ' Settings',
                'hidden' => false,
            ],
            self::SECTION_INTEGRITY => [
                'label' => get_string('settings:integrity_heading', 'local_ai_course_assistant'),
                'visiblename' => get_string('settings:integrity_heading', 'local_ai_course_assistant') . ' Settings',
                'hidden' => false,
            ],
        ];
    }

    /**
     * Create a settings page for a section.
     *
     * @param string $sectionid
     * @return \admin_settingpage
     */
    public static function create_page(string $sectionid): \admin_settingpage {
        $sections = self::get_sections();
        if (!isset($sections[$sectionid])) {
            throw new \coding_exception('Unknown SOLA settings section: ' . $sectionid);
        }
        $section = $sections[$sectionid];
        return new \admin_settingpage(
            $sectionid,
            $section['visiblename'],
            'moodle/site:config',
            $section['hidden']
        );
    }

    /**
     * Add shared page navigation and optional top-save tools.
     *
     * @param \admin_settingpage $settings
     * @param string $currentsection
     * @param bool $showtopsave
     * @return void
     */
    public static function add_page_chrome(\admin_settingpage $settings, string $currentsection, bool $showtopsave = true): void {
        $settings->add(new \admin_setting_description(
            'local_ai_course_assistant/' . self::slug_from_section($currentsection) . '_page_chrome',
            '',
            self::render_page_chrome($currentsection, $showtopsave)
        ));
    }

    /**
     * Render the shared page navigation.
     *
     * @param string $currentsection
     * @param bool $showtopsave
     * @return string
     */
    private static function render_page_chrome(string $currentsection, bool $showtopsave): string {
        $sections = self::get_sections();
        $slug = self::slug_from_section($currentsection);
        $shellid = 'aica-settings-shell-' . $slug;
        $saveid = 'aica-top-save-' . $slug;
        $buttonsid = 'aica-top-buttons-' . $slug;

        $nav = '';
        foreach ($sections as $sectionid => $section) {
            $attributes = ['class' => 'aica-settings-navlink'];
            if ($sectionid === $currentsection) {
                $attributes['class'] .= ' is-active';
                $attributes['aria-current'] = 'page';
            }
            $nav .= \html_writer::link(
                new \moodle_url('/admin/settings.php', ['section' => $sectionid]),
                s($section['label']),
                $attributes
            );
        }

        $topsavehtml = $showtopsave ? '<div id="' . $saveid . '"></div>' : '';

        return <<<HTML
<style>
.aica-settings-nav-row .form-label {
    display: none;
}

.aica-settings-nav-row .form-setting {
    flex: 0 0 100%;
    max-width: 100%;
}

.aica-settings-nav-row .form-shortname,
.aica-settings-nav-row .form-description {
    display: none;
}

.aica-settings-nav-shell {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.aica-settings-top-buttons {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.5rem;
    margin: 0;
}

.aica-settings-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.aica-settings-navlink {
    display: inline-flex;
    align-items: center;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background: #ffffff;
    color: #173140;
    font-weight: 600;
    line-height: 1.2;
    padding: 0.55rem 0.9rem;
    text-decoration: none;
    transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
}

.aica-settings-navlink:hover,
.aica-settings-navlink:focus {
    border-color: #173140;
    color: #173140;
    text-decoration: none;
}

.aica-settings-navlink:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgba(23, 49, 64, 0.18);
}

.aica-settings-navlink.is-active,
.aica-settings-navlink[aria-current="page"] {
    background: #173140;
    border-color: #173140;
    color: #ffffff;
}
</style>
<div id="{$shellid}" class="aica-settings-nav-shell">
    {$topsavehtml}
    <div class="aica-settings-nav" aria-label="SOLA settings sections">
        {$nav}
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var shell = document.getElementById("{$shellid}");
    var row = shell ? shell.closest(".form-item.row") : null;
    if (!row) {
        return;
    }
    row.classList.add("aica-settings-nav-row");
HTML
            . ($showtopsave ? <<<HTML
    var topSave = document.getElementById("{$saveid}");
    var bottomButtons = document.querySelector(".form-buttons");
    if (!topSave || !bottomButtons || document.getElementById("{$buttonsid}")) {
        return;
    }
    var clonedButtons = bottomButtons.cloneNode(true);
    clonedButtons.id = "{$buttonsid}";
    clonedButtons.classList.add("aica-settings-top-buttons");
    topSave.appendChild(clonedButtons);
HTML : '') .
<<<HTML
});
</script>
HTML;
    }

    /**
     * Convert a section id into a safe suffix.
     *
     * @param string $sectionid
     * @return string
     */
    private static function slug_from_section(string $sectionid): string {
        $slug = preg_replace('/[^a-z0-9_]+/i', '_', $sectionid);
        return trim((string)$slug, '_');
    }
}
