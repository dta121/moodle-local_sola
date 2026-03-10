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
 * Extracts plain text from course modules for RAG indexing.
 *
 * Supports: mod_page, mod_book, mod_assign, mod_forum, mod_label,
 *           mod_glossary, mod_quiz.
 *
 * @package    local_ai_course_assistant
 * @copyright  2025 AI Course Assistant
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_extractor {

    /** @var int Minimum characters required to keep a module's content. */
    private const MIN_CHARS = 80;

    /**
     * Extract text content from all supported modules in a course.
     *
     * @param int $courseid
     * @return array Array of ['cmid'=>int, 'modtype'=>string, 'title'=>string,
     *                         'section'=>string, 'text'=>string]
     */
    public static function extract_course_modules(int $courseid): array {
        global $DB;

        $modinfo = get_fast_modinfo($courseid);
        $sections = $modinfo->get_section_info_all();

        // Build a map of cmid → section name.
        $sectionnames = [];
        foreach ($sections as $section) {
            $name = get_section_name($courseid, $section);
            if (!empty($modinfo->sections[$section->section])) {
                foreach ($modinfo->sections[$section->section] as $cmid) {
                    $sectionnames[$cmid] = $name;
                }
            }
        }

        $results = [];

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            $supported = ['page', 'book', 'assign', 'forum', 'label', 'glossary', 'quiz'];
            if (!in_array($cm->modname, $supported, true)) {
                continue;
            }

            try {
                $text = self::extract_module_text($cm->modname, $cm->instance, $courseid, (int) $cm->id, $DB);
            } catch (\Exception $e) {
                continue;
            }

            if (empty($text) || strlen($text) < self::MIN_CHARS) {
                continue;
            }

            $results[] = [
                'cmid'    => (int) $cm->id,
                'modtype' => $cm->modname,
                'title'   => $cm->name,
                'section' => $sectionnames[$cm->id] ?? '',
                'text'    => $text,
            ];
        }

        return $results;
    }

    /**
     * Extract text content from a single course module by cmid.
     *
     * @param int $cmid
     * @return array|null ['cmid'=>int, 'modtype'=>string, 'title'=>string, 'text'=>string]
     *                    or null if unsupported/empty.
     */
    public static function extract_module(int $cmid): ?array {
        global $DB;

        try {
            $cmrec  = $DB->get_record('course_modules', ['id' => $cmid], 'id,course,module,instance', MUST_EXIST);
            $module = $DB->get_record('modules', ['id' => $cmrec->module], 'name', MUST_EXIST);
            $modname  = $module->name;
            $courseid = (int) $cmrec->course;
            $instance = (int) $cmrec->instance;

            $modinfo = get_fast_modinfo($courseid);
            $cm = $modinfo->get_cm($cmid);
            $title = $cm->name;

            $text = self::extract_module_text($modname, $instance, $courseid, $cmid, $DB);
        } catch (\Exception $e) {
            return null;
        }

        if (empty($text) || strlen($text) < self::MIN_CHARS) {
            return null;
        }

        return [
            'cmid'    => $cmid,
            'modtype' => $modname,
            'title'   => $title,
            'text'    => $text,
        ];
    }

    /**
     * Extract plain text from a specific module type and instance.
     *
     * @param string $modname Module type name (page, book, etc.)
     * @param int    $instance Module instance ID
     * @param int    $courseid Course ID
     * @param int    $cmid Course module ID
     * @param \moodle_database $DB
     * @return string Plain text, or empty string if not extractable.
     */
    private static function extract_module_text(
        string $modname,
        int $instance,
        int $courseid,
        int $cmid,
        \moodle_database $DB
    ): string {
        switch ($modname) {
            case 'page':
                return self::extract_page($instance, $courseid, $cmid, $DB);

            case 'book':
                return self::extract_book($instance, $courseid, $cmid, $DB);

            case 'assign':
                return self::extract_intro_module('assign', 'assign', $instance, $courseid, $cmid, $DB);

            case 'forum':
                return self::extract_intro_module('forum', 'forum', $instance, $courseid, $cmid, $DB);

            case 'label':
                return self::extract_intro_module('label', 'label', $instance, $courseid, $cmid, $DB);

            case 'glossary':
                return self::extract_glossary($instance, $courseid, $cmid, $DB);

            case 'quiz':
                return self::extract_intro_module('quiz', 'quiz', $instance, $courseid, $cmid, $DB);

            default:
                return '';
        }
    }

    /**
     * Extract text from mod_page.
     */
    private static function extract_page(int $instance, int $courseid, int $cmid, \moodle_database $DB): string {
        $record = $DB->get_record('page', ['id' => $instance, 'course' => $courseid], 'content, contentformat, revision');
        if ($record && !empty($record->content)) {
            $context = \context_module::instance($cmid);
            return self::clean_rich_text(
                $record->content,
                (int) $record->contentformat,
                $context,
                'mod_page',
                'content',
                (int) ($record->revision ?? 0)
            );
        }
        return '';
    }

    /**
     * Extract text from mod_book (all chapters concatenated).
     */
    private static function extract_book(int $instance, int $courseid, int $cmid, \moodle_database $DB): string {
        $chapters = $DB->get_records(
            'book_chapters',
            ['bookid' => $instance, 'hidden' => 0],
            'pagenum ASC',
            'id, title, content, contentformat'
        );

        if (!$chapters) {
            return '';
        }

        $context = \context_module::instance($cmid);
        $parts = [];
        foreach ($chapters as $ch) {
            $text = self::clean_rich_text(
                $ch->content,
                (int) $ch->contentformat,
                $context,
                'mod_book',
                'chapter',
                (int) $ch->id
            );
            if (strlen($text) > 50) {
                $heading = !empty($ch->title) ? "{$ch->title}\n" : '';
                $parts[] = $heading . $text;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Extract text from mod_glossary (all entries: concept + definition).
     */
    private static function extract_glossary(int $instance, int $courseid, int $cmid, \moodle_database $DB): string {
        $entries = $DB->get_records(
            'glossary_entries',
            ['glossaryid' => $instance, 'approved' => 1],
            'concept ASC',
            'id, concept, definition, definitionformat'
        );

        if (!$entries) {
            return '';
        }

        $context = \context_module::instance($cmid);
        $parts = [];
        foreach ($entries as $entry) {
            $deftext = self::clean_rich_text(
                $entry->definition,
                (int) $entry->definitionformat,
                $context,
                'mod_glossary',
                'entry',
                (int) $entry->id
            );
            if (!empty($entry->concept) && strlen($deftext) > 10) {
                $parts[] = "{$entry->concept}: {$deftext}";
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Extract plain text from a module intro field using Moodle's intro formatter.
     *
     * @param string $table
     * @param string $modname
     * @param int $instance
     * @param int $courseid
     * @param int $cmid
     * @param \moodle_database $DB
     * @return string
     */
    private static function extract_intro_module(
        string $table,
        string $modname,
        int $instance,
        int $courseid,
        int $cmid,
        \moodle_database $DB
    ): string {
        $record = $DB->get_record($table, ['id' => $instance, 'course' => $courseid], 'intro, introformat');
        if (!$record || empty($record->intro)) {
            return '';
        }

        $html = format_module_intro($modname, $record, $cmid, false);
        return self::normalise_html_to_text($html);
    }

    /**
     * Rewrite pluginfile URLs, format rich text, and normalize it to plain text.
     *
     * @param string $html Raw HTML content.
     * @param int $format Moodle text format constant.
     * @param \context_module $context
     * @param string $component
     * @param string $filearea
     * @param int|null $itemid
     * @return string
     */
    private static function clean_rich_text(
        string $html,
        int $format,
        \context_module $context,
        string $component,
        string $filearea,
        ?int $itemid = null
    ): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $rewritten = file_rewrite_pluginfile_urls($html, 'pluginfile.php', $context->id, $component, $filearea, $itemid);
        $formatted = format_text($rewritten, $format, [
            'context' => $context,
            'noclean' => true,
            'overflowdiv' => true,
            'filter' => false,
            'para' => false,
        ]);

        return self::normalise_html_to_text($formatted);
    }

    /**
     * Strip markup and normalize whitespace from already-formatted HTML.
     *
     * @param string $html
     * @return string
     */
    private static function normalise_html_to_text(string $html): string {
        $text = preg_replace('/<(?:br)\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/(?:p|div|li|h[1-6]|tr|table|ul|ol)>/i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace("/\r\n?/", "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/ *\n */', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
