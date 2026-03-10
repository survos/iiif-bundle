<?php
declare(strict_types=1);

namespace Survos\IiifBundle;

use Survos\IiifBundle\Builder\ManifestBuilder;
use Survos\IiifBundle\Serializer\IiifSerializer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SurvosIiifBundle extends AbstractBundle
{
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
    }
}
