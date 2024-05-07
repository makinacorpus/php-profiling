<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony\Command;

use MakinaCorpus\Profiling\Prometheus\Collector\SysInfoCollector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: "profiling:prometheus:sys-info", description: "Collect system information samples")]
class PrometheusSysInfoCommand extends Command
{
    public function __construct(
        private SysInfoCollector $collector,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure()
    {
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->collector->collect();

        return self::SUCCESS;
    }
}
