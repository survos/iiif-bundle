<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Serializer;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

final class IiifSerializer
{
    private SymfonySerializer $serializer;

    public function __construct()
    {
        $encoders = [new JsonEncoder()];
        $normalizers = [
            new JsonSerializableNormalizer(),
            new ArrayDenormalizer(),
        ];

        $this->serializer = new SymfonySerializer($normalizers, $encoders);
    }

    public function getSerializer(): SymfonySerializer
    {
        return $this->serializer;
    }

    public function serialize(array $data, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return $this->serializer->serialize($data, 'json', [
            'json_encode_flags' => $flags,
        ]);
    }
}
