<?php
declare(strict_types=1);

namespace Survos\IiifBundle;

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
        // No services to register - all classes are designed to be instantiated directly
        // The ManifestBuilder and Model classes are stateless utilities
    }
}
