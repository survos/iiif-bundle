<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Model;

use JsonSerializable;
use Survos\IiifBundle\Enum\Motivation;

final class TextualBody implements JsonSerializable
{
    public function __construct(
        public string $value,
        public string $language,
        public string $format = 'text/plain',
    ) {
    }

    public static function create(string $value, string $language = 'en', string $format = 'text/plain'): self
    {
        return new self($value, $language, $format);
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'TextualBody',
            'value' => $this->value,
            'language' => $this->language,
            'format' => $this->format,
        ];
    }
}
