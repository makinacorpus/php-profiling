<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler\Formatter;

use MakinaCorpus\Profiling\Implementation\DefaultProfiler;
use PHPUnit\Framework\TestCase;

class PlainTextFormatterTest extends TestCase
{
    public function testDefaultFormatter(): void
    {
        $formatter = new PlainTextFormatter();

        $profiler = new DefaultProfiler("pouet");
        $profiler->execute();

        self::assertMatchesRegularExpression(
            '/\[\d+\]\[[a-z0-9]+\] pouet: time: \d+.\d{3} ms memory: \d+ B/',
            $formatter->format($profiler)
        );

        $profiler->stop();

        self::assertMatchesRegularExpression(
            '/\[\d+\]\[[a-z0-9]+\] pouet: time: \d+.\d{3} ms memory: \d+ B/',
            $formatter->format($profiler)
        );
    }

    public function testCustomFormatter(): void
    {
        $formatter = new PlainTextFormatter();
        $formatter->setFormat(<<<TXT
             Values:
               - {pid}: current process identifier,
               - {id}: profiler trace unique identifier
               - {name}: profiler trace absolute name
               - {relname}: profiler trace relative name
               - {timestr}: formatted time
               - {timems}: raw time in milliseconds as float
               - {timenano}: raw time in nanoseconds as float
               - {memstr}: formatted memory consumption
               - {membytes}: memory consumptions in bytes
               - {childcount}: number of children
            TXT
        );

        $profiler = new DefaultProfiler("pouet");
        $profiler->execute();

        self::assertMatchesRegularExpression(
            '/Values:\n.*/',
            $formatter->format($profiler)
        );

        $profiler->stop();

        self::assertMatchesRegularExpression(
            '/Values:\n.*/',
            $formatter->format($profiler)
        );
    }

    public function testCannotChangeAfterFormat(): void
    {
        $formatter = new PlainTextFormatter();
        $formatter->format((new DefaultProfiler())->execute());

        self::expectException(\LogicException::class);
        $formatter->setFormat('pouet');
    }
}
