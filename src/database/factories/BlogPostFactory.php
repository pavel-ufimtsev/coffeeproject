<?php

namespace Database\Factories;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(4), '.');

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'excerpt' => fake()->sentence(12),
            'body' => "## Вступление\n\n".fake()->paragraph(8)."\n\n## Как это работает\n\n".fake()->paragraph(8),
            'status' => BlogPostStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 400)),
            'author_name' => 'Coffee Project',
            'source' => 'admin',
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => BlogPostStatus::Draft]);
    }

    public function archived(): static
    {
        return $this->state(['status' => BlogPostStatus::Archived]);
    }
}
