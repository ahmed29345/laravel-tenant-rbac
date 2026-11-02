<?php

namespace Ahmed29345\TenantRbac\Resolvers;

use Ahmed29345\TenantRbac\Contracts\ResolvesTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Reads the tenant from the subdomain of the configured base domain,
 * e.g. acme.example.com resolves to "acme". Disabled until a base
 * domain is configured.
 */
final class SubdomainResolver implements ResolvesTenant
{
    public function resolve(Request $request): Model|int|string|null
    {
        $domain = config('tenant-rbac.resolution.domain');

        if (! is_string($domain) || $domain === '') {
            return null;
        }

        $host = strtolower($request->getHost());
        $suffix = '.'.strtolower(ltrim($domain, '.'));

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen($suffix));

        // Only a single label counts: "a.b.example.com" is not a tenant.
        return $subdomain !== '' && ! str_contains($subdomain, '.') ? $subdomain : null;
    }
}
