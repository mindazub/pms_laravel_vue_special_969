<?php

namespace Database\Factories;

use App\Models\Scrapboard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Scrapboard>
 */
class ScrapboardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title($this->faker->unique()->words(2, true));

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(100, 999),
            'workbook_data' => [
                'activeSheetId' => 'sheet-1',
                'sheets' => [
                    [
                        'id' => 'sheet-1',
                        'name' => 'Sheet 1',
                        'rows' => 12,
                        'columns' => 6,
                        'cells' => [],
                    ],
                    [
                        'id' => 'sheet-2',
                        'name' => 'Sheet 2',
                        'rows' => 12,
                        'columns' => 6,
                        'cells' => [],
                    ],
                ],
            ],
        ];
    }
}
