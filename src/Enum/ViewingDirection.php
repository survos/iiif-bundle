<?php
declare(strict_types=1);

namespace Survos\IiifBundle\Enum;

enum ViewingDirection: string
{
    case LEFT_TO_RIGHT = 'left-to-right';
    case RIGHT_TO_LEFT = 'right-to-left';
    case TOP_TO_BOTTOM = 'top-to-bottom';
    case BOTTOM_TO_TOP = 'bottom-to-top';
}
