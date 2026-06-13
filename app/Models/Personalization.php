<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $owner
 * @property string|null $photo_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder<static>|Personalization newModelQuery()
 * @method static Builder<static>|Personalization newQuery()
 * @method static Builder<static>|Personalization owner(?mixed $id)
 * @method static Builder<static>|Personalization query()
 * @method static Builder<static>|Personalization whereCreatedAt($value)
 * @method static Builder<static>|Personalization whereId($value)
 * @method static Builder<static>|Personalization whereOwner($value)
 * @method static Builder<static>|Personalization wherePhotoUrl($value)
 * @method static Builder<static>|Personalization whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Personalization extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'personalizations';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'owner',
        'photo_url'
    ];

    //

    /**
     * @param Builder<Personalization> $query
     * @param mixed $id
     * @return Builder<Personalization>
     */
    public function scopeOwner(Builder $query, mixed $id): Builder
    {
        return $query->where('owner', $id);
    }
}
