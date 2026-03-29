<?php

declare(strict_types=1);

namespace Survos\IiifBundle;

use Survos\CoreBundle\HasAssetMapperInterface;
use Survos\CoreBundle\Traits\HasAssetMapperTrait;
use Survos\IiifBundle\Builder\ManifestBuilder;
use Survos\IiifBundle\Service\ManifestLoader;
use Survos\IiifBundle\Service\ManifestSummaryExtractor;
use Survos\IiifBundle\Serializer\IiifSerializer;
use Survos\IiifBundle\Twig\Components\IiifViewer;
use Survos\IiifBundle\Twig\IiifExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SurvosIiifBundle extends AbstractBundle implements HasAssetMapperInterface
{
    use HasAssetMapperTrait;

    public const ASSET_NAMESPACE = '@survos/iiif';

    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $services = $container->services();

        $services
            ->set(IiifSerializer::class)
            ->autowire()
            ->autoconfigure();

        $services
            ->set(ManifestBuilder::class)
            ->autowire()
            ->autoconfigure();

        $services
            ->set(ManifestLoader::class)
            ->autowire()
            ->autoconfigure();

        $services
            ->set(ManifestSummaryExtractor::class)
            ->autowire()
            ->autoconfigure();

        $services
            ->set(IiifExtension::class)
            ->autowire()
            ->autoconfigure()
            ->tag('twig.extension');

        // Register IiifViewer Twig component only when ux-twig-component is available
        if (class_exists(\Symfony\UX\TwigComponent\Attribute\AsTwigComponent::class)) {
            $services
                ->set(IiifViewer::class)
                ->autowire()
                ->autoconfigure();
        }
    }
}
