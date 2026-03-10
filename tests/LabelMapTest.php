<?php

declare(strict_types=1);

namespace Survos\IiifBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\IiifBundle\Model\LabelMap;

final class LabelMapTest extends TestCase
{
    public function testCreateSingleLanguage(): void
    {
        $labelMap = LabelMap::create('en', 'Hello World');

        $this->assertSame(['en' => ['Hello World']], $labelMap->toArray());
    }

    public function testCreateMultilingual(): void
    {
        $labelMap = LabelMap::multilingual([
            'en' => 'Hello',
            'es' => 'Hola',
        ]);

        $expected = [
            'en' => ['Hello'],
            'es' => ['Hola'],
        ];
        $this->assertSame($expected, $labelMap->toArray());
    }

    public function testCreateFromArray(): void
    {
        $labelMap = LabelMap::fromArray('en', ['Page 1', 'Page 2']);

        $this->assertSame(['en' => ['Page 1', 'Page 2']], $labelMap->toArray());
    }

    public function testAddMultipleValues(): void
    {
        $labelMap = LabelMap::create('en', 'First');
        $labelMap->add('en', 'Second');
        $labelMap->add('en', 'Third');

        $this->assertSame(['en' => ['First', 'Second', 'Third']], $labelMap->toArray());
    }

    public function testJsonSerialize(): void
    {
        $labelMap = LabelMap::create('en', 'Test Label');
        $json = json_encode($labelMap);

        $this->assertJsonStringEqualsJsonString('{"en":["Test Label"]}', $json);
    }
}
