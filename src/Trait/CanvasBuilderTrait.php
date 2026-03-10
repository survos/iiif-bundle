<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Trait;

use Survos\IiifBundle\Enum\Motivation;
use Survos\IiifBundle\Model\Annotation;
use Survos\IiifBundle\Model\AnnotationPage;
use Survos\IiifBundle\Model\ImageService3;
use Survos\IiifBundle\Model\ResourceItem;
use Survos\IiifBundle\Model\TextualBody;

trait CanvasBuilderTrait
{
    public function addImage(
        string $annotationId,
        string $imageUrl,
        string $format = 'image/jpeg',
        ?int $width = null,
        ?int $height = null,
        ?ImageService3 $service = null,
    ): self {
        $resource = ResourceItem::createImage($imageUrl, $format, $width, $height);
        if ($service) {
            $resource->addService($service);
        }

        $annotation = Annotation::createPainting($annotationId, $resource, $this->id);
        $annotationPage = AnnotationPage::create($annotationId . '/page');
        $annotationPage->addItem($annotation);

        $this->addItem($annotationPage);
        return $this;
    }

    public function addSupplementingText(
        string $annotationId,
        string $text,
        string $language = 'en',
    ): self {
        $body = TextualBody::create($text, $language);
        $annotation = Annotation::createSupplementing($annotationId, $body, $this->id);
        $annotationPage = AnnotationPage::create($annotationId . '/page');
        $annotationPage->addItem($annotation);

        $this->addAnnotationPage($annotationPage);
        return $this;
    }

    public function addWordAnnotation(
        string $annotationId,
        string $text,
        string $language,
        int $x,
        int $y,
        int $width,
        int $height,
    ): self {
        $target = $this->id . "#xywh={$x},{$y},{$width},{$height}";
        $body = TextualBody::create($text, $language);
        $annotation = Annotation::createSupplementing($annotationId, $body, $target);
        $annotationPage = AnnotationPage::create($annotationId . '/page');
        $annotationPage->addItem($annotation);

        $this->addAnnotationPage($annotationPage);
        return $this;
    }
}
