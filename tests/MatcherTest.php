<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests;

use MakinaCorpus\Profiling\Matcher;
use PHPUnit\Framework\TestCase;

final class MatcherTest extends TestCase
{
    public function testEmpty(): void
    {
        $matcher = new Matcher();

        self::assertTrue($matcher->isEmpty());
        self::assertFalse($matcher->match('foo'));
        self::assertFalse($matcher->match(''));
    }

    public function testExact(): void
    {
        $matcher = new Matcher('bar');

        self::assertFalse($matcher->match('foo'));
        self::assertTrue($matcher->match('bar'));
        self::assertFalse($matcher->match('foobar'));
        self::assertFalse($matcher->match('barfoo'));
    }

    public function testExactMany(): void
    {
        $matcher = new Matcher();
        $matcher->addPattern('bar');
        $matcher->addPattern('foo');
        $matcher->addPattern('fizz');

        self::assertTrue($matcher->match('foo'));
        self::assertTrue($matcher->match('bar'));
        self::assertTrue($matcher->match('fizz'));
        self::assertFalse($matcher->match('foobar'));
        self::assertFalse($matcher->match('barfoo'));
    }

    public function testPrefix(): void
    {
        $matcher = new Matcher();
        $matcher->addPattern('foo', true);

        self::assertTrue($matcher->match('foo'));
        self::assertFalse($matcher->match('bar'));
        self::assertTrue($matcher->match('foozzz'));
        self::assertFalse($matcher->match('barfoo'));
    }

    public function testPrefixMany(): void
    {
        $matcher = new Matcher();
        $matcher->addPattern('foo', true);
        $matcher->addPattern('bar', true);
        $matcher->addPattern('fizz', true);

        self::assertTrue($matcher->match('foo'));
        self::assertTrue($matcher->match('foozzz'));
        self::assertTrue($matcher->match('bar'));
        self::assertTrue($matcher->match('barzzz'));
        self::assertTrue($matcher->match('fizz'));
        self::assertTrue($matcher->match('fizzzzz'));
        self::assertFalse($matcher->match('zzzfoo'));
        self::assertFalse($matcher->match('zzzbar'));
        self::assertFalse($matcher->match('zzzfizz'));
    }

    public function testWildcard(): void
    { 
        $matcher = new Matcher();
        $matcher->addPattern('foo*bar');

        self::assertTrue($matcher->match('foobar'));
        self::assertTrue($matcher->match('foozzzbar'));
        self::assertFalse($matcher->match('foozzz'));
        self::assertFalse($matcher->match('foo'));
        self::assertFalse($matcher->match('bar'));
        self::assertFalse($matcher->match('zzzfoozzzbar'));
        self::assertFalse($matcher->match('foozzzbarzzz'));
    }

    public function testWildcardMany(): void
    { 
        $matcher = new Matcher();
        $matcher->addPattern('foo*bar');
        $matcher->addPattern('bar*foo');

        self::assertTrue($matcher->match('foobar'));
        self::assertTrue($matcher->match('foozzzbar'));
        self::assertFalse($matcher->match('foozzz'));
        self::assertFalse($matcher->match('zzzfoozzzbar'));
        self::assertFalse($matcher->match('foozzzbarzzz'));

        self::assertTrue($matcher->match('barfoo'));
        self::assertTrue($matcher->match('barzzzfoo'));
        self::assertFalse($matcher->match('barzzz'));
        self::assertFalse($matcher->match('zzzbarzzzfoo'));
        self::assertFalse($matcher->match('barzzzfoozzz'));

        self::assertFalse($matcher->match('foo'));
        self::assertFalse($matcher->match('bar'));
    }

    public function testWildcardOne(): void
    { 
        $matcher = new Matcher();
        $matcher->addPattern('foo?bar');

        self::assertTrue($matcher->match('foozbar'));
        self::assertFalse($matcher->match('foobar'));
        self::assertFalse($matcher->match('foozzzbar'));
    }

    public function testWildcardOneMany(): void
    { 
        $matcher = new Matcher();
        $matcher->addPattern('foo?bar');
        $matcher->addPattern('bar?foo');

        self::assertTrue($matcher->match('foozbar'));
        self::assertFalse($matcher->match('foobar'));
        self::assertFalse($matcher->match('foozzzbar'));

        self::assertTrue($matcher->match('barzfoo'));
        self::assertFalse($matcher->match('barfoo'));
        self::assertFalse($matcher->match('barzzzfoo'));
    }
}
