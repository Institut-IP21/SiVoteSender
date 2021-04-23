<?php

namespace Database\Factories;

use App\Models\VoterList;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoterListFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = VoterList::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title'      => $this->faker->name,
            'owner'      => $this->faker->uuid(),
        ];
    }
}
