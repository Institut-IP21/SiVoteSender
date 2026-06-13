<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\CascadeSoftDeletes;

/**
 * @property int $id
 * @property string|null $title
 * @property string|null $email
 * @property \Illuminate\Support\Carbon|null $email_verified
 * @property bool $email_blocked
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon|null $phone_verified
 * @property bool $phone_blocked
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, VoterList> $voterLists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SentMessage> $sentMessages
 */
class Voter extends Model
{
    /** @use HasFactory<\Database\Factories\VoterFactory> */
    use HasFactory;
    use SoftDeletes, CascadeSoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'voters';

    /** @var list<string> */
    protected $cascadeDeletes = ['sentMessages'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified' => 'datetime',
        'phone_verified' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'email',
        'email_verified',
        'phone',
        'phone_verified',
        'email_blocked',
        'phone_blocked'
    ];

    /**
     * All of the relationships to be touched.
     *
     * @var list<string>
     */
    protected $touches = ['voterLists'];

    /** @return BelongsToMany<VoterList, $this> */
    public function voterLists(): BelongsToMany
    {
        return $this->belongsToMany(VoterList::class, 'voterlist_voter', 'voter_id', 'voterlist_id')->withTimestamps();
    }

    /** @return HasMany<SentMessage, $this> */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(SentMessage::class);
    }

    //

    /**
     * @param Builder<Voter> $query
     * @return Builder<Voter>
     */
    public function scopeVerifiedEmail(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified');
    }

    /**
     * @param Builder<Voter> $query
     * @return Builder<Voter>
     */
    public function scopeVerifiedPhone(Builder $query): Builder
    {
        return $query->whereNotNull('phone_verified');
    }
}
