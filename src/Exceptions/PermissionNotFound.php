<?php

namespace Ahmed29345\TenantRbac\Exceptions;

use InvalidArgumentException;

final class PermissionNotFound extends InvalidArgumentException implements TenantRbacException
{
    /**
     * @param  array<int, string>  $names
     */
    public static function named(array $names): self
    {
        return new self('Permission(s) not found: '.implode(', ', $names).'.');
    }
}
