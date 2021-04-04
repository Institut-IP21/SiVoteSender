<?php

namespace Database\Factories;

use App\Models\Adrema;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdremaFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Adrema::class;

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
