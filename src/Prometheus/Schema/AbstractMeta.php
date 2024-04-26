<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Schema;

use MakinaCorpus\Profiling\Prometheus\Error\InvalidLabelValuesError;

abstract class AbstractMeta
{
    private int $labelCount;
    private bool $debug = false;

    public function __construct(
        private string $name,
        private array $labels = [],
        private ?string $help = null,
        private bool $active = true,
    ) {
        $this->labelCount = \count($labels);
    }

    /**
     * @internal
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Compute unique key for storage.
     *
     * Use this or not, it is basically a unique (name, ...labels).
     */
    public function computeUniqueStorageKey(array $labelValues): string
    {
        return $this->name . ':' . \implode(',', $labelValues);
    }

    /**
     * From user input, validate that labels matches the expected ones and
     * return a normalized value array, which is sorted the same as the
     * definitions.
     */
    public function validateLabelValues(array $labels): ?array
    {
        if (!$this->labelCount) {
            if (!$labels) {
                return [];
            }
            if ($this->debug) {
                throw new InvalidLabelValuesError(\sprintf("Label values count must match, expected %d got %d.", $this->labelCount, \count($labels)));
            }
            return null;
        }

        $isNumeric = true;
        foreach ($labels as $key => $value) {
            if (null === $value || (!\is_scalar($value) && !$value instanceof \Stringable)) {
                if ($this->debug) {
                    throw new InvalidLabelValuesError(\sprintf("Label values must be string or stringable, key '%s' is '%s'.", $key, \get_debug_type($value)));
                }
                return null;
            }
            if (!\is_int($key)) {
                $isNumeric = false;
                break;
            }
        }

        if (\count($labels) !== $this->labelCount) {
            if ($this->debug) {
                throw new InvalidLabelValuesError(\sprintf("Label values count must match, expected %d got %d.", $this->labelCount, \count($labels)));
            }
            return null;
        }

        // When user input is numeric, it must be in the right order, we cannot
        // further guess what is valid or not.
        if ($isNumeric) {
            return \array_values($labels);
        }

        // Reorder label values to match definition, important for storage.
        $ret = [];
        foreach ($this->labels as $key) {
            if (!\array_key_exists($key, $labels)) {
                if ($this->debug) {
                    throw new InvalidLabelValuesError(\sprintf("Label values with key '%s' is missing.", $key));
                }
                return null;
            }
            $ret[] = (string) $labels[$key];
        }

        return $ret;
    }

    /**
     * Get name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get help.
     */
    public function getHelp(): string
    {
        return $this->help ?? $this->name;
    }

    /**
     * Get label names.
     */
    public function getLabelNames(): array
    {
        return $this->labels;
    }

    /**
     * Should this measure be taken.
     */
    public function isActive(): bool
    {
        return $this->active;
    }
}
