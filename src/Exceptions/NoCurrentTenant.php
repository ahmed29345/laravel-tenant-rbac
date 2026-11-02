<?php

namespace Ahmed29345\TenantRbac\Exceptions;

use LogicException;

final class NoCurrentTenant extends LogicException implements TenantRbacException
{
    public static function forModel(object $model): self
    {
        return new self(sprintf(
            'Cannot save [%s] without a tenant: set a current tenant or fill the tenant key explicitly.',
            $model::class,
        ));
    }
}
