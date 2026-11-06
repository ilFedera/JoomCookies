<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service\Scan;

\defined('_JEXEC') or die;

final class ScanLimits
{
    public const MODE_QUICK = 'quick';
    public const MODE_COMPLETE = 'complete';

    /**
     * @param  array<string, mixed>  $options
     */
    public static function pageCap(array $options): int
    {
        $mode = self::mode($options);

        if ($mode === self::MODE_COMPLETE) {
            return 0;
        }

        $max = (int) ($options['max_pages'] ?? 50);

        return max(1, min(5000, $max));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function allowsMorePages(array $options, int $processed): bool
    {
        $cap = self::pageCap($options);

        return $cap === 0 || $processed < $cap;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function mode(array $options): string
    {
        $mode = (string) ($options['scan_mode'] ?? $options['mode'] ?? self::MODE_QUICK);

        return $mode === self::MODE_COMPLETE ? self::MODE_COMPLETE : self::MODE_QUICK;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function depth(array $options): int
    {
        return max(0, min(8, (int) ($options['max_depth'] ?? 2)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function timeout(array $options): int
    {
        return max(1, min(20, (int) ($options['timeout'] ?? 8)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function maxBytes(array $options): int
    {
        return max(10_000, min(1_000_000, (int) ($options['max_bytes'] ?? 400_000)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function maxSeconds(array $options): int
    {
        return max(3, min(25, (int) ($options['max_seconds'] ?? 12)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function delayMs(array $options): int
    {
        return max(0, min(5000, (int) ($options['delay_ms'] ?? 150)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function batchSize(array $options): int
    {
        return max(1, min(25, (int) ($options['batch_size'] ?? 8)));
    }
}
