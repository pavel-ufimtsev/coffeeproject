<?php

namespace App\Console\Commands;

use App\Services\Blog\ArchiveExtractor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Run with the archive mounted read-only into a one-off container, e.g.
 *
 *     docker compose run --rm -v ../coffeeproject_yii/CoffeeProjectWebArchive/backups:/archive:ro \
 *         app php artisan blog:extract-archive /archive
 *
 * then copy the bundle to the server and run blog:import there.
 */
#[Signature('blog:extract-archive {archive : The web archive\'s backups folder} {--out= : Bundle folder (default storage/app/private/blog-restore)} {--only= : Extract one post by slug}')]
#[Description('Read the old blog out of the web archive into a restore bundle (posts.json + images)')]
class BlogExtractArchiveCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ArchiveExtractor $extractor): int
    {
        $out = $this->option('out') ?: storage_path('app/private/blog-restore');

        try {
            $run = $extractor->extract($this->argument('archive'), $out, $this->option('only') ?: null);
            foreach ($run as $line) {
                $this->line($line, verbosity: 'v');
            }
            $summary = $run->getReturn();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("{$summary['posts']} posts and {$summary['images']} images written to {$out}; {$summary['missing']} image references were never archived.");

        return self::SUCCESS;
    }
}
