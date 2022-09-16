<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Benchmark;

/**
 * @BeforeMethods({"setUp"})
 */
class FloatRoundBench
{
    private float $number;

    public function setUp()
    {
        $this->number = \mt_rand(10000,100000000000)/1000000;
    }

    /**
     * @Revs(1000)
     */
    public function benchRound()
    {
        \round($this->number, 3);
    }

    /**
     * @Revs(1000)
     */
    public function benchNumberFormat()
    {
        \number_format($this->number, 3);
    }

    /**
     * @Revs(1000)
     */
    public function benchSprintf()
    {
        \sprintf("%.3F", $this->number);
    }
}
