<?php

namespace Tests\Concerns;

use App\Services\Catalog\CatalogImporter;
use Illuminate\Support\Facades\Storage;

/**
 * Imports a small catalog through the real importer: two active coffees
 * (one tagged Kenya, one in the sale collection), one archived tea in a child
 * category, and a few legacy redirects.
 */
trait ImportsCatalogFixture
{
    protected string $fixturePath;

    protected function importCatalogFixture(): void
    {
        Storage::fake('public');

        $this->fixturePath = sys_get_temp_dir().'/catalog-fixture-'.uniqid();
        mkdir("{$this->fixturePath}/images", recursive: true);

        $image = imagecreatetruecolor(40, 40);
        imagejpeg($image, "{$this->fixturePath}/images/kenya-wolf.jpg");
        imagedestroy($image);

        file_put_contents("{$this->fixturePath}/products.json", json_encode(['products' => $this->fixtureProducts()]));
        file_put_contents("{$this->fixturePath}/redirects.json", json_encode([
            '/moskva/coffee' => '/coffee',
            '/moskva/coffee/kenya' => '/coffee/kenya',
            '/coffee/old-lot' => '/coffee',
        ]));

        app(CatalogImporter::class)->import($this->fixturePath);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fixtureProducts(): array
    {
        $coffee = fn (array $overrides) => array_replace([
            'root' => 'coffee', 'category' => 'coffee', 'type' => 'coffee', 'status' => 'active', 'tagline' => 'Яркий кофе',
            'summary' => 'Описание сорта', 'description' => '<p>Заметки обжарщика</p>', 'specs' => ['country' => 'Кения', 'roast' => 'Средняя обжарка'],
            'roaster' => 'coffee-project', 'featured' => false, 'collections' => [], 'images' => [],
        ], $overrides);

        return [
            $coffee([
                'slug' => 'kenya-wolf', 'name' => 'Кения Волк', 'sort' => 1, 'featured' => true, 'retailcrm_product_id' => 142,
                'filters' => ['brew' => ['pourover', 'french'], 'origin' => ['kenya'], 'taste' => ['berry']],
                'collections' => ['unique'],
                'variants' => [
                    ['label' => '250 г', 'weight_g' => 250, 'retailcrm_offer_id' => 1001, 'price' => 1177, 'old_price' => null],
                    ['label' => '1 кг', 'weight_g' => 1000, 'retailcrm_offer_id' => 1002, 'price' => 4156, 'old_price' => null],
                ],
                'images' => [['file' => 'kenya-wolf.jpg', 'source' => 'archive']],
            ]),
            $coffee([
                'slug' => 'brazil-fine-cup', 'name' => 'Бразилия Файн Кап', 'sort' => 2, 'retailcrm_product_id' => 66,
                'specs' => ['country' => 'Бразилия'],
                'filters' => ['brew' => ['espresso'], 'origin' => ['brazil'], 'taste' => ['cacao']],
                'collections' => ['sale'],
                'variants' => [
                    ['label' => '250 г', 'weight_g' => 250, 'retailcrm_offer_id' => 2001, 'price' => 690, 'old_price' => 812],
                ],
            ]),
            [
                'slug' => 'puer-shu', 'root' => 'chaj', 'category' => 'pujer', 'type' => 'tea', 'status' => 'archived', 'name' => 'Шу Пуэр',
                'tagline' => 'Пуэр', 'summary' => '', 'description' => '', 'specs' => [], 'roaster' => null, 'featured' => false, 'sort' => 1000,
                'filters' => [], 'collections' => [], 'retailcrm_product_id' => 148, 'images' => [],
                'variants' => [['label' => '100 г', 'weight_g' => 100, 'retailcrm_offer_id' => 3001, 'price' => 780, 'old_price' => null]],
            ],
        ];
    }
}
