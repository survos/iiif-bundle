<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Model;

use JsonSerializable;

final class ImageService3 implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $profile = 'level2',
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => 'ImageService3',
            'profile' => $this->profile,
        ];
    }
}
