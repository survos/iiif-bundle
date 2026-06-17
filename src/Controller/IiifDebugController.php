<?php

declare(strict_types=1);

namespace Survos\IiifBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Standalone IIIF debug viewer: paste (or link to) a IIIF Presentation manifest URL
 * and render it in the diva.js document viewer. Handy for inspecting a manifest —
 * ours or a third party's — without wiring it into a host page.
 */
final class IiifDebugController extends AbstractController
{
    #[Route('/iiif/debug', name: 'survos_iiif_debug', methods: ['GET'])]
    public function debug(Request $request): Response
    {
        return $this->render('@SurvosIiif/debug/diva.html.twig', [
            'manifestUrl' => trim((string) $request->query->get('manifest', '')),
        ]);
    }
}
