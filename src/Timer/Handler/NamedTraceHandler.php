<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Timer\Handler;

interface NamedTraceHandler extends TraceHandler
{
    /**
     * Set name.
     *
     * Name is for implementors own usage, it doesn't serve any purpose
     * in this API. It can be used to identify incomming traces and
     * dispatch them along their name.
     *
     * Per default, the name in the profiling.yaml will be injected here
     * when using the Symfony bundle.
     */
    public function setName(string $name): void;

    /**
     * Get name.
     */
    public function getName(): string;
}
