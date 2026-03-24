<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Dto;

final class ManifestSummary
{
    public ?string $manifestUrl = null;
    public ?string $label = null;
    public ?string $summary = null;
    public ?string $thumbnailUrl = null;
    public ?string $imageUrl = null;
    public ?string $iiifBase = null;

    /** @var array<string, string|list<string>> */
    public array $metadata = [];

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'manifest_url' => $this->manifestUrl,
            'label' => $this->label,
            'summary' => $this->summary,
            'thumbnail_url' => $this->thumbnailUrl,
            'image_url' => $this->imageUrl,
            'iiif_base' => $this->iiifBase,
            'metadata' => $this->metadata,
        ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }
}
