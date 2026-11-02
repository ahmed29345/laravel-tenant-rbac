<?php

namespace Ahmed29345\TenantRbac\Exceptions;

use RuntimeException;

final class CrossTenantWrite extends RuntimeException implements TenantRbacException
{
    public static function make(object $model, int|string|null $modelTenant, int|string $currentTenant): self
    {
        return new self(sprintf(
            'Refusing to write [%s] belonging to tenant [%s] while acting as tenant [%s].',
            $model::class,
            $modelTenant ?? 'none',
            $currentTenant,
        ));
    }
}
