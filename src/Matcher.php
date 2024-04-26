<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

/**
 * Matches strings.
 *
 * Input are a set of user-given glob-style patterns, output is a boolean
 * that tells if the given string matches any of the glob patterns.
 *
 * Internally, it builds up a regex and uses preg to match, which is much
 * more efficient.
 */
class Matcher
{
    private string $compiled;
    private bool $empty = true;

    public function __construct(string $regex = '')
    {
        if ($regex) {
            $this->empty = false;
        }
        $this->compiled = '@^(' . $regex . ')$@';
    }

    /**
     * Match string.
     */
    public function match(string $input): bool
    {
        if ($this->empty) {
            return false;
        }
        return (bool) \preg_match($this->compiled, $input);
    }

    /**
     * Get compiled regex.
     *
     * @internal
     *   For caching purpose.
     */
    public function getCompiledRegex(bool $withoutDelimiter = true): string
    {
        if ($withoutDelimiter) {
            return \substr(\substr($this->compiled, 0, -3), 3);
        }
        return $this->compiled;
    }

    /**
     * Is this instance empty.
     */
    public function isEmpty(): bool
    {
        return $this->empty;
    }

    /**
     * Add pattern to the match list.
     */
    public function addPattern(string $pattern, bool $isPrefix = false): void
    {
        if ($isPrefix) {
            $pattern .= '*';
        }

        $pattern = $this->quote($pattern);

        $this->append(\str_replace('*', '.*', \str_replace('?', '.{1}', $pattern)));
    }

    private function append(string $regex): void
    {
        $this->compiled = \substr($this->compiled, 0, -3) . ($this->empty ? '' : '|') . $regex . ')$@';
        $this->empty = false;
    }

    private function quote(string $regex): string
    {
        foreach (['@', '|', '[', ']', '{', '}', ':'] as $char) {
            $regex = \str_replace($char, '\\' . $char, $regex);
        }
        return $regex;
    }
}
