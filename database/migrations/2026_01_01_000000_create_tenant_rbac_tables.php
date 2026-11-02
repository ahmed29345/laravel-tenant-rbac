<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('tenant-rbac.tables');

        Schema::create($tables['tenants'], function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create($tables['permissions'], function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create($tables['roles'], function (Blueprint $table) use ($tables) {
            $table->id();
            $table->foreignId('tenant_id')->constrained($tables['tenants'])->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create($tables['role_permission'], function (Blueprint $table) use ($tables) {
            $table->foreignId('role_id')->constrained($tables['roles'])->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained($tables['permissions'])->cascadeOnDelete();

            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create($tables['tenant_user_role'], function (Blueprint $table) use ($tables) {
            $table->foreignId('tenant_id')->constrained($tables['tenants'])->cascadeOnDelete();
            $table->foreignId('user_id')->constrained($tables['users'])->cascadeOnDelete();
            $table->foreignId('role_id')->constrained($tables['roles'])->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'role_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        $tables = config('tenant-rbac.tables');

        Schema::dropIfExists($tables['tenant_user_role']);
        Schema::dropIfExists($tables['role_permission']);
        Schema::dropIfExists($tables['roles']);
        Schema::dropIfExists($tables['permissions']);
        Schema::dropIfExists($tables['tenants']);
    }
};
