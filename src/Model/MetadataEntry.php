<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Model;

use JsonSerializable;
use Survos\IiifBundle\Enum\Motivation;

final class MetadataEntry implements JsonSerializable
{
    public function __construct(
        public LabelMap $label,
        public LabelMap $value,
    ) {
    }

    public static function create(string $labelLanguage, string $labelValue, string $valueLanguage, string $valueValue): self
    {
        return new self(
            LabelMap::create($labelLanguage, $labelValue),
            LabelMap::create($valueLanguage, $valueValue),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
        ];
    }
}
