<?php

namespace Database\Factories;

use App\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Note>
 */
class NoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(Note::STATUSES);
        $progress = match ($status) {
            Note::STATUS_DONE => 100,
            Note::STATUS_IN_PROGRESS => $this->faker->randomElement([0, 25, 50, 75]),
            default => 0,
        };

        return [
            'title' => $this->faker->sentence,
            'content' => $this->faker->paragraph,
            'status' => $status,
            'progress' => $progress,
        ];
    }
}
