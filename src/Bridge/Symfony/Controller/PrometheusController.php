<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony\Controller;

use MakinaCorpus\Profiling\Prometheus\Output\Renderer;
use MakinaCorpus\Profiling\Prometheus\Schema\Schema;
use MakinaCorpus\Profiling\Prometheus\Storage\Storage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PrometheusController
{
    public function __construct(
        private ?string $accessToken,
        private Storage $storage,
        private Schema $schema,
    ) {}

    /**
     * Fetch metrics endpoint.
     */
    public function metrics(Request $request): Response
    {
        // @todo Implement bearer token.
        if (!$this->accessToken || $request->get('access_token') !== $this->accessToken) {
            // Security components might not installed/enabled.
            throw \class_exists(AccessDeniedException::class) ? new AccessDeniedException() : new \Exception();
        }

        return new StreamedResponse(callback: fn () => (new Renderer())->echo($this->storage, $this->schema));
    }
}
