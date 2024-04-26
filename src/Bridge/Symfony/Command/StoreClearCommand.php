<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony\Command;

use MakinaCorpus\Profiling\Store\TraceStoreRegistry;
use MakinaCorpus\Profiling\TraceStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: "profiling:store:clear", description: "Clear stored data")]
class StoreClearCommand extends Command
{
    private TraceStoreRegistry $traceStoreRegistry;

    public function __construct(TraceStoreRegistry $traceStoreRegistry)
    {
        parent::__construct();

        $this->traceStoreRegistry = $traceStoreRegistry;
    }

    #[\Override]
    protected function configure()
    {
        $this
            ->addArgument('store', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, "Store to clear", null)
            ->addOption('list', 'l', InputOption::VALUE_NONE, "List all known stores", null)
            ->addOption('until', 'u', InputOption::VALUE_REQUIRED, "Remove all traces until provided date time", null)
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('list')) {
            $output->writeln("Available stores:");
            foreach ($this->traceStoreRegistry->list() as $name) {
                $output->writeln(" - " . $name);
            }
            return self::SUCCESS;
        }

        if (!$stores = $input->getArgument('store')) {
            $output->writeln("No store name provided.");

            return -1;
        }

        if (/* $until = */ $input->getOption('until')) {
            throw new InvalidArgumentException("--until is not implemented yet.");
        }

        $instances = [];
        foreach ($stores as $name) {
            $instances[] = $this->traceStoreRegistry->get($name);
        }

        $output->writeln("Clearing stores.");
        foreach ($instances as $instance) {
            \assert($instance instanceof TraceStore);
            $instance->clear();
            $output->writeln(" - " . $name . " cleared");
        }

        return self::SUCCESS;
    }
}
