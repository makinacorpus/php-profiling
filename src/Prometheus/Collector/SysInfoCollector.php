<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Collector;

use MakinaCorpus\Profiling\Prometheus\Logger\SampleLogger;

/**
 * Collects system information samples.
 */
class SysInfoCollector
{
    public function __construct(
        private SampleLogger $logger,
        private bool $collectLoadAvg = true,
        private bool $collectMemory = true,
        /**
         * @var array<string,string>
         *   Keys are arbitrary names, which will be used as sample name infix
         */
        private array $disks = [],
    ) {}

    public function collect(): void
    {
        if ($this->collectLoadAvg) {
            $this->doCollectLoadAvg();
        }
        if ($this->collectMemory) {
            $this->doCollectMemory();
        }
        if ($this->disks) {
            $this->doCollectDisks();
        }
    }

    private function doCollectLoadAvg(): void
    {
        if (!\function_exists('sys_getloadavg')) {
            // @todo log error?
            return;
        }

        list ($one, $five, $fifteen) = \sys_getloadavg();
        $this->logger->gauge('sys_load_avg', ["1"], $one);
        $this->logger->gauge('sys_load_avg', ["5"], $five);
        $this->logger->gauge('sys_load_avg', ["15"], $fifteen);
    }

    private function doCollectMemory(): void
    {
        $handle = @\fopen('/proc/meminfo', 'r');
        if (!$handle) {
            // @todo log error?
            return;
        }

        $buffers = $cached = $memAvail = $memFree = $memTotal = $swapFree = $swapTotal = null;

        while ($line = \fgets($handle)) {
            $matches = [];
            if (\preg_match('@([^:]+):\s+(\d+)(\s+kb|)@ims', $line, $matches)) {
                $value = (int) $matches[2];
                if ($matches[3]) {
                    // Size is not kB but KiB really. Multiplicator is 1024.
                    match ($matches[1]) {
                        'Buffers' => $buffers = 1024 * $value,
                        'Cached' => $cached = 1024 * $value,
                        'MemAvailable' => $memAvail = 1024 * $value,
                        'MemFree' => $memFree = 1024 * $value,
                        'MemTotal' => $memTotal = 1024 * $value,
                        'SwapFree' => $swapFree = 1024 * $value,
                        'SwapTotal' => $swapTotal = 1024 * $value,
                        default => null, // Ignore.
                    };
                }
            }
        }

        if ($buffers) {
            $this->logger->gauge('sys_mem_buffers', [], $buffers);
        }
        if ($cached) {
            $this->logger->gauge('sys_mem_cached', [], $cached);
        }
        if ($memTotal) {
            $this->logger->gauge('sys_mem_total', [], $memTotal);
            if ($memFree) {
                $this->logger->gauge('sys_mem_free', [], $memFree);
                $this->logger->gauge('sys_mem_used', [], $memTotal - $memFree);
            }
        }
        if ($memAvail) {
            $this->logger->gauge('sys_mem_available', [], $memAvail);
        }
        if ($swapTotal) {
            $this->logger->gauge('sys_mem_swap_total', [], $swapTotal);
            if ($swapFree) {
                $this->logger->gauge('sys_mem_swap_free', [], $swapFree);
                $this->logger->gauge('sys_mem_swap_used', [], $swapTotal - $swapFree);
            }
        }
    }

    private function doCollectDisks(): void
    {
        foreach ($this->disks as $name => $path) {
            if (!\is_dir($path) && !\is_file($path)) {
                // @todo log error?
                break;
            }

            if (false !== ($total = \disk_total_space($path))) {
                $this->logger->gauge('sys_disk_total', [$name], (int) $total);
                if (false !== ($free = \disk_free_space($path))) {
                    $this->logger->gauge('sys_disk_free', [$name], (int) $free);
                    $this->logger->gauge('sys_disk_used', [$name], (int) ($total - $free));
                }
            }
        }
    }
}
