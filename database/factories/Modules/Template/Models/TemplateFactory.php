<?php

namespace Database\Factories\Modules\Template\Models;

use App\Modules\Template\Models\Template;
use App\Shared\Enums\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Template factory for testing
 */
class TemplateFactory extends Factory
{
    protected $model = Template::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);
        
        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'channel' => fake()->randomElement(Channel::cases()),
            'content' => 'Hello {{name}}, ' . fake()->sentence(),
            'subject' => fake()->words(4, true),
            'description' => fake()->sentence(),
            'is_active' => true,
            'variables' => ['name'],
        ];
    }

    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => Channel::EMAIL,
        ]);
    }

    public function sms(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => Channel::SMS,
            'subject' => null,
        ]);
    }

    public function push(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => Channel::PUSH,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withVariables(array $variables): static
    {
        $content = 'Test message';
        foreach ($variables as $var) {
            $content .= ' {{' . $var . '}}';
        }
        
        return $this->state(fn (array $attributes) => [
            'content' => $content,
            'variables' => $variables,
        ]);
    }
}
