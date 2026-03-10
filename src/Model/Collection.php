<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Model;

use JsonSerializable;

final class Collection extends AbstractResource
{
    /** @var Manifest[]|Collection[] */
    public array $items = [];

    public function __construct(
        string $id,
    ) {
        parent::__construct($id, 'Collection');
    }

    public static function create(string $id): self
    {
        return new self($id);
    }

    public function addItem(Manifest|Collection $item): self
    {
        $this->items[] = $item;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'items' => $this->items,
        ]);
    }
}
