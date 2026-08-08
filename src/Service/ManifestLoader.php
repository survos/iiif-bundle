<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Service;

use Survos\FetchBundle\Contract\PersistentFetcherInterface;

use function is_array;
use function json_decode;

final class ManifestLoader
{
    public function __construct(
        private readonly PersistentFetcherInterface $persistentFetcher,
    ) {
    }

    /**
     * Manifests are cached indefinitely by PersistentFetcher (see this bundle's README) --
     * fine for IIIF manifests, which are effectively static once published. `.wip` hosts are
     * routed through the local Symfony proxy automatically (see WipProxy in fetch-bundle).
     *
     * @return array<string, mixed>
     */
    public function load(string $manifestUrl): array
    {
        $result = $this->persistentFetcher->fetch($manifestUrl);

        if (!$result->isOkay()) {
            throw new \RuntimeException(sprintf('Failed to fetch IIIF manifest from %s (status %d).', $manifestUrl, $result->statusCode));
        }

        $payload = json_decode($result->contents ?? '', true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($payload)) {
            throw new \RuntimeException(sprintf('Invalid IIIF manifest payload from %s.', $manifestUrl));
        }

        return $payload;
    }
}
