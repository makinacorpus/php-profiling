<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Store;

abstract class TraceQuery
{
    protected array $channels = [];
    protected ?\DateTimeInterface $from = null;
    protected ?\DateTimeInterface $to = null;
    protected int $limit = 0;
    protected int $offset = 0;
    protected int $page = 1;
    protected bool $orderAsc = false;
    protected bool $orderDesc = true;

    /**
     * @return $this
     */
    public function channel(string ...$channels): self
    {
        foreach ($channels as $channel) {
            $this->channels[] = $channel;
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function from(\DateTimeInterface $date): self
    {
        if ($this->to && $this->to < $date) {
            throw new \InvalidArgumentException(\sprintf("Impossible query: given from date '%s' is after set to date '%s'.", $date->format(\DateTime::ISO8601), $this->to->format(\DateTime::ISO8601)));
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function to(\DateTimeInterface $date): self
    {
        if ($this->from && $this->from > $date) {
            throw new \InvalidArgumentException(\sprintf("Impossible query: given to date '%s' is before set from date '%s'.", $date->format(\DateTime::ISO8601), $this->from->format(\DateTime::ISO8601)));
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function range(int $limit, int $page): self
    {
        $this->limit = $limit;
        $this->page = $page;
        $this->offset = \max(0, $page - 1) * $limit;

        return $this;
    }

    /**
     * @return $this
     */
    public function orderAsc(): self
    {
        $this->orderAsc = true;
        $this->orderDesc = false;

        return $this;
    }

    /**
     * @return $this
     */
    public function orderDesc(): self
    {
        $this->orderAsc = false;
        $this->orderDesc = true;

        return $this;
    }

    /**
     * Execute query and fetch result.
     */
    public abstract function execute(): TraceQueryResult;
}
