<?php
/**
 * Reservations – articles.php
 * Object descriptions from articles. A reservations section is linked to one
 * articles section (settings.presentation_source = "articles:<section_id>");
 * an object points to one of its articles (presentation_ref = "articles:<article_id>").
 * Reads the articles tables only, never writes them. Which article is visible
 * is articles' own rule (Articles\Articles::visibleSql), not a copy of it.
 * @module reservations  @version 0.9.0  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

final class ReservationsArticles
{
    public const MODULE = 'articles';
    public const PREFIX = self::MODULE . ':';
    /** Defined by the access file of an article's detail page. */
    public const DETAIL = 'ARTICLE_ID';

    /**
     * Visible and planned articles of a section, for the choice at the object.
     *
     * @return array<string, string> ref => title
     */
    public static function choices(int $sectionId): array
    {
        if (!self::loaded()) {
            return [];
        }
        $rows = $GLOBALS['database']->fetchAll(
            'SELECT i.`article_id`, i.`title` FROM `{TP}mod_articles_items` i
              WHERE i.`section_id` = ? AND ' . Articles\Articles::visibleSql('i', null, true) . '
              ORDER BY i.`title`, i.`article_id`',
            [$sectionId],
        );
        $choices = [];
        foreach ($rows as $row) {
            $choices[self::PREFIX . (int) $row['article_id']] = (string) $row['title'];
        }
        return $choices;
    }

    /**
     * Title and URL for several refs in one query. Refs that are not visible
     * articles of the section, or whose page visitors cannot open (trash,
     * private, registered, section out of its period), are missing (orphaned).
     *
     * @param list<string> $refs
     * @return array<string, array{title: string, url: string}> ref => article
     */
    public static function resolveMany(int $sectionId, array $refs): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (string $ref): ?int => ReservationsWbce::idAfter(self::PREFIX, $ref), $refs))));
        if ($ids === [] || !self::loaded()) {
            return [];
        }
        $rows = $GLOBALS['database']->fetchAll(
            "SELECT i.`article_id`, i.`title`, i.`link` FROM `{TP}mod_articles_items` i
               JOIN `{TP}sections` s ON s.`section_id` = i.`section_id`
               JOIN `{TP}pages` p ON p.`page_id` = s.`page_id`
              WHERE i.`section_id` = ? AND i.`link` <> ''
                AND " . Articles\Articles::visibleSql('i') . ' AND ' . Articles\Articles::publicSql('s', 'p') . '
                AND i.`article_id` IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            [$sectionId, ...$ids],
        );
        $articles = [];
        foreach ($rows as $row) {
            $articles[self::PREFIX . (int) $row['article_id']] = ['title' => (string) $row['title'], 'url' => page_link((string) $row['link'])];
        }
        return $articles;
    }

    /**
     * Loads Articles\Articles, which holds articles' own visibility rules; false without the module.
     */
    private static function loaded(): bool
    {
        // a plain class file; outside articles' own pages its autoloader is not registered
        if (!class_exists(Articles\Articles::class)) {
            $file = WB_PATH . '/modules/articles/src/Articles.php';
            if (!is_file($file)) {
                return false;
            }
            require_once $file;
        }
        return true;
    }
}
