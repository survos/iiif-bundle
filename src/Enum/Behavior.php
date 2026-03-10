<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Enum;

enum Behavior: string
{
    case PAGED = 'paged';
    case CONTINUOUS = 'continuous';
    case INDIVIDUALS = 'individuals';
    case AUTO_ADVANCE = 'auto-advance';
    case NO_ADVANCE = 'no-advance';
    case START_NEW_SCROLL = 'start-new-scroll';
    case HIDDEN = 'hidden';
    case UNSUPPORTED = 'unsupported';
}
