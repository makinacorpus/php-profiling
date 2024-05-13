<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony\Command;

use MakinaCorpus\Profiling\Prometheus\Storage\Storage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: "profiling:prometheus:create-schema", description: "Create configured storage schema")]
class PrometheusCreateSchemaCommand extends Command
{
    public function __construct(
        private Storage $storage,
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
        $this->storage->ensureSchema();

        return self::SUCCESS;
    }
}
