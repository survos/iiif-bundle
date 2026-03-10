<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Model;

use JsonSerializable;

final class Service implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $type,
        public ?string $profile = null,
        /** @var array<string, mixed> */
        public array $extra = [],
    ) {
    }

    public static function create(string $id, string $type, ?string $profile = null): self
    {
        return new self($id, $type, $profile);
    }

    public function jsonSerialize(): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type,
        ];
        if ($this->profile) {
            $data['profile'] = $this->profile;
        }
        return array_merge($data, $this->extra);
    }
}
