<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Database\Factories\VoterFactory;
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
 * @property Carbon|null $email_verified
 * @property bool $email_blocked
 * @property string|null $phone
 * @property Carbon|null $phone_verified
 * @property bool $phone_blocked
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, VoterList> $voterLists
 * @property-read Collection<int, SentMessage> $sentMessages
 * @property-read int|null $sent_messages_count
 * @property-read int|null $voter_lists_count
 * @method static VoterFactory factory($count = null, $state = [])
 * @method static Builder<static>|Voter newModelQuery()
 * @method static Builder<static>|Voter newQuery()
 * @method static Builder<static>|Voter onlyTrashed()
 * @method static Builder<static>|Voter query()
 * @method static Builder<static>|Voter verifiedEmail()
 * @method static Builder<static>|Voter verifiedPhone()
 * @method static Builder<static>|Voter whereCreatedAt($value)
 * @method static Builder<static>|Voter whereDeletedAt($value)
 * @method static Builder<static>|Voter whereEmail($value)
 * @method static Builder<static>|Voter whereEmailBlocked($value)
 * @method static Builder<static>|Voter whereEmailVerified($value)
 * @method static Builder<static>|Voter whereId($value)
 * @method static Builder<static>|Voter wherePhone($value)
 * @method static Builder<static>|Voter wherePhoneBlocked($value)
 * @method static Builder<static>|Voter wherePhoneVerified($value)
 * @method static Builder<static>|Voter whereTitle($value)
 * @method static Builder<static>|Voter whereUpdatedAt($value)
 * @method static Builder<static>|Voter withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Voter withoutTrashed()
 * @mixin \Eloquent
 */
class Voter extends Model
{
    /** @use HasFactory<VoterFactory> */
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
