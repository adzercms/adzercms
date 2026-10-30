<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Analytics;

enum Step: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
}
