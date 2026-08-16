<?php

declare(strict_types=1);

namespace Survos\IiifBundle;

use Survos\Kit\AbstractUxBundle;
use Survos\Kit\SurvosKitBundle;
use Survos\Kit\Traits\HasConfigurableRoutes;
use Survos\IiifBundle\Builder\ManifestBuilder;
use Survos\IiifBundle\Service\ManifestLoader;
use Survos\IiifBundle\Service\ManifestSummaryExtractor;
use Survos\IiifBundle\Serializer\IiifSerializer;
use Survos\IiifBundle\Twig\Components\IiifPdf;
use Survos\IiifBundle\Twig\Components\IiifViewer;
use Survos\IiifBundle\Twig\IiifExtension;
use Survos\Kit\Routing\ConfigurableRoutesInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

#[RequiredBundle(SurvosKitBundle::class)]
// Symfony\Component\HttpKernel\Bundle\Bundle <-- Flex auto-registration marker (see Survos\Kit\AbstractSurvosBundle)
final class SurvosIiifBundle extends AbstractUxBundle implements ConfigurableRoutesInterface
{
    use HasConfigurableRoutes;

    public function configure(DefinitionConfigurator $definition): void
    {
        // This bundle's ENTIRE route surface is one developer tool: /iiif/debug,
        // which renders any manifest URL handed to it in ?manifest=. It is off by
        // default because it is a debug page that would otherwise be published
        // unauthenticated by every app that installs the bundle, and because it
        // renders remote content from a URL an anonymous visitor controls. Apps
        // that want it opt in:
        //
        //     survos_iiif:
        //         routes_enabled: true
        //
        // Default prefix stays '' so opting in restores the original /iiif/debug
        // URL unchanged (the prefix is what the controller attribute appends to).
        // Everything else the bundle exposes — the IiifViewer/IiifPdf Twig
        // components, Builder, Serializer, Service — is unrouted and unaffected.
        // See survos/mono#43.
        $this->addRouteOptions($definition->rootNode()->children(), '', defaultEnabled: false);
    }

    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        // parent::loadExtension() registers the diva debug viewer's routes.
        parent::loadExtension($config, $container, $builder);

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

        // Register the viewer Twig components only when ux-twig-component is available
        if (class_exists(\Symfony\UX\TwigComponent\Attribute\AsTwigComponent::class)) {
            $services
                ->set(IiifViewer::class)
                ->autowire()
                ->autoconfigure();

            $services
                ->set(IiifPdf::class)
                ->autowire()
                ->autoconfigure();
        }
    }
}
