<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\FilterGroup;
use App\Models\FilterValue;
use App\Models\Location;
use App\Models\ProductCollection;
use App\Models\Roaster;
use Illuminate\Database\Seeder;

/**
 * The fixed part of the catalog: categories, filters, collections, roasters
 * and locations. Safe to run again: records are matched by slug and updated.
 */
class CatalogStructureSeeder extends Seeder
{
    /**
     * Root categories and their children. Root slugs are URL prefixes and
     * match the old site, so they must not change.
     *
     * @var array<string, array{name: string, h1: string, seo_title: string, seo_description: string, children: array<string, string>}>
     */
    private const CATEGORIES = [
        'coffee' => [
            'name' => 'Кофе',
            'h1' => 'Свежеобжаренный кофе в зёрнах',
            'seo_title' => 'Кофе в зёрнах: купить свежеобжаренный кофе с доставкой по России | Coffee Project',
            'seo_description' => 'Свежеобжаренный кофе в зёрнах от обжарщика Coffee Project из Екатеринбурга. Обжариваем под заказ, доставляем по всей России.',
            'children' => [],
        ],
        'chaj' => [
            'name' => 'Чай',
            'h1' => 'Чай',
            'seo_title' => 'Чай: купить листовой чай с доставкой по России | Coffee Project',
            'seo_description' => 'Чёрный, зелёный, травяной чай и матча. Доставка из Екатеринбурга по всей России.',
            'children' => [
                'chjornyj-chaj' => 'Чёрный чай',
                'zelenyj-chaj' => 'Зелёный чай',
                'ulun' => 'Улун',
                'pujer' => 'Пуэр',
                'matcha' => 'Матча',
                'travyanoj-chaj' => 'Травяной и фруктовый чай',
            ],
        ],
        'accessory' => [
            'name' => 'Аксессуары',
            'h1' => 'Аксессуары для кофе',
            'seo_title' => 'Аксессуары для кофе: воронки, фильтры, кофемолки | Coffee Project',
            'seo_description' => 'Всё для заваривания кофе дома: воронки, фильтры, кофемолки, термостаканы и книги о кофе.',
            'children' => [
                'brewers' => 'Для заваривания',
                'filters' => 'Фильтры',
                'grinders' => 'Кофемолки',
                'cups' => 'Термостаканы',
                'books' => 'Книги',
                'care' => 'Чистка и уход',
                'clothing' => 'Одежда',
                'certificates' => 'Подарочные сертификаты',
                'drinks' => 'Напитки',
            ],
        ],
    ];

    /**
     * Coffee filter groups. Each value: [name, page heading, landing page?, parent value].
     *
     * @var array<string, array{name: string, values: array<string, array{0: string, 1: string|null, 2: bool, 3?: string}>}>
     */
    private const COFFEE_FILTERS = [
        'brew' => [
            'name' => 'Способ приготовления',
            'values' => [
                'espresso' => ['Эспрессо', 'Кофе для эспрессо', true],
                'pourover' => ['Пуровер', 'Кофе для пуровера', true],
                'chemex' => ['Кемекс', 'Кофе для кемекса', true],
                'aeropress' => ['Аэропресс', 'Кофе для аэропресса', true],
                'drip' => ['Капельная кофеварка', 'Кофе для капельной кофеварки', true],
                'french' => ['Френч-пресс', 'Кофе для френч-пресса', true],
                'cezve' => ['Турка', 'Кофе для турки', true],
                'geyzer' => ['Гейзерная кофеварка', 'Кофе для гейзерной кофеварки', true],
            ],
        ],
        'origin' => [
            'name' => 'Происхождение',
            'values' => [
                'africa' => ['Африка', 'Кофе из Африки', true],
                'south-america' => ['Южная Америка', 'Кофе из Южной Америки', true],
                'central-america' => ['Центральная Америка', 'Кофе из Центральной Америки', true],
                'asia' => ['Азия', 'Кофе из Азии', true],
                'kenya' => ['Кения', 'Кофе из Кении', true, 'africa'],
                'ethiopia' => ['Эфиопия', 'Кофе из Эфиопии', true, 'africa'],
                'colombia' => ['Колумбия', 'Кофе из Колумбии', true, 'south-america'],
                'brazil' => ['Бразилия', 'Кофе из Бразилии', true, 'south-america'],
                'nicaragua' => ['Никарагуа', 'Кофе из Никарагуа', true, 'central-america'],
                'honduras' => ['Гондурас', 'Кофе из Гондураса', true, 'central-america'],
                'mexico' => ['Мексика', 'Кофе из Мексики', true, 'central-america'],
                'vietnam' => ['Вьетнам', 'Кофе из Вьетнама', true, 'asia'],
            ],
        ],
        'taste' => [
            'name' => 'Вкус',
            'values' => [
                'berry' => ['Ягодный', 'Кофе с ягодным вкусом', true],
                'fruit' => ['Фруктовый', 'Кофе с фруктовым вкусом', true],
                'citrus' => ['Цитрусовый', 'Кофе с цитрусовыми нотами', true],
                'floral' => ['Цветочный', 'Кофе с цветочным ароматом', true],
                'cacao' => ['Шоколад и какао', 'Кофе с шоколадным вкусом', true],
                'nutty' => ['Ореховый', 'Кофе с ореховым вкусом', true],
                'caramel' => ['Карамельный', 'Кофе с карамельным вкусом', true],
                'sweet' => ['Сладкий', 'Кофе со сладким вкусом', true],
                'spicy' => ['Пряный', 'Кофе с пряными нотами', true],
                'sour' => ['С кислинкой', 'Кофе с кислинкой', true],
                'bitter' => ['Терпкий', 'Кофе с терпким вкусом', true],
                'salt' => ['Солоноватый', 'Кофе с солоноватым привкусом', true],
            ],
        ],
        'roast' => [
            'name' => 'Обжарка',
            'values' => [
                'medium' => ['Средняя', null, false],
                'medium-dark' => ['Средне-тёмная', null, false],
            ],
        ],
    ];

    /**
     * Coffee collections: slug => [name, page heading].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const COFFEE_COLLECTIONS = [
        'unique' => ['Уникальные сорта', 'Уникальные сорта кофе'],
        'aromatic' => ['Ароматные сорта', 'Ароматный кофе'],
        'strong' => ['Крепкий кофе', 'Крепкий кофе в зёрнах'],
        'decaf' => ['Без кофеина', 'Кофе без кофеина'],
        'robusta' => ['Робуста и бленды', 'Робуста и бленды на её основе'],
        'drip-bags' => ['Дрип-пакеты', 'Кофе в дрип-пакетах'],
        'sets' => ['Наборы', 'Кофейные наборы'],
        'sale' => ['Распродажа', 'Распродажа кофе'],
    ];

    /**
     * Seed the catalog structure.
     */
    public function run(): void
    {
        $roots = [];
        $sort = 0;

        foreach (self::CATEGORIES as $slug => $data) {
            $roots[$slug] = $root = Category::updateOrCreate(
                ['parent_id' => null, 'slug' => $slug],
                [
                    'name' => $data['name'],
                    'h1' => $data['h1'],
                    'seo_title' => $data['seo_title'],
                    'seo_description' => $data['seo_description'],
                    'sort' => $sort++,
                ],
            );

            $childSort = 0;
            foreach ($data['children'] as $childSlug => $name) {
                Category::updateOrCreate(
                    ['parent_id' => $root->id, 'slug' => $childSlug],
                    ['name' => $name, 'sort' => $childSort++],
                );
            }
        }

        $groupSort = 0;
        foreach (self::COFFEE_FILTERS as $groupSlug => $group) {
            $filterGroup = FilterGroup::updateOrCreate(
                ['category_id' => $roots['coffee']->id, 'slug' => $groupSlug],
                ['name' => $group['name'], 'sort' => $groupSort++],
            );

            $valueSort = 0;
            foreach ($group['values'] as $valueSlug => $value) {
                FilterValue::updateOrCreate(
                    ['filter_group_id' => $filterGroup->id, 'slug' => $valueSlug],
                    [
                        'name' => $value[0],
                        'h1' => $value[1],
                        'is_landing' => $value[2],
                        'parent_id' => isset($value[3])
                            ? FilterValue::where('filter_group_id', $filterGroup->id)->where('slug', $value[3])->value('id')
                            : null,
                        'sort' => $valueSort++,
                    ],
                );
            }
        }

        $collectionSort = 0;
        foreach (self::COFFEE_COLLECTIONS as $slug => [$name, $h1]) {
            ProductCollection::updateOrCreate(
                ['category_id' => $roots['coffee']->id, 'slug' => $slug],
                ['name' => $name, 'h1' => $h1, 'sort' => $collectionSort++],
            );
        }

        $coffeeProject = Roaster::updateOrCreate(
            ['slug' => 'coffee-project'],
            ['name' => 'Coffee Project', 'city' => 'Екатеринбург', 'is_partner' => false, 'sort' => 0],
        );
        Roaster::updateOrCreate(
            ['slug' => 'ingresso'],
            ['name' => 'Ingresso', 'city' => 'Екатеринбург', 'is_partner' => true, 'sort' => 1],
        );

        Location::updateOrCreate(
            ['roaster_id' => $coffeeProject->id, 'retailcrm_store_code' => 'V36'],
            ['name' => 'Обжарочный цех Coffee Project', 'city' => 'Екатеринбург', 'roast_days' => ['mon', 'wed', 'fri']],
        );
    }
}
