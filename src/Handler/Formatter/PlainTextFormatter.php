<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler\Formatter;

use MakinaCorpus\Profiling\ProfilerTrace;
use MakinaCorpus\Profiling\Handler\Formatter;

/**
 * Available tokens are:
 *   - {pid}: current process identifier,
 *   - {id}: profiler trace unique identifier
 *   - {name}: profiler trace absolute name
 *   - {relname}: profiler trace relative name
 *   - {timestr}: formatted time
 *   - {timems}: raw time in milliseconds as float
 *   - {timenano}: raw time in nanoseconds as float
 *   - {memstr}: formatted memory consumption
 *   - {membytes}: memory consumptions in bytes
 *   - {childcount}: number of children
 */
class PlainTextFormatter implements Formatter
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
            return \sprintf('%.1f GiB', $bytes / 1024 / 1024 / 1024);
        }
        if ($bytes >= 1024 * 1024) {
            return \sprintf('%.1f MiB', $bytes / 1024 / 1024);
        }
        if ($bytes >= 1024) {
            return \sprintf('%d KiB', $bytes / 1024);
        }
        return \sprintf('%d B', $bytes);
    }

    /**
     * Format time string.
     */
    public static function formatTime(float $msec): string
    {
        return \sprintf("%.3F ms", $msec);
    }

    /**
     * Set format.
     */
    public function setFormat(string $format): void
    {
        if ($this->started) {
            throw new \LogicException("Cannot change format if output has started.");
        }
        $this->format = $format;
    }

    /**
     * {@inheritdoc}
     */
    public function format(ProfilerTrace $trace): string
    {
        if (!$this->started) {
            $this->started = true;
        }

        $elapsedTime = $trace->getElapsedTime();
        $consumedMemory = $trace->getMemoryUsage();

        return \strtr($this->format, [
             '{pid}' => $this->pid ?? ($this->pid = \getmypid()),
             '{id}' => $trace->getId(),
             '{name}' => $trace->getAbsoluteName(),
             '{relname}' => $trace->getName(),
             '{timestr}' => self::formatTime($elapsedTime),
             '{timems}' => $elapsedTime,
             '{timenano}' => $trace->getElapsedTime(),
             '{memstr}' => self::formatMemory($consumedMemory),
             '{membytes}' => $consumedMemory,
             '{childcount}' => \count($trace->getChildren()),
        ]);
    }
}
