<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerTrace;

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
            throw new \InvalidArgumentException("\$stream must be a string or an opened resource.");
        }
        $this->filePermission = $filePermission;
        $this->useLocking = $useLocking;
    }

    /**
     * {@inheritdoc}
     */
    public function onStart(Profiler $profiler): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function onStop(ProfilerTrace $trace): void
    {
        if ($this->shouldLog($trace)) {
            $this->write($this->format($trace));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        if (\is_resource($this->stream)) {
            \fflush($this->stream);
        }
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
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
                throw new \LogicException(\sprintf("Could not open file for writing: %s", $this->url ?? '<existing stream>'));
            }
            $this->stream = $stream;
        }

        $stream = $this->stream;
        if (!\is_resource($stream)) {
            throw new \LogicException("No stream was opened yet.");
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
                throw new \LogicException(\sprintf("Could not create director: %s", $url));
            }
        }
        $this->directoryCreated = true;
    }
}
