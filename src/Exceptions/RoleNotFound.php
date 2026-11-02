<?php

namespace Ahmed29345\TenantRbac\Exceptions;

use InvalidArgumentException;

final class RoleNotFound extends InvalidArgumentException implements TenantRbacException
{
    public static function named(string $name, int|string $tenantId): self
    {
        return new self("Role [{$name}] does not exist in tenant [{$tenantId}].");
    }
}
