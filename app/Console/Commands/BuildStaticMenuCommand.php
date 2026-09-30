<?php

namespace App\Console\Commands;

use App\Services\MenuStaticBuilder;
use Exception;
use Illuminate\Console\Command;

class BuildStaticMenuCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'menu:build-static';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild static menu JSON files from database';

    /**
     * Execute the console command.
     */
    public function handle(MenuStaticBuilder $builder): int
    {
        $this->info('Building static menu files...');

        try {
            $result = $builder->build();

            $this->info('Categories: '.$result['categories_count']);
            $this->info('Products: '.$result['products_count']);
            $this->info('Version: '.$result['version']);
            $this->info('Generated at: '.$result['generated_at']);

            $this->newLine();
            $this->info('✓ Static menu built successfully.');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Failed to build static menu: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
