<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('catalog:import {--path= : Directory with products.json, redirects.json and images/} {--without-images : Skip processing photos}')]
#[Description('Restore the catalog (categories, products, photos, redirects) from the restore data')]
class CatalogImportCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CatalogImporter $importer): int
    {
        $path = $this->option('path') ?: database_path('data/catalog');

        try {
            foreach ($importer->import($path, ! $this->option('without-images')) as $line) {
                $this->components->info($line);
            }
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Run `php artisan retailcrm:sync-catalog` to load current prices from retailCRM.');

        return self::SUCCESS;
    }
}
