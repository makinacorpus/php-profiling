<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony;

use MakinaCorpus\Profiling\Bridge\Symfony\DependencyInjection\Compiler\ProfilerAwarePass;
use MakinaCorpus\Profiling\ProfilerAware;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @codeCoverageIgnore
 */
final class ProfilingBundle extends Bundle
{
    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        $container
            ->registerForAutoconfiguration(ProfilerAware::class)
            ->addTag('profiling.profiler_aware')
        ;

        $container->addCompilerPass(new ProfilerAwarePass());
    }
}
