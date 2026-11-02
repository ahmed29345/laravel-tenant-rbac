<?php

namespace Ahmed29345\TenantRbac\Tests\Fixtures;

use Ahmed29345\TenantRbac\Concerns\HasTenantRoles;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasTenantRoles;

    protected $table = 'users';

    protected $fillable = ['name', 'email'];
}
