<?php

namespace Database\Factories;

use App\Models\VoterList;
use App\Models\SentMessage;
use App\Models\Voter;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoterFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Voter::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => $this->faker->name,
            'email' => $this->faker->email,
            'email_verified' => null,
            'phone' => null,
            // 'phone_verified' => null,
        ];
    }

    public function verifiedEmail()
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified' => $this->faker->datetime,
            ];
        });
    }

    public function hasPhone()
    {
        return $this->state(function (array $attributes) {
            return [
                'phone' => $this->faker->e164PhoneNumber,
            ];
        });
    }
}
