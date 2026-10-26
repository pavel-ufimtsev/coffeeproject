<?php

namespace Tests\Feature\Shop;

use App\Services\Catalog\CoffeeQuiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ImportsCatalogFixture;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use ImportsCatalogFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCatalogFixture();
    }

    public function test_the_quiz_asks_its_questions_and_is_indexable()
    {
        $this->get('/quiz')
            ->assertOk()
            ->assertSee('data-quiz', false)
            ->assertSee('Как вы обычно готовите кофе?')
            ->assertSee('<meta name="robots" content="index,follow">', false);
    }

    public function test_answers_lead_to_matching_coffee_with_the_grind_picked()
    {
        $this->get('/quiz?brew=espresso&milk=milk&taste=classic&roast=any&form=ground')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSeeInOrder(['Лучше всего подходит', 'Бразилия Файн Кап', 'Подходит для способа «Эспрессо»', 'Вкус: шоколад и какао', 'Хорошо с молоком'])
            ->assertSee('<option value="espresso" selected', false);
    }

    public function test_bright_pour_over_answers_put_the_berry_coffee_first()
    {
        $quiz = app(CoffeeQuiz::class);
        $answers = $quiz->answers(['brew' => 'pourover', 'milk' => 'black', 'taste' => 'bright', 'roast' => 'any', 'form' => 'beans']);

        $this->assertSame('kenya-wolf', $quiz->recommend($answers)->first()['product']->slug);
    }

    public function test_incomplete_or_made_up_answers_show_the_questions_again()
    {
        $this->assertNull(app(CoffeeQuiz::class)->answers(['brew' => 'espresso']));
        $this->assertNull(app(CoffeeQuiz::class)->answers(['brew' => 'teapot', 'milk' => 'black', 'taste' => 'any', 'roast' => 'any', 'form' => 'beans']));

        $this->get('/quiz?brew=teapot')->assertOk()->assertSee('data-quiz', false);
    }
}
