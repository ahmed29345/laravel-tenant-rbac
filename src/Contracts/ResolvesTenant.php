<?php

namespace Ahmed29345\TenantRbac\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface ResolvesTenant
{
    /**
     * Find the tenant a request is for.
     *
     * Return a tenant model, an identifier to look up in the configured
     * identifier column, or null when this resolver does not apply.
     */
    public function resolve(Request $request): Model|int|string|null;
}
