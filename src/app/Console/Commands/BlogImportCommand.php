<?php

namespace App\Console\Commands;

use App\Services\Blog\ArchiveImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('blog:import {--path= : Bundle folder from blog:extract-archive (default storage/app/private/blog-restore)} {--dry-run : Report what would change without writing} {--only= : Import one post by slug} {--report= : Where to write the CSV report (default storage/app/blog-import-report.csv)}')]
#[Description('Load the restored blog posts into the site as drafts')]
class BlogImportCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ArchiveImporter $importer): int
    {
        $path = $this->option('path') ?: storage_path('app/private/blog-restore');
        $dryRun = (bool) $this->option('dry-run');

        try {
            $run = $importer->import($path, $dryRun, $this->option('only') ?: null);
            foreach ($run as $line) {
                $this->line($line, verbosity: 'v');
            }
            $result = $run->getReturn();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $file = $this->option('report') ?: storage_path('app/blog-import-report.csv');
        ArchiveImporter::writeReport($result['report'], $file);

        $suggested = collect($result['report'])->countBy('suggested_action')->sortKeys();

        $this->components->info(($dryRun ? 'Dry run: ' : '')."{$result['created']} created, {$result['updated']} updated, {$result['skipped']} kept or failed, {$result['images']} images stored.");
        $this->components->twoColumnDetail('Suggested actions', $suggested->map(fn ($n, $action) => "{$action}: {$n}")->join(', '));
        $this->components->twoColumnDetail('Report', $file);
        $this->components->info('Every imported post is a draft. Nothing was published.');

        return self::SUCCESS;
    }
}
