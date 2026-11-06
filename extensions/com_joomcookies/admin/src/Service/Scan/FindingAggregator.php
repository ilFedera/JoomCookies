<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

final class FindingAggregator
{
    /**
     * @param  list<array<string, mixed>>  $findings
     * @return list<array<string, mixed>>
     */
    public static function group(array $findings): array
    {
        $groups = [];

        foreach ($findings as $row) {
            $key = ScanComparer::key($row);

            if (!isset($groups[$key])) {
                $groups[$key] = $row;
                $groups[$key]['occurrences'] = 0;
                $groups[$key]['pages'] = [];
            }

            $groups[$key]['occurrences']++;
            $page = (string) ($row['page_url'] ?? '');

            if ($page !== '') {
                $groups[$key]['pages'][$page] = true;
            }

            $meta = $row['meta'] ?? [];

            if (\is_string($meta)) {
                $decoded = json_decode($meta, true);
                $meta = \is_array($decoded) ? $decoded : [];
            }

            if (isset($meta['pages']) && \is_array($meta['pages'])) {
                foreach ($meta['pages'] as $listed) {
                    if (is_string($listed) && $listed !== '') {
                        $groups[$key]['pages'][$listed] = true;
                    }
                }
            }
        }

        $out = [];

        foreach ($groups as $row) {
            $pages = array_keys($row['pages']);
            $row['pages'] = $pages;
            $row['page_url'] = $pages[0] ?? (string) ($row['page_url'] ?? '');
            $row['page_count'] = \count($pages);
            $out[] = $row;
        }

        return $out;
    }
}
