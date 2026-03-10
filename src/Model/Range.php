<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Model;

use JsonSerializable;

final class Range extends AbstractResource
{
    /** @var Canvas[]|Range[] */
    public array $items = [];

    public function __construct(
        string $id,
    ) {
        parent::__construct($id, 'Range');
    }

    public static function create(string $id): self
    {
        return new self($id);
    }

    public function addItem(Canvas|Range $item): self
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
