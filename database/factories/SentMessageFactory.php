<?php

namespace Database\Factories;

use App\Models\SentMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class SentMessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SentMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'type'       => SentMessage::TYPE_EMAIL,
            'successful' => true,
            'status'     => "",
            // The following have to be set when creating!
            // 'voter_id'        => null,
            // 'voterlist_id'       => null,
            // 'batch_uuid'      => null,
            // 'verification_id' => null,
        ];
    }

    public function notSuccessful()
    {
        return $this->state(function (array $attributes) {
            return [
                'successful' => false,
                'status'     => "Could not deliver message.",
            ];
        });
    }

    public function typePhone()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => SentMessage::TYPE_SMS,
            ];
        });
    }
}
