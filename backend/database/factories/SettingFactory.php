<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LlmProvider;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gitlab_token'       => fake()->sha256(),
            'gitlab_username'    => fake()->userName(),
            'bitrix24_api_key'   => fake()->sha256(),
            'bitrix24_user_id'   => (string) fake()->numberBetween(1, 9999),
            'llm_provider'       => fake()->randomElement(LlmProvider::cases()),
            'llm_api_key'        => 'sk-' . fake()->regexify('[a-zA-Z0-9]{48}'),
            'llm_system_prompt'  => null,
            'developer_name'     => fake()->name(),
            'developer_position' => fake()->jobTitle(),
            'sync_schedule_time' => fake()->time('H:i'),
        ];
    }

    /**
     * Settings configured to use Claude as the LLM provider.
     */
    public function withClaude(): static
    {
        return $this->state(fn (array $attributes) => [
            'llm_provider' => LlmProvider::Claude,
        ]);
    }

    /**
     * Settings configured to use OpenAI as the LLM provider.
     */
    public function withOpenAI(): static
    {
        return $this->state(fn (array $attributes) => [
            'llm_provider' => LlmProvider::OpenAI,
        ]);
    }
}
