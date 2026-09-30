<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('app:migrate-menu-from-excel');
    }
}
