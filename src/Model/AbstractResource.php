<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Model;

use JsonSerializable;

abstract class AbstractResource implements JsonSerializable
{
    public ?LabelMap $label = null;
    public ?LabelMap $summary = null;
    /** @var MetadataEntry[] */
    public array $metadata = [];
    public ?Thumbnail $thumbnail = null;
    public ?string $rights = null;
    public ?MetadataEntry $requiredStatement = null;
    /** @var array<int, Service> */
    public array $service = [];
    public ?string $id = null;
    public ?string $type = null;

    public function __construct(
        string $id,
        string $type,
    ) {
        $this->id = $id;
        $this->type = $type;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type,
        ];

        if ($this->label) {
            $data['label'] = $this->label;
        }
        if ($this->summary) {
            $data['summary'] = $this->summary;
        }
        if ($this->metadata) {
            $data['metadata'] = $this->metadata;
        }
        if ($this->thumbnail) {
            $data['thumbnail'] = $this->thumbnail;
        }
        if ($this->rights) {
            $data['rights'] = $this->rights;
        }
        if ($this->requiredStatement) {
            $data['requiredStatement'] = $this->requiredStatement;
        }
        if ($this->service) {
            $data['service'] = array_values($this->service);
        }

        return $data;
    }

    public function setLabel(string $language, string $value): self
    {
        $this->label = LabelMap::create($language, $value);
        return $this;
    }

    public function setSummary(string $language, string $value): self
    {
        $this->summary = LabelMap::create($language, $value);
        return $this;
    }

    public function addMetadata(string $labelLanguage, string $labelValue, string $valueLanguage, string $valueValue): self
    {
        $this->metadata[] = MetadataEntry::create($labelLanguage, $labelValue, $valueLanguage, $valueValue);
        return $this;
    }

    public function setRights(string $rights): self
    {
        $this->rights = $rights;
        return $this;
    }

    public function setRequiredStatement(string $language, string $label, string $value): self
    {
        $this->requiredStatement = new MetadataEntry(
            LabelMap::create($language, $label),
            LabelMap::create($language, $value),
        );
        return $this;
    }

    public function addService(Service $service): self
    {
        $this->service[] = $service;
        return $this;
    }
}
