<?php

namespace App\Console\Commands;

use App\Services\RetailCrm\CatalogSync;
use App\Services\RetailCrm\RetailCrm;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('retailcrm:sync-catalog')]
#[Description('Copy prices and status of linked products from retailCRM')]
class RetailCrmSyncCatalogCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RetailCrm $crm, CatalogSync $sync): int
    {
        if (! $crm->isConfigured()) {
            $this->components->error('Set RETAILCRM_URL and RETAILCRM_API_KEY in src/.env first.');

            return self::FAILURE;
        }

        $run = $sync->run();

        if ($run->error) {
            $this->components->error($run->error);

            return self::FAILURE;
        }

        $this->components->info("Checked {$run->products_checked} products, {$run->variants_changed} variant prices changed.");

        foreach ($run->changes ?? [] as $change) {
            $this->components->twoColumnDetail(
                "{$change['product']} · {$change['variant']}",
                $this->formatPrice($change['from']).' → '.$this->formatPrice($change['to']),
            );
        }

        if ($run->issues) {
            $this->components->warn(count($run->issues).' problems need attention:');
            $this->table(['Product', 'Variant', 'retailCRM', 'Problem'], collect($run->issues)->map(fn (array $issue) => [
                $issue['product'],
                $issue['variant'] ?? '-',
                $issue['retailcrm_product_id'].($issue['retailcrm_offer_id'] ? ' / '.$issue['retailcrm_offer_id'] : ''),
                $issue['problem'],
            ]));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{price: ?string, old_price: ?string}  $prices
     */
    private function formatPrice(array $prices): string
    {
        $price = $prices['price'] === null ? '-' : number_format((float) $prices['price'], 0, ',', ' ');

        return $prices['old_price'] === null
            ? $price
            : $price.' (было '.number_format((float) $prices['old_price'], 0, ',', ' ').')';
    }
}
