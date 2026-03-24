<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

use function is_array;
use function json_decode;
use function str_contains;

final class ManifestLoader
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /** @return array<string, mixed> */
    public function load(string $manifestUrl): array
    {
        $options = [];
        if (str_contains($manifestUrl, '.wip')) {
            $options['proxy'] = 'http://127.0.0.1:7080';
        }

        $response = $this->httpClient->request('GET', $manifestUrl, $options);
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($payload)) {
            throw new \RuntimeException(sprintf('Invalid IIIF manifest payload from %s.', $manifestUrl));
        }

        return $payload;
    }
}
