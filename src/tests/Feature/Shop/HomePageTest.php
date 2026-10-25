<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ImportsCatalogFixture;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use ImportsCatalogFixture, RefreshDatabase;

    public function test_brew_methods_show_how_many_coffees_suit_them_and_from_what_price()
    {
        $this->importCatalogFixture();

        // Only Бразилия Файн Кап (690 ₽) is for espresso in the fixture.
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.url('/coffee/espresso').'"', false)
            ->assertSeeInOrder(['Эспрессо', '1 сорт', 'от 690']);
    }
}
