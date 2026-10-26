<?php

namespace Tests\Feature\Shop;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReviewStatus;
use App\Mail\ReviewSubmitted;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ImportsCatalogFixture;
use Tests\TestCase;

class ReviewsTest extends TestCase
{
    use ImportsCatalogFixture, RefreshDatabase;

    private Product $kenya;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCatalogFixture();
        $this->kenya = Product::where('slug', 'kenya-wolf')->firstOrFail();
    }

    private function review(array $overrides = []): array
    {
        return [
            'name' => 'Анна',
            'email' => 'Anna@Example.ru',
            'rating' => 5,
            'brew_method' => 'Аэропресс',
            'body' => 'Яркая ягодная кислинка, отлично в аэропрессе.',
            'consent' => '1',
            ...$overrides,
        ];
    }

    public function test_a_review_waits_for_approval_and_tells_the_shop()
    {
        Mail::fake();

        $this->postJson(route('reviews.store', $this->kenya), $this->review())
            ->assertOk()
            ->assertJsonPath('message', 'Спасибо, Анна! Отзыв появится на сайте после быстрой проверки.');

        $review = ProductReview::sole();
        $this->assertSame(ReviewStatus::Pending, $review->status);
        $this->assertSame('anna@example.ru', $review->email);
        Mail::assertQueued(ReviewSubmitted::class);

        $this->get('/coffee/kenya-wolf')->assertDontSee('Яркая ягодная кислинка');
    }

    public function test_approved_reviews_show_with_a_rating_and_structured_data()
    {
        $this->kenya->reviews()->create([...$this->review(), 'email' => 'anna@example.ru', 'status' => ReviewStatus::Approved, 'approved_at' => now()]);
        $this->kenya->reviews()->create([...$this->review(['name' => 'Олег', 'rating' => 4]), 'email' => 'oleg@example.ru', 'status' => ReviewStatus::Approved, 'approved_at' => now()]);
        $this->kenya->reviews()->create([...$this->review(['name' => 'Спамер', 'body' => 'Купите наши часы недорого прямо сейчас']), 'email' => 's@example.ru', 'status' => ReviewStatus::Rejected]);

        $this->get('/coffee/kenya-wolf')
            ->assertOk()
            ->assertSee('Яркая ягодная кислинка')
            ->assertSee('4,5')
            ->assertSee('2 отзыва')
            ->assertDontSee('Купите наши часы')
            ->assertSee('"@type":"AggregateRating","ratingValue":4.5,"reviewCount":2', false);
    }

    public function test_buyers_are_marked_by_their_order_email()
    {
        Mail::fake();
        $order = Order::create([
            'token' => Str::random(40), 'checkout_token' => Str::random(40), 'status' => OrderStatus::Completed,
            'first_name' => 'Анна', 'last_name' => 'Иванова', 'phone' => '+79097019007', 'email' => 'anna@example.ru',
            'delivery_method' => DeliveryMethod::Courier, 'delivery_price' => 0, 'city' => 'Екатеринбург', 'street' => 'ул. Вайнера', 'house' => '36',
            'payment_method' => PaymentMethod::Cash, 'subtotal' => 1177, 'total' => 1177, 'consent_at' => now(), 'confirmed_at' => now(),
        ]);
        $order->items()->create(['product_id' => $this->kenya->id, 'name' => 'Кения Волк', 'variant_label' => '250 г', 'price' => 1177, 'quantity' => 1, 'total' => 1177]);

        $this->postJson(route('reviews.store', $this->kenya), $this->review())->assertOk();

        $this->assertTrue(ProductReview::sole()->is_verified_buyer);
    }

    public function test_a_review_needs_a_rating_and_some_words()
    {
        $this->postJson(route('reviews.store', $this->kenya), $this->review(['rating' => null, 'body' => 'Норм']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating', 'body']);

        $this->postJson(route('reviews.store', $this->kenya), $this->review(['website' => 'http://spam.example']))
            ->assertUnprocessable();

        $this->assertSame(0, ProductReview::count());
    }

    public function test_admins_publish_and_reject_reviews()
    {
        $admin = User::factory()->admin()->create();
        $review = $this->kenya->reviews()->create([...$this->review(), 'email' => 'anna@example.ru', 'status' => ReviewStatus::Pending]);

        $this->actingAs(User::factory()->create())->get(route('admin.reviews.index'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.reviews.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/reviews/Index')->has('reviews.data', 1)->where('counts.pending', 1));

        $this->actingAs($admin)->patch(route('admin.reviews.update', $review), ['status' => 'approved'])->assertRedirect();
        $this->assertSame(ReviewStatus::Approved, $review->fresh()->status);
        $this->assertNotNull($review->fresh()->approved_at);

        $this->actingAs($admin)->patch(route('admin.reviews.update', $review), ['status' => 'rejected']);
        $this->assertNull($review->fresh()->approved_at);

        $this->actingAs($admin)->delete(route('admin.reviews.destroy', $review));
        $this->assertSame(0, ProductReview::count());
    }
}
