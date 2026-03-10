<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Model;

use JsonSerializable;

final class LabelMap implements JsonSerializable
{
    /** @var array<string, array{string}> */
    private array $map = [];

    private function __construct()
    {
    }

    public static function create(string $language, string $value): self
    {
        $instance = new self();
        $instance->map[$language] = [$value];
        return $instance;
    }

    /** @param array<string, string> $values */
    public static function multilingual(array $values): self
    {
        $instance = new self();
        foreach ($values as $language => $value) {
            $instance->map[$language] = [$value];
        }
        return $instance;
    }

    /** @param string[] $values */
    public static function fromArray(string $language, array $values): self
    {
        $instance = new self();
        $instance->map[$language] = $values;
        return $instance;
    }

    public function jsonSerialize(): array
    {
        return $this->map;
    }

    public function add(string $language, string $value): self
    {
        if (!isset($this->map[$language])) {
            $this->map[$language] = [];
        }
        $this->map[$language][] = $value;
        return $this;
    }

    /** @return array<string, array{string}> */
    public function toArray(): array
    {
        return $this->map;
    }
}
