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
    public const MODULE = 'news_img';
    public const PREFIX = self::MODULE . ':';
    /** Defined by the page of a single post. */
    public const DETAIL = 'POST_ID';

    /**
     * Active posts of a news_img section, for the choice at the object.
     *
     * @return array<string, string> ref => title
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
            $choices[self::PREFIX . (int) $row['post_id']] = (string) $row['title'];
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
        $ids = array_values(array_unique(array_filter(array_map(static fn (string $ref): ?int => ReservationsWbce::idAfter(self::PREFIX, $ref), $refs))));
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
