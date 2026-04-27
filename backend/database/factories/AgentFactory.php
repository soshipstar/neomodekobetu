<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'code' => 'ag_'.uniqid(),
            'contact_name' => $this->faker->name(),
            'contact_email' => $this->faker->safeEmail(),
            'contact_phone' => null,
            'address' => null,
            'default_commission_rate' => 0.20,
            'bank_info' => null,
            'contract_document_path' => null,
            'contract_terms' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }
}
