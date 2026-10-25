<?php

namespace Tests\Feature;

use App\Services\RetailCrm\RetailCrm;
use App\Services\RetailCrm\RetailCrmException;
use Mockery\MockInterface;
use Tests\TestCase;

class RetailCrmCheckTest extends TestCase
{
    public function test_it_fails_when_retailcrm_is_not_configured()
    {
        $this->artisan('retailcrm:check')
            ->expectsOutputToContain('Set RETAILCRM_URL and RETAILCRM_API_KEY')
            ->assertFailed();
    }

    public function test_the_client_refuses_to_call_the_api_without_configuration()
    {
        $this->expectException(RetailCrmException::class);

        app(RetailCrm::class)->sites();
    }

    public function test_it_lists_the_account_references_and_confirms_the_store()
    {
        $this->fakeCrm(site: 'coffeeproject');

        $this->artisan('retailcrm:check')
            ->expectsOutputToContain('Connected')
            ->expectsOutputToContain('Ready: orders will go to store "coffeeproject"')
            ->assertSuccessful();
    }

    public function test_it_fails_for_an_unknown_store_code()
    {
        $this->fakeCrm(site: 'missing');

        $this->artisan('retailcrm:check')
            ->expectsOutputToContain('Store "missing" does not exist')
            ->assertFailed();
    }

    public function test_it_reports_api_errors()
    {
        $this->mock(RetailCrm::class, function (MockInterface $mock) {
            $mock->allows('isConfigured')->andReturnTrue();
            $mock->allows('url')->andReturn('https://example.retailcrm.ru');
            $mock->allows('site')->andReturnNull();
            $mock->allows('credentials')->andThrow(new RetailCrmException('retailCRM request failed: Wrong "apiKey" value.'));
        });

        $this->artisan('retailcrm:check')
            ->expectsOutputToContain('Wrong "apiKey" value.')
            ->assertFailed();
    }

    private function fakeCrm(?string $site): void
    {
        $this->mock(RetailCrm::class, function (MockInterface $mock) use ($site) {
            $mock->allows('isConfigured')->andReturnTrue();
            $mock->allows('url')->andReturn('https://example.retailcrm.ru');
            $mock->allows('site')->andReturn($site);
            $mock->allows('credentials')->andReturn(['credentials' => ['/api/orders/create']]);
            $mock->allows('sites')->andReturn([
                'coffeeproject' => ['code' => 'coffeeproject', 'name' => 'CoffeeProject', 'url' => 'https://coffeeproject.ru'],
            ]);
            $mock->allows('deliveryTypes')->andReturn([
                'courier' => ['code' => 'courier', 'name' => 'Курьер', 'active' => true],
            ]);
            $mock->allows('paymentTypes')->andReturn([
                'cash' => ['code' => 'cash', 'name' => 'Наличные', 'active' => true],
            ]);
        });
    }
}
