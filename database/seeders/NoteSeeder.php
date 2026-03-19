<?php

namespace Database\Seeders;

use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class NoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $projects = Project::factory(8)->create([
            'user_id' => $user->id,
        ]);

        foreach ($projects as $project) {
            Note::factory(random_int(8, 20))->create([
                'user_id' => $user->id,
                'project_id' => $project->id,
            ]);
        }
    }
}
