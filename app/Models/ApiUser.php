<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $owner
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Personalization|null $personalization
 * @method static Builder<static>|ApiUser newModelQuery()
 * @method static Builder<static>|ApiUser newQuery()
 * @method static Builder<static>|ApiUser query()
 * @mixin \Eloquent
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
