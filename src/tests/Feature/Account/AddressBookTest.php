<?php

namespace Tests\Feature\Account;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressBookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * @return array<string, string>
     */
    private function address(array $overrides = []): array
    {
        return ['label' => 'Дом', 'city' => 'Екатеринбург', 'street' => 'ул. Вайнера', 'house' => '36', 'apartment' => '28', ...$overrides];
    }

    public function test_addresses_can_be_added_edited_and_removed()
    {
        $this->actingAs($this->user)->get(route('account.addresses.index'))->assertOk()->assertSee('Сохранённых адресов пока нет');

        $this->actingAs($this->user)->post(route('account.addresses.store'), $this->address())->assertRedirect(route('account.addresses.index'));
        $this->actingAs($this->user)->post(route('account.addresses.store'), $this->address(['label' => 'Работа', 'street' => 'ул. Малышева']));

        [$home, $work] = $this->user->addresses()->get();
        $this->assertTrue($home->is_default);
        $this->assertFalse($work->is_default);

        $this->actingAs($this->user)->get(route('account.addresses.index'))
            ->assertSee('Дом')
            ->assertSee('Основной')
            ->assertSee('Екатеринбург, ул. Вайнера, д. 36, кв. 28');

        $this->actingAs($this->user)->put(route('account.addresses.update', $work), $this->address(['label' => 'Офис', 'house' => '5']));
        $this->assertSame(['Офис', '5'], [$work->fresh()->label, $work->fresh()->house]);

        $this->actingAs($this->user)->patch(route('account.addresses.default', $work));
        $this->assertTrue($work->fresh()->is_default);
        $this->assertFalse($home->fresh()->is_default);

        $this->actingAs($this->user)->delete(route('account.addresses.destroy', $work));
        $this->assertTrue($home->fresh()->is_default, 'Deleting the default promotes the one left.');
        $this->assertSame(1, $this->user->addresses()->count());
    }

    public function test_labels_are_unique_per_customer()
    {
        $this->user->addresses()->create($this->address());
        User::factory()->create()->addresses()->create($this->address());

        $this->actingAs($this->user)->post(route('account.addresses.store'), $this->address())->assertSessionHasErrors('label');
        $this->assertSame(2, Address::count());
    }

    public function test_someone_elses_address_is_not_found()
    {
        $other = User::factory()->create()->addresses()->create($this->address());

        $this->actingAs($this->user)->get(route('account.addresses.edit', $other))->assertNotFound();
        $this->actingAs($this->user)->put(route('account.addresses.update', $other), $this->address(['street' => 'x']))->assertNotFound();
        $this->actingAs($this->user)->delete(route('account.addresses.destroy', $other))->assertNotFound();
        $this->assertSame('ул. Вайнера', $other->fresh()->street);
    }

    public function test_guests_are_sent_to_log_in()
    {
        $this->get(route('account.addresses.index'))->assertRedirect(route('login'));
    }
}
