<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony5;

use MakinaCorpus\Profiling\Bridge\Symfony5\DependencyInjection\Compiler\ProfilerContextAwarePass;
use MakinaCorpus\Profiling\Implementation\ProfilerContextAware;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @codeCoverageIgnore
 */
final class ProfilingBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container
            ->registerForAutoconfiguration(ProfilerContextAware::class)
            ->addTag('profiling.profiler_aware')
        ;

        $container->addCompilerPass(new ProfilerContextAwarePass());
    }
}
