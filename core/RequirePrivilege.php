<?php

declare(strict_types=1);

namespace Core;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class RequirePrivilege
{
    public function __construct(public Privilege $privilege)
    {
    }
}
