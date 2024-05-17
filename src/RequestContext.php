<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

use Symfony\Component\HttpFoundation\Request;

/**
 * Current profiler context information.
 */
class RequestContext
{
    public function __construct(
        public readonly string $route,
        public readonly string $method,
        /** Local host name, if not found request host name. */
        public readonly string $hostname,
    ) {}

    /**
     * Create null instance.
     */
    public static function null(): self
    {
        return new self("none", "none", \gethostname() ?: 'unknown');
    }

    /**
     * Create instance for a CLI command.
     */
    public static function command(mixed $command): self
    {
        return new self(self::name($command), "command", \gethostname() ?: 'unknown');
    }

    /**
     * Create instance for a bus message.
     */
    public static function message(mixed $message): self
    {
        return new self(self::name($message), "message", \gethostname() ?: 'unknown');
    }

    /**
     * Create instance for an HTTP request.
     */
    public static function request(Request $request): self
    {
        return new self(
            $request->attributes->get('_route') ?? 'none',
            \strtolower($request->getMethod()),
            \gethostname() ?: $request->getHost(),
        );
    }

    /**
     * Give a type name for any data type.
     */
    public static function name(mixed $anything): string
    {
        return \str_replace('\\', '.', \is_object($anything) ? \get_debug_type($anything) : $anything);
    }

    /**
     * Get string reprensentation for log and debug.
     */
    public function toString(): string
    {
        return \sprintf('%s[%s]@%s', $this->route, $this->method, $this->hostname);
    }
}
