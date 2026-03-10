<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Model;

use JsonSerializable;
use Survos\IiifBundle\Enum\ViewingDirection;
use Survos\IiifBundle\Enum\Behavior;

final class Manifest extends AbstractResource
{
    /** @var Canvas[] */
    public array $items = [];
    /** @var Range[] */
    public array $structures = [];
    /** @var Collection[] */
    public array $collections = [];

    public function __construct(
        string $id,
    ) {
        parent::__construct($id, 'Manifest');
    }

    public static function create(string $id): self
    {
        return new self($id);
    }

    public function addItem(Canvas $canvas): self
    {
        $this->items[] = $canvas;
        return $this;
    }

    public function addStructure(Range $range): self
    {
        $this->structures[] = $range;
        return $this;
    }

    public function setViewingDirection(ViewingDirection $direction): self
    {
        $this->addMetadata('en', 'viewingDirection', 'none', $direction->value);
        return $this;
    }

    public function setBehavior(Behavior $behavior): self
    {
        $this->addMetadata('en', 'behavior', 'none', $behavior->value);
        return $this;
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'items' => $this->items,
            'structures' => $this->structures ? array_values($this->structures) : null,
            'collections' => $this->collections ? array_values($this->collections) : null,
        ]);
    }
}
