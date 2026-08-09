<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\KebabStyle;
use Illuminate\Database\Seeder;

class KebabStyleSeeder extends Seeder
{
    /**
     * @var array<int, array{slug: string, name: string, group: string, description: string, is_filterable: bool}>
     */
    private const STYLES = [
        ['slug' => 'kebab', 'name' => 'Kebab', 'group' => 'style', 'description' => 'The foundation of civilisation.', 'is_filterable' => true],
        ['slug' => 'hsp', 'name' => 'HSP', 'group' => 'style', 'description' => 'Halal Snack Pack. Chips, meat, sauce, consequences.', 'is_filterable' => true],
        ['slug' => 'doner', 'name' => 'Doner', 'group' => 'style', 'description' => 'Vertical rotisserie, shaved to order.', 'is_filterable' => true],
        ['slug' => 'shawarma', 'name' => 'Shawarma', 'group' => 'style', 'description' => 'Levantine wrap, garlic forward.', 'is_filterable' => true],
        ['slug' => 'iskender', 'name' => 'Iskender', 'group' => 'style', 'description' => 'Served on bread, under tomato and butter.', 'is_filterable' => true],
        ['slug' => 'gozleme', 'name' => 'Gozleme', 'group' => 'style', 'description' => 'Flatbread, filled and griddled.', 'is_filterable' => false],
        ['slug' => 'plate', 'name' => 'Kebab Plate', 'group' => 'style', 'description' => 'For those who require cutlery.', 'is_filterable' => false],

        ['slug' => 'chicken', 'name' => 'Chicken', 'group' => 'protein', 'description' => 'The safe choice.', 'is_filterable' => true],
        ['slug' => 'lamb', 'name' => 'Lamb', 'group' => 'protein', 'description' => 'The correct choice.', 'is_filterable' => true],
        ['slug' => 'mixed', 'name' => 'Mixed', 'group' => 'protein', 'description' => 'The indecisive choice. Also the best choice.', 'is_filterable' => true],
        ['slug' => 'falafel', 'name' => 'Falafel', 'group' => 'protein', 'description' => 'Structurally ambitious.', 'is_filterable' => true],

        ['slug' => 'turkish', 'name' => 'Turkish', 'group' => 'cuisine', 'description' => 'Charcoal, ocakbasi, bread with opinions.', 'is_filterable' => true],
        ['slug' => 'lebanese', 'name' => 'Lebanese', 'group' => 'cuisine', 'description' => 'Garlic toum and pickled turnip.', 'is_filterable' => true],
        ['slug' => 'afghan', 'name' => 'Afghan', 'group' => 'cuisine', 'description' => 'Bread the size of a surfboard.', 'is_filterable' => false],
        ['slug' => 'greek', 'name' => 'Greek', 'group' => 'cuisine', 'description' => 'Souvlaki adjacent. The Society allows it.', 'is_filterable' => false],
    ];

    public function run(): void
    {
        foreach (self::STYLES as $index => $style) {
            KebabStyle::query()->updateOrCreate(
                ['slug' => $style['slug']],
                $style + ['sort_order' => $index * 10],
            );
        }
    }
}
