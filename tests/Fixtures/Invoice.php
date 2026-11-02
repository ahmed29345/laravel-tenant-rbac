<?php

namespace Ahmed29345\TenantRbac\Tests\Fixtures;

use Ahmed29345\TenantRbac\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'number', 'total'];
}
