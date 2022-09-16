<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler\Formatter;

class Helper
{
    private bool $started = false;
    private string $format = '[{pid}][{id}] {name}: time: {timestr} memory: {memstr}';
    private ?int $pid = null;

    /**
     * Format memory string.
     */
    public static function formatMemory(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return \round($bytes / 1024 / 1024 / 1024,  2) . ' GiB';
        }
        if ($bytes >= 1024 * 1024) {
            return \round($bytes / 1024 / 1024,  2) . ' MiB';
        }
        if ($bytes >= 1024) {
            return \round($bytes / 1024,  2) . ' KiB';
        }
        return $bytes . ' B';
    }

    /**
     * Format time string.
     */
    public static function formatTime(float $msec): string
    {
        return \round($msec, 3) . ' ms';
    }
}
