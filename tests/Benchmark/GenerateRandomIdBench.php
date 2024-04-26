<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Benchmark;

class GenerateRandomIdBench
{
    private static $allowedChars = [0, 1, 2, 3, 4, 5, 6, 7, 8, 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];
    private static $allowedCharsStr = '0123456789abcdefghijklmnopqrstuvwxyz';

    /**
     * @Revs(1000)
     */
    public function benchBin2HexRandomBytes()
    {
        // @phpstan-ignore-next-line
        \bin2hex(\random_bytes(4));
    }

    /**
     * @Revs(1000)
     */
    public function benchArrayRandomConcat()
    {
        $foo = '';
        for ($i = 0; $i < 8; ++$i) {
            $foo .= self::$allowedChars[\array_rand(self::$allowedChars)];
        }
    }

    /**
     * @Revs(1000)
     */
    public function benchStringRandomConcat()
    {
        $foo = '';
        for ($i = 0; $i < 8; ++$i) {
            $foo .= self::$allowedCharsStr[\rand(0, 35)];
        }
    }

    /**
     * @Revs(1000)
     */
    public function benchLocalStringRandomConcat()
    {
        $allowedCharsStr = '0123456789abcdefghijklmnopqrstuvwxyz';
        $foo = '';
        for ($i = 0; $i < 8; ++$i) {
            $foo .= $allowedCharsStr[\rand(0, 35)];
        }
    }

    /**
     * @Revs(1000)
     */
    public function benchChrRandRandConcat()
    {
        $foo = '';
        for ($i = 0; $i < 8; ++$i) {
            $foo .= \chr(\rand(0, 1) ? \rand(48, 57) : \rand(97, 122));
        }
    }

    /**
     * @Revs(1000)
     */
    public function benchChrRandRandImplode()
    {
        $foo[7] = \chr(\rand(0, 1) ? \rand(48, 57) : \rand(97, 122));
        for ($i = 0; $i < 7; ++$i) {
            $foo[$i] = \chr(\rand(0, 1) ? \rand(48, 57) : \rand(97, 122));
        }
        // @phpstan-ignore-next-line
        \implode('', $foo);
    }
}
