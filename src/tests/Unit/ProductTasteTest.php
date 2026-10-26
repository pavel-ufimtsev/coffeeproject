<?php

namespace Tests\Unit;

use App\Models\Product;
use Tests\TestCase;

class ProductTasteTest extends TestCase
{
    private function product(array $specs): Product
    {
        return new Product(['specs' => $specs]);
    }

    public function test_a_list_of_notes_becomes_pills()
    {
        $this->assertSame(['Нутелла', 'Фундук', 'Пралине'], $this->product(['tasting_notes' => 'нутелла, фундук и пралине.'])->tastingNotes());
        $this->assertSame(['Банан', 'Темный шоколад', 'Табак'], $this->product(['tasting_notes' => 'Букет: банан, темный шоколад, табак.'])->tastingNotes());
    }

    public function test_notes_written_as_a_sentence_stay_a_sentence()
    {
        $this->assertSame([], $this->product(['tasting_notes' => 'Во вкусе ноты жареного миндаля, какао'])->tastingNotes());
        $this->assertSame([], $this->product(['tasting_notes' => 'Насыщенный и терпкий кофе и никакой кислинки, нотами ореха'])->tastingNotes());
        $this->assertSame([], $this->product(['tasting_notes' => 'Микс 8 сортов из четырех стран.'])->tastingNotes());
    }

    public function test_the_roast_maps_to_five_steps()
    {
        $this->assertSame(3, $this->product(['roast' => 'Средняя обжарка'])->roastLevel());
        $this->assertSame(4, $this->product(['roast' => 'Средне-темная обжарка'])->roastLevel());
        $this->assertSame(1, $this->product(['roast' => 'Светлая'])->roastLevel());
        $this->assertNull($this->product(['roast' => ''])->roastLevel());
    }
}
