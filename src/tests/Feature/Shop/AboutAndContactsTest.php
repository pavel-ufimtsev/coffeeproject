<?php

namespace Tests\Feature\Shop;

use App\Mail\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AboutAndContactsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function message(array $overrides = []): array
    {
        return [
            'name' => 'Анна',
            'email' => 'anna@example.ru',
            'phone' => '8 909 701 90 07',
            'topic' => 'coffee',
            'message' => 'Подскажите кофе для гейзерной кофеварки.',
            'consent' => '1',
            ...$overrides,
        ];
    }

    public function test_the_about_page_tells_the_story_and_names_the_company()
    {
        $this->get('/about')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('about').'">', false)
            ->assertSee('Александра Уфимцева')
            ->assertDontSee('Павел')
            ->assertSee('кофейный паспорт')
            ->assertSee(config('shop.ogrn'))
            ->assertSee('"@type":"AboutPage"', false)
            ->assertSee('"foundingDate":"2012"', false);
    }

    public function test_the_contacts_page_shows_the_form_and_the_shop_contacts()
    {
        $this->get('/contacts')
            ->assertOk()
            ->assertSee('action="'.route('contacts.store').'"', false)
            ->assertSee('data-contact-form', false)
            ->assertSee(config('shop.phone'))
            ->assertSee('"@type":"ContactPage"', false);
    }

    public function test_every_page_links_about_and_contacts_in_the_footer()
    {
        $this->get('/')
            ->assertSee('href="'.route('about').'"', false)
            ->assertSee('href="'.route('contacts').'"', false);
    }

    public function test_the_old_contact_and_press_pages_redirect()
    {
        $this->get('/more/contact')->assertRedirect('/contacts')->assertStatus(301);
        $this->get('/press')->assertRedirect('/about')->assertStatus(301);
    }

    public function test_a_message_sent_in_the_background_is_mailed_to_the_shop_and_thanks_in_russian()
    {
        Mail::fake();
        config(['shop.contact_email' => 'hello@coffeeproject.ru']);

        $this->postJson('/contacts', $this->message())
            ->assertOk()
            ->assertJson(['message' => 'Спасибо, Анна! Мы получили ваше сообщение и скоро ответим на anna@example.ru.']);

        Mail::assertQueued(ContactMessage::class, function (ContactMessage $mail) {
            return $mail->hasTo('hello@coffeeproject.ru')
                && $mail->hasReplyTo('anna@example.ru')
                && $mail->data['phone'] === '+7 909 701-90-07'
                && $mail->data['topic'] === 'coffee';
        });
    }

    public function test_without_javascript_the_form_comes_back_with_the_thanks()
    {
        Mail::fake();

        $this->post('/contacts', $this->message(['phone' => '']))
            ->assertRedirect(route('contacts'));

        $this->get('/contacts')->assertSee('Спасибо, Анна!');

        Mail::assertQueued(ContactMessage::class, fn (ContactMessage $mail) => $mail->data['phone'] === null);
    }

    public function test_invalid_fields_get_messages_and_nothing_is_sent()
    {
        Mail::fake();

        $this->postJson('/contacts', $this->message([
            'name' => '',
            'email' => 'not-an-email',
            'phone' => '123',
            'topic' => 'jobs',
            'message' => 'Привет',
            'consent' => '',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'topic', 'message', 'consent']);

        Mail::assertNothingQueued();
    }

    public function test_bots_filling_the_honeypot_are_turned_away()
    {
        Mail::fake();

        $this->postJson('/contacts', $this->message(['website' => 'https://spam.example']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('website');

        Mail::assertNothingQueued();
    }

    public function test_the_email_shows_the_message_and_who_sent_it()
    {
        $mail = new ContactMessage([
            'name' => 'Анна',
            'email' => 'anna@example.ru',
            'phone' => '+7 909 701-90-07',
            'topic' => 'wholesale',
            'message' => 'Нужен кофе в офис, 5 кг в месяц.',
        ]);

        $mail->assertHasSubject('Сообщение с сайта: Опт и кофе в офис, Анна');
        $mail->assertSeeInHtml('Нужен кофе в офис, 5 кг в месяц.');
        $mail->assertSeeInText('anna@example.ru');
    }
}
