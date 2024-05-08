<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $role1 = Role::create(['name' => 'Support']);
        $role2 = Role::create(['name' => 'Admin']);
        $role3 = Role::create(['name' => 'User']);

        Permission::create(['name' => 'users.index'])->syncRoles([$role1,$role2]);

    }
}
