<?php

namespace Database\Factories;

use App\Models\Adrema;
use App\Models\SentMessage;
use App\Models\Verification;
use App\Models\Voter;
use Illuminate\Database\Eloquent\Factories\Factory;

class VerificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Verification::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            //'adrema_id',
            'template'     => 'Please verify by following this link: %%LINK%%',
            'sent_at'      => null,
            'redirect_url' => $this->faker->optional()->url
        ];
    }

    public function hasVoters($count)
    {
        return $this->state(function (array $attributes) use ($count) {
            return [
                'sent_at' => $this->faker->datetime,
            ];
        });
    }
}
