<?php

declare(strict_types=1);

namespace Core;

enum Privilege: int
{
    case GUEST = 0;
    case USER = 1;
    case ADMIN = 2;
    case ROOT = 3;
}
