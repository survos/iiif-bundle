<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Service;

use Survos\IiifBundle\Dto\ManifestSummary;

use function array_filter;
use function array_is_list;
use function count;
use function is_array;
use function is_scalar;
use function is_string;
use function rtrim;
use function trim;

final class ManifestSummaryExtractor
{
    public function __construct(
        private readonly ManifestLoader $loader,
    ) {
    }

    public function fromUrl(string $manifestUrl): ManifestSummary
    {
        return $this->fromArray($this->loader->load($manifestUrl), $manifestUrl);
    }

    /** @param array<string, mixed> $manifest */
    public function fromArray(array $manifest, ?string $manifestUrl = null): ManifestSummary
    {
        $summary = new ManifestSummary();
        $summary->manifestUrl = $manifestUrl ?? $this->extractUrl($manifest);
        $summary->label = $this->flattenLangMap($manifest['label'] ?? null);
        $summary->summary = $this->flattenLangMap($manifest['summary'] ?? null);
        $summary->thumbnailUrl = $this->extractThumbnailUrl($manifest);
        $summary->metadata = $this->extractMetadata($manifest['metadata'] ?? []);

        $canvas = $this->firstCanvas($manifest);
        if ($canvas !== null) {
            $paintingBody = $this->firstPaintingBody($canvas);
            if ($paintingBody !== null) {
                $summary->imageUrl = $this->extractUrl($paintingBody);
                $summary->iiifBase = $this->extractServiceUrl($paintingBody);
            }

            $summary->thumbnailUrl ??= $this->extractThumbnailUrl($canvas);
        }

        if ($summary->iiifBase !== null) {
            $summary->thumbnailUrl ??= rtrim($summary->iiifBase, '/') . '/full/!512,512/0/default.jpg';
            $summary->imageUrl ??= rtrim($summary->iiifBase, '/') . '/full/max/0/default.jpg';
        }

        return $summary;
    }

    /** @param array<string, mixed> $manifest */
    private function firstCanvas(array $manifest): ?array
    {
        $items = $manifest['items'] ?? null;
        if (!is_array($items) || $items === []) {
            return null;
        }

        foreach ($items as $item) {
            if (is_array($item)) {
                return $item;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $canvas */
    private function firstPaintingBody(array $canvas): ?array
    {
        $pages = $canvas['items'] ?? [];
        if (!is_array($pages)) {
            return null;
        }

        foreach ($pages as $page) {
            if (!is_array($page) || !is_array($page['items'] ?? null)) {
                continue;
            }

            foreach ($page['items'] as $annotation) {
                if (!is_array($annotation)) {
                    continue;
                }

                $motivation = $annotation['motivation'] ?? null;
                if ($motivation !== null && $motivation !== 'painting') {
                    continue;
                }

                $body = $annotation['body'] ?? null;
                if (is_array($body)) {
                    return $body;
                }
            }
        }

        return null;
    }

    private function extractThumbnailUrl(mixed $value): ?string
    {
        if (is_array($value) && array_is_list($value)) {
            foreach ($value as $item) {
                $url = $this->extractThumbnailUrl($item);
                if ($url !== null) {
                    return $url;
                }
            }
        }

        return $this->extractUrl($value);
    }

    private function extractServiceUrl(mixed $body): ?string
    {
        if (!is_array($body)) {
            return null;
        }

        $service = $body['service'] ?? null;
        if (is_array($service) && array_is_list($service)) {
            foreach ($service as $entry) {
                $url = $this->extractUrl($entry);
                if ($url !== null) {
                    return $url;
                }
            }
        }

        return $this->extractUrl($service);
    }

    private function extractUrl(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);
            return $value !== '' ? $value : null;
        }

        if (!is_array($value)) {
            return null;
        }

        foreach (['id', '@id', 'url', 'href', 'src'] as $key) {
            $candidate = $value[$key] ?? null;
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    /** @return array<string, string|list<string>> */
    private function extractMetadata(mixed $metadata): array
    {
        if (!is_array($metadata)) {
            return [];
        }

        $out = [];
        foreach ($metadata as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $label = $this->flattenLangMap($entry['label'] ?? null);
            if ($label === null) {
                continue;
            }

            $value = $this->flattenMetadataValue($entry['value'] ?? null);
            if ($value === null || $value === []) {
                continue;
            }

            $out[$label] = $value;
        }

        return $out;
    }

    /** @return string|list<string>|null */
    private function flattenMetadataValue(mixed $value): string|array|null
    {
        $flat = $this->flattenLangMap($value, allowMultiple: true);
        if ($flat === null || $flat === []) {
            return null;
        }

        return $flat;
    }

    /** @return string|list<string>|null */
    private function flattenLangMap(mixed $value, bool $allowMultiple = false): string|array|null
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed !== '' ? $trimmed : null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (!is_array($value)) {
            return null;
        }

        if (array_is_list($value)) {
            $values = array_values(array_filter(array_map(function (mixed $entry): ?string {
                $flattened = $this->flattenLangMap($entry);
                return is_string($flattened) && $flattened !== '' ? $flattened : null;
            }, $value)));

            if ($values === []) {
                return null;
            }

            return $allowMultiple || count($values) > 1 ? $values : $values[0];
        }

        $values = [];
        foreach ($value as $entries) {
            $flattened = $this->flattenLangMap($entries, allowMultiple: true);
            if (is_string($flattened) && $flattened !== '') {
                $values[] = $flattened;
                continue;
            }

            if (is_array($flattened)) {
                foreach ($flattened as $item) {
                    if (is_string($item) && $item !== '') {
                        $values[] = $item;
                    }
                }
            }
        }

        $values = array_values(array_filter($values, static fn (string $item): bool => $item !== ''));

        if ($values === []) {
            return null;
        }

        return $allowMultiple || count($values) > 1 ? $values : $values[0];
    }
}
