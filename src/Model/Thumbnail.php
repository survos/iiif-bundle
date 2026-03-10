<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Model;

use JsonSerializable;

final class Thumbnail implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $type = 'Image',
        public ?string $format = null,
        public ?int $width = null,
        public ?int $height = null,
        /** @var ImageService3[]|Service[] */
        public array $service = [],
    ) {
    }

    public static function create(string $id, ?string $format = null, ?int $width = null, ?int $height = null): self
    {
        return new self($id, 'Image', $format, $width, $height);
    }

    public function jsonSerialize(): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type,
        ];
        if ($this->format) {
            $data['format'] = $this->format;
        }
        if ($this->width) {
            $data['width'] = $this->width;
        }
        if ($this->height) {
            $data['height'] = $this->height;
        }
        if ($this->service) {
            $data['service'] = array_values($this->service);
        }
        return $data;
    }
}
