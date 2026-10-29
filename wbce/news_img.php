<?php
/**
 * Reservations – news_img.php
 * Object descriptions from news_img. A reservations section is linked to one
 * news_img section (settings.presentation_source = "news_img:<section_id>");
 * an object points to one of its posts (presentation_ref = "news_img:<post_id>").
 * Reads the news_img tables only, never writes them.
 * @module reservations  @version 0.4.0  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

final class ReservationsNewsImg
{
    public const PREFIX = 'news_img:';

    /**
     * The id after the prefix, or null for other sources and empty values.
     */
    public static function idOf(?string $ref): ?int
    {
        if ($ref === null || !str_starts_with($ref, self::PREFIX)) {
            return null;
        }
        $id = (int) substr($ref, strlen(self::PREFIX));
        return $id > 0 ? $id : null;
    }

    /**
     * All news_img sections, for the choice in the settings.
     *
     * @return array<int, string> section id => label
     */
    public static function sections(): array
    {
        $rows = $GLOBALS['database']->fetchAll(
            "SELECT s.`section_id`, p.`menu_title`, p.`language` FROM `{TP}sections` s
               JOIN `{TP}pages` p ON p.`page_id` = s.`page_id`
              WHERE s.`module` = 'news_img'
              ORDER BY p.`language`, p.`menu_title`, s.`section_id`",
        );
        $sections = [];
        foreach ($rows as $row) {
            $sections[(int) $row['section_id']] = sprintf('%s (%s, #%d)', $row['menu_title'], $row['language'], $row['section_id']);
        }
        return $sections;
    }

    /**
     * Active posts of a news_img section, for the choice at the object.
     *
     * @return array<int, string> post id => title
     */
    public static function choices(int $sectionId): array
    {
        $rows = $GLOBALS['database']->fetchAll(
            'SELECT `post_id`, `title` FROM `{TP}mod_news_img_posts`
              WHERE `section_id` = ? AND `active` = 1
              ORDER BY `title`, `post_id`',
            [$sectionId],
        );
        $choices = [];
        foreach ($rows as $row) {
            $choices[(int) $row['post_id']] = (string) $row['title'];
        }
        return $choices;
    }

    /**
     * Title and URL for several refs in one query. Refs that are not active posts
     * of the section are missing from the result (orphaned).
     *
     * @param list<string> $refs
     * @return array<string, array{title: string, url: string}> ref => post
     */
    public static function resolveMany(int $sectionId, array $refs): array
    {
        $ids = array_values(array_unique(array_filter(array_map(self::idOf(...), $refs))));
        if ($ids === []) {
            return [];
        }
        $rows = $GLOBALS['database']->fetchAll(
            'SELECT `post_id`, `title`, `link` FROM `{TP}mod_news_img_posts`
              WHERE `section_id` = ? AND `active` = 1 AND `post_id` IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            [$sectionId, ...$ids],
        );
        $posts = [];
        foreach ($rows as $row) {
            // same URL as news_img's own LinkResolver
            $posts[self::PREFIX . (int) $row['post_id']] = ['title' => (string) $row['title'], 'url' => page_link((string) $row['link'])];
        }
        return $posts;
    }
}
