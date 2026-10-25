<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_privacy_policy_keeps_the_old_address_and_names_the_operator()
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('<h1>Политика конфиденциальности</h1>', false)
            ->assertSee(config('shop.legal_full_name'))
            ->assertSee(config('shop.inn'))
            ->assertSee('<link rel="canonical" href="'.url('/privacy-policy').'">', false);
    }

    public function test_the_terms_of_use_name_the_seller_and_link_the_policy()
    {
        $this->get('/terms')
            ->assertOk()
            ->assertSee('<h1>Условия использования</h1>', false)
            ->assertSee(config('shop.ogrn'))
            ->assertSee('href="'.route('legal.privacy').'"', false);
    }

    public function test_every_page_links_both_documents_in_the_footer()
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.route('legal.terms').'"', false)
            ->assertSee('href="'.route('legal.privacy').'"', false)
            ->assertDontSee('site-footer__wordmark', false);
    }
}
