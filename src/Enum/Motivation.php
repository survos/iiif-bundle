<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Enum;

enum Motivation: string
{
    case PAINTING = 'painting';
    case SUPPLEMENTING = 'supplementing';
    case COMMENTING = 'commenting';
    case TAGGING = 'tagging';
    case CLASSIFYING = 'classifying';
    case IDENTIFYING = 'identifying';
    case LINKING = 'linking';
}
