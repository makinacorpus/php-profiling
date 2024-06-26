<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Timer\Handler;

use MakinaCorpus\Profiling\Error\HandlerError;
use MakinaCorpus\Profiling\Timer;
use MakinaCorpus\Profiling\Timer\TimerTrace;

class StreamHandler extends AbstractFormatterHandler
{
    /** @var resource|null */
    protected $stream;
    protected ?string $url = null;
    protected ?int $filePermission = 0;
    protected bool $useLocking = true;
    protected bool $directoryCreated = false;
    protected bool $appendLineFeed = true;

    public function __construct($stream, ?int $filePermission = null, bool $useLocking = false)
    {
        if (\is_resource($stream)) {
            $this->stream = $stream;
        } else if (\is_string($stream)) {
            $this->url = $stream;
        } else {
            throw new HandlerError("\$stream must be a string or an opened resource.");
        }
        $this->filePermission = $filePermission;
        $this->useLocking = $useLocking;
    }

    #[\Override]
    public function onStart(Timer $timer): void
    {
    }

    #[\Override]
    public function onStop(TimerTrace $trace): void
    {
        if ($this->shouldLog($trace)) {
            $this->write($this->format($trace));
        }
    }

    #[\Override]
    public function flush(): void
    {
        if (\is_resource($this->stream)) {
            \fflush($this->stream);
        }
    }

    /**
     * Close resource.
     */
    public function close(): void
    {
        if ($this->url && \is_resource($this->stream)) {
            \fclose($this->stream);
        }
        $this->stream = null;
    }

    /**
     * Return the currently active stream.
     *
     * @internal
     *   For unit tests usage.
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Return the stream URL.
     *
     * @internal
     *   For unit tests usage.
     */
    public function getStreamUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Write ligne.
     */
    protected function write(string $string): void
    {
        if (!\is_resource($this->stream)) {
            $this->createTargetDirectory();
            $stream = \fopen($this->url, 'a');
            if ($this->filePermission !== null) {
                @\chmod($this->url, $this->filePermission);
            }
            if (!\is_resource($stream)) {
                $this->stream = null;
                throw new HandlerError(\sprintf("Could not open file for writing: %s", $this->url ?? '<existing stream>'));
            }
            $this->stream = $stream;
        }

        $stream = $this->stream;
        if (!\is_resource($stream)) {
            throw new HandlerError("No stream was opened yet.");
        }

        if ($this->appendLineFeed) {
            $string .= "\n";
        }

        if ($this->useLocking) {
            \flock($stream, LOCK_EX);
            \fwrite($stream, (string) $string);
            \flock($stream, LOCK_UN);
        } else {
            \fwrite($stream, (string) $string);
        }
    }

    /**
     * Create directory.
     */
    private function createTargetDirectory(): void
    {
        if ($this->directoryCreated) {
            return;
        }
        $url = $this->url;
        $pieces = \explode('://', $url, 2);
        if (2 === \count($pieces)) {
            if ('file' === $pieces[0]) {
                $url = '/' . $pieces[1];
            } else {
                return;
            }
        }
        $url = \dirname($url);
        if (!\is_dir($url)) {
            $status = \mkdir($url, 0777, true);
            if (false === $status && !\is_dir($url)) {
                throw new HandlerError(\sprintf("Could not create director: %s", $url));
            }
        }
        $this->directoryCreated = true;
    }
}
