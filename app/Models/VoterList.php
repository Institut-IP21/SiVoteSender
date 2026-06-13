<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Database\Factories\VoterListFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\CascadeSoftDeletes;

/**
 * @property int $id
 * @property string $owner
 * @property string $title
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Voter> $voters
 * @property-read Collection<int, SentMessage> $sentMessages
 * @property-read Collection<int, Verification> $verifications
 * @property-read int|null $sent_messages_count
 * @property-read int|null $verifications_count
 * @property-read int|null $voters_count
 * @method static VoterListFactory factory($count = null, $state = [])
 * @method static Builder<static>|VoterList newModelQuery()
 * @method static Builder<static>|VoterList newQuery()
 * @method static Builder<static>|VoterList onlyTrashed()
 * @method static Builder<static>|VoterList owner(?mixed $id)
 * @method static Builder<static>|VoterList query()
 * @method static Builder<static>|VoterList whereCreatedAt($value)
 * @method static Builder<static>|VoterList whereDeletedAt($value)
 * @method static Builder<static>|VoterList whereId($value)
 * @method static Builder<static>|VoterList whereOwner($value)
 * @method static Builder<static>|VoterList whereTitle($value)
 * @method static Builder<static>|VoterList whereUpdatedAt($value)
 * @method static Builder<static>|VoterList withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|VoterList withoutTrashed()
 * @mixin \Eloquent
 */
class VoterList extends Model
{
    /** @use HasFactory<VoterListFactory> */
    use HasFactory;
    use SoftDeletes;
    use CascadeSoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'voterlists';

    /** @var list<string> */
    protected $cascadeDeletes = ['voters', 'sentMessages', 'verifications'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'owner',
        'title'
    ];

    /**
     * Checks if voterlist has any emails that are blocked
     */
    public function checkVoterListHasBlockedVoters(): bool
    {
        $emails = $this->voters->pluck('email');

        $result = GlobalEmailBlockList::whereIn('email', $emails)->get();

        return $result->isNotEmpty();
    }

    /** @return BelongsToMany<Voter, $this> */
    public function voters(): BelongsToMany
    {
        return $this->belongsToMany(Voter::class, 'voterlist_voter', 'voterlist_id', 'voter_id')->withTimestamps();
    }

    /** @return HasMany<SentMessage, $this> */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(SentMessage::class, 'voterlist_id');
    }

    /** @return HasMany<Verification, $this> */
    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class, 'voterlist_id');
    }

    //

    /**
     * @param Builder<VoterList> $query
     * @param mixed $id
     * @return Builder<VoterList>
     */
    public function scopeOwner(Builder $query, mixed $id): Builder
    {
        return $query->where('owner', $id);
    }
}
