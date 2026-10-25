<?php

namespace App\Console\Commands;

use App\Services\RetailCrm\RetailCrm;
use App\Services\RetailCrm\RetailCrmException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('retailcrm:check')]
#[Description('Check the retailCRM connection and list the stores, delivery and payment types')]
class RetailCrmCheckCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RetailCrm $crm): int
    {
        if (! $crm->isConfigured()) {
            $this->components->error('Set RETAILCRM_URL and RETAILCRM_API_KEY in src/.env first.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('URL', (string) $crm->url());
        $this->components->twoColumnDetail('Store (RETAILCRM_SITE)', $crm->site() ?? '<fg=yellow>not set</>');

        try {
            $credentials = $crm->credentials();
            $sites = $crm->sites();
            $deliveryTypes = $crm->deliveryTypes();
            $paymentTypes = $crm->paymentTypes();
        } catch (RetailCrmException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Connected. The API key can use '.count($credentials['credentials'] ?? []).' API methods.');

        $this->table(['Store code', 'Name', 'URL'], collect($sites)
            ->map(fn (array $site) => [$site['code'], $site['name'] ?? '', $site['url'] ?? ''])
            ->values());

        $this->table(['Delivery code', 'Name', 'Active'], collect($deliveryTypes)
            ->map(fn (array $type) => [$type['code'], $type['name'] ?? '', ($type['active'] ?? false) ? 'yes' : 'no'])
            ->values());

        $this->table(['Payment code', 'Name', 'Active'], collect($paymentTypes)
            ->map(fn (array $type) => [$type['code'], $type['name'] ?? '', ($type['active'] ?? false) ? 'yes' : 'no'])
            ->values());

        if ($crm->site() === null) {
            $this->components->warn('Set RETAILCRM_SITE to one of the store codes above.');

            return self::FAILURE;
        }

        if (! array_key_exists($crm->site(), $sites)) {
            $this->components->error("Store \"{$crm->site()}\" does not exist or this API key cannot access it.");

            return self::FAILURE;
        }

        $this->components->info('Ready: orders will go to store "'.$crm->site().'".');

        return self::SUCCESS;
    }
}
