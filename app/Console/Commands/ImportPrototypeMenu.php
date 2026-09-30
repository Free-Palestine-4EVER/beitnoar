<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportPrototypeMenu extends Command
{
    protected $signature = 'menu:import-prototype {--fresh : Drop existing data before importing} {--force : Force operation without prompt}';
    protected $description = 'Import the menu from the HTML prototype into the database';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            if (!$this->option('force') && !$this->confirm('This will delete all existing categories, products, and tags. Continue?')) {
                $this->info('Import cancelled.');
                return 0;
            }

            \App\Models\Product::query()->delete();
            \App\Models\Category::query()->delete();
            \App\Models\Tag::query()->delete();
            $this->info('Existing data cleared.');
        }

        $this->call('db:seed', ['--class' => 'Database\\Seeders\\TagSeeder']);
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\MenuSeeder']);

        $this->info('Menu imported successfully from prototype data!');
        $this->info('Categories: ' . \App\Models\Category::count());
        $this->info('Products: ' . \App\Models\Product::count());
        $this->info('Tags: ' . \App\Models\Tag::count());

        return 0;
    }
}
