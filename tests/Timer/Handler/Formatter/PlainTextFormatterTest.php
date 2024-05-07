<?php

declare(strict_types=1);

namespace MakinaCorpus\Tests\Timer\Handler\Formatter;

use MakinaCorpus\Profiling\Error\HandlerError;
use MakinaCorpus\Profiling\Timer;
use MakinaCorpus\Profiling\Timer\Handler\Formatter\PlainTextFormatter;
use PHPUnit\Framework\TestCase;

class PlainTextFormatterTest extends TestCase
{
    public function testDefaultFormatter(): void
    {
        $formatter = new PlainTextFormatter();

        $timer = new Timer("pouet");
        $timer->execute();

        self::assertMatchesRegularExpression(
            '/\[\d+\]\[[a-z0-9]+\] pouet: time: \d+.\d{3} ms memory: \d+(|.\d+) ([KMG]i|)B/',
            $formatter->format($timer)
        );

        $timer->stop();

        self::assertMatchesRegularExpression(
            '/\[\d+\]\[[a-z0-9]+\] pouet: time: \d+.\d{3} ms memory: \d+(|.\d+) ([KMG]i|)B/',
            $formatter->format($timer)
        );
    }

    public function testCustomFormatter(): void
    {
        $formatter = new PlainTextFormatter();
        $formatter->setFormat(<<<TXT
             Values:
               - {pid}: current process identifier,
               - {id}: timer trace unique identifier
               - {name}: timer trace absolute name
               - {relname}: timer trace relative name
               - {timestr}: formatted time
               - {timems}: raw time in milliseconds as float
               - {timenano}: raw time in nanoseconds as float
               - {memstr}: formatted memory consumption
               - {membytes}: memory consumptions in bytes
               - {childcount}: number of children
            TXT
        );

        $timer = new Timer("pouet");
        $timer->execute();

        self::assertMatchesRegularExpression(
            '/Values:\n.*/',
            $formatter->format($timer)
        );

        $timer->stop();

        self::assertMatchesRegularExpression(
            '/Values:\n.*/',
            $formatter->format($timer)
        );
    }

    public function testCannotChangeAfterFormat(): void
    {
        $formatter = new PlainTextFormatter();
        $formatter->format((new Timer())->execute());

        self::expectException(HandlerError::class);
        $formatter->setFormat('pouet');
    }
}
