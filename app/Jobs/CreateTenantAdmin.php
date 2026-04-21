<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateTenantAdmin
{
    protected $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle()
    {
        // Use the tenant's context to create the user in the correct database
        $this->tenant->run(function () {
            User::updateOrCreate(
                ['email' => 'admin@' . $this->tenant->id . '.com'],
                [
                    'name' => 'School Administrator',
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'is_active' => true,
                ]
            );
        });
    }
}
