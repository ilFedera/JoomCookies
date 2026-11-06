<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

final class ScanComparer
{
    /**
     * @param  list<array<string, mixed>>  $previous
     * @param  list<array<string, mixed>>  $current
     * @return array{added: list<array<string, mixed>>, removed: list<array<string, mixed>>, unchanged: int}
     */
    public static function diff(array $previous, array $current, bool $currentComplete = true): array
    {
        $prevKeys = [];
        $currKeys = [];

        foreach ($previous as $row) {
            $prevKeys[self::key($row)] = $row;
        }

        foreach ($current as $row) {
            $currKeys[self::key($row)] = $row;
        }

        $added   = [];
        $removed = [];

        foreach ($currKeys as $key => $row) {
            if (!isset($prevKeys[$key])) {
                $added[] = $row;
            }
        }

        foreach ($prevKeys as $key => $row) {
            if (!isset($currKeys[$key])) {
                $removed[] = $row;
            }
        }

        if (!$currentComplete) {
            $removed = [];
        }

        return [
            'added'     => $added,
            'removed'   => $removed,
            'unchanged' => \count($currKeys) - \count($added),
            'incomplete' => !$currentComplete,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function key(array $row): string
    {
        return implode('|', [
            (string) ($row['type'] ?? ''),
            (string) ($row['name'] ?? ''),
            (string) ($row['domain'] ?? ''),
            (string) ($row['service_id'] ?? ''),
        ]);
    }
}
