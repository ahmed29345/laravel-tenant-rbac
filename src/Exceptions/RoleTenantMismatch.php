<?php

namespace Ahmed29345\TenantRbac\Exceptions;

use InvalidArgumentException;

final class RoleTenantMismatch extends InvalidArgumentException implements TenantRbacException
{
    public static function make(string $role, int|string $roleTenant, int|string $tenant): self
    {
        return new self("Role [{$role}] belongs to tenant [{$roleTenant}] and cannot be used in tenant [{$tenant}].");
    }
}
