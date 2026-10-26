<?php

namespace App\Services\Catalog;

use App\Enums\ProductType;
use App\Models\FilterValue;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * The "which coffee suits me" quiz: five questions, then the coffees on
 * sale that fit the answers best, each with the reasons it was picked.
 */
class CoffeeQuiz
{
    /** Taste filters that read as classic, everyday coffee. */
    private const CLASSIC = ['cacao', 'nutty', 'caramel', 'sweet', 'bitter'];

    /** Taste filters that read as bright and unusual. */
    private const BRIGHT = ['berry', 'citrus', 'floral', 'sour', 'fruit'];

    /** Tastes that go well with milk and sugar. */
    private const WITH_MILK = ['cacao', 'nutty', 'caramel', 'sweet'];

    /** Brew method filter → grind choice at purchase. */
    public const GRINDS = [
        'espresso' => 'espresso',
        'aeropress' => 'aeropress',
        'cezve' => 'cezve',
        'chemex' => 'pourover',
        'pourover' => 'pourover',
        'drip' => 'drip',
        'french' => 'french',
        'geyzer' => 'moka',
    ];

    /**
     * The questions with their options, value => label.
     *
     * @return array<string, array{title: string, hint: string, options: array<string, string>}>
     */
    public function questions(): array
    {
        $brews = $this->brewMethods()->mapWithKeys(fn (FilterValue $v) => [$v->slug => $v->name])->all();

        return [
            'brew' => [
                'title' => 'Как вы обычно готовите кофе?',
                'hint' => 'Одни сорта лучше раскрываются в эспрессо, другие — в воронке или турке.',
                'options' => $brews + ['any' => 'По-разному'],
            ],
            'milk' => [
                'title' => 'Что добавляете в кофе?',
                'hint' => 'С молоком лучше всего звучат шоколадные и ореховые сорта, а яркие кислотные — без добавок.',
                'options' => ['black' => 'Ничего, пью чёрный', 'milk' => 'Молоко или сливки', 'sweet' => 'Сахар или сироп'],
            ],
            'taste' => [
                'title' => 'Какой вкус вам ближе?',
                'hint' => 'Все наши сорта — чистый кофе без ароматизаторов. Разный вкус дают страна, ферма, обработка и обжарка.',
                'options' => [
                    'classic' => 'Классический: шоколад, орехи, карамель',
                    'balanced' => 'Сбалансированный, с лёгкой фруктовой ноткой',
                    'bright' => 'Яркий и необычный: ягоды, цитрусы, цветы',
                    'any' => 'Положусь на вас',
                ],
            ],
            'roast' => [
                'title' => 'Какая обжарка нравится?',
                'hint' => 'Светлая — ярче и кислотнее, тёмная — плотнее и горче, средняя — посередине.',
                'options' => ['light' => 'Светлая', 'medium' => 'Средняя', 'dark' => 'Тёмная', 'any' => 'Не знаю'],
            ],
            'form' => [
                'title' => 'В зёрнах или молотый?',
                'hint' => 'Молотый кофе быстрее теряет аромат, но если кофемолки нет, смелем бесплатно под ваш способ.',
                'options' => ['beans' => 'В зёрнах, есть кофемолка', 'ground' => 'Молотый'],
            ],
        ];
    }

    /**
     * Keep only answers the quiz knows; null when any question is unanswered.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>|null
     */
    public function answers(array $input): ?array
    {
        $answers = [];

        foreach ($this->questions() as $key => $question) {
            $value = $input[$key] ?? null;

            if (! is_string($value) || ! array_key_exists($value, $question['options'])) {
                return null;
            }

            $answers[$key] = $value;
        }

        return $answers;
    }

    /**
     * The best coffees for the answers, best first, with the reasons.
     *
     * @param  array<string, string>  $answers
     * @return Collection<int, array{product: Product, reasons: list<string>}>
     */
    public function recommend(array $answers, int $limit = 3): Collection
    {
        $brewName = $this->brewMethods()->firstWhere('slug', $answers['brew'])?->name;

        return Product::active()
            ->whereIn('type', [ProductType::Coffee, ProductType::Drip])
            ->with(['activeVariants', 'mainImage', 'roaster', 'category.parent', 'filterValues.group'])
            ->get()
            ->filter(fn (Product $product) => $product->isPurchasable())
            ->map(fn (Product $product) => $this->score($product, $answers, $brewName))
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->map(fn (array $match) => ['product' => $match['product'], 'reasons' => $match['reasons']]);
    }

    /**
     * @param  array<string, string>  $answers
     * @return array{product: Product, score: float, reasons: list<string>}
     */
    private function score(Product $product, array $answers, ?string $brewName): array
    {
        // A plain collection: on an Eloquent one, only() matches model ids, not keys.
        $tastes = $product->filterValues->filter(fn (FilterValue $v) => $v->group->slug === 'taste')->keyBy('slug')->toBase();
        $brews = $product->filterValues->filter(fn (FilterValue $v) => $v->group->slug === 'brew')->pluck('slug');
        $level = $product->roastLevel();
        $score = $product->is_featured ? 0.5 : 0.0;
        $reasons = [];

        if ($answers['brew'] !== 'any' && $brews->contains($answers['brew'])) {
            $score += 4;
            $reasons[] = "Подходит для способа «{$brewName}»";
        }

        $liked = match ($answers['taste']) {
            'classic' => self::CLASSIC,
            'bright' => self::BRIGHT,
            'balanced' => ['fruit', 'sweet', 'caramel', 'cacao'],
            default => [],
        };
        $disliked = match ($answers['taste']) {
            'classic' => ['berry', 'citrus', 'floral', 'sour'],
            'bright' => ['bitter'],
            default => [],
        };

        if ($answers['milk'] !== 'black') {
            $liked = array_unique([...$liked, ...self::WITH_MILK]);
            $disliked = array_unique([...$disliked, 'sour', 'floral']);
            $score += $level !== null && $level >= 3 ? 1 : 0;
        }

        $hits = $tastes->only($liked);
        $score += min($hits->count(), 3) * 1.5 - $tastes->only($disliked)->count();

        if ($hits->isNotEmpty()) {
            $reasons[] = 'Вкус: '.mb_strtolower($hits->take(3)->pluck('name')->implode(', '));
        }

        $roastScore = match ($answers['roast']) {
            'light' => $level === null ? 0 : ($level <= 2 ? 2 : ($level === 3 ? 0.5 : -1)),
            'medium' => $level === 3 ? 2 : 0,
            'dark' => $level === null ? 0 : ($level >= 4 ? 2 : ($level === 3 ? 0.5 : -1)),
            default => 0,
        };
        $score += $roastScore;

        if ($roastScore >= 2 && filled($product->specs['roast'] ?? null)) {
            $reasons[] = $product->specs['roast'];
        }

        if ($answers['milk'] !== 'black' && $tastes->only(self::WITH_MILK)->isNotEmpty()) {
            $reasons[] = 'Хорошо с молоком';
        }

        return ['product' => $product, 'score' => $score, 'reasons' => array_values(array_unique($reasons))];
    }

    /**
     * @return Collection<int, FilterValue>
     */
    private function brewMethods(): Collection
    {
        return once(fn () => FilterValue::whereRelation('group', 'slug', 'brew')->orderBy('sort')->get());
    }
}
