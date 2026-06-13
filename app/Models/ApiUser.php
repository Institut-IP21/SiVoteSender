<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $owner
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Personalization|null $personalization
 */
class ApiUser extends Authenticatable
{

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'owner',
    ];

    /** @return HasOne<Personalization, $this> */
    public function personalization(): HasOne
    {
        return $this->hasOne(Personalization::class, 'owner', 'owner');
    }
}
