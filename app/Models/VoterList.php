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
 * @property string $owner
 * @property string $title
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Voter> $voters
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SentMessage> $sentMessages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Verification> $verifications
 */
class VoterList extends Model
{
    /** @use HasFactory<\Database\Factories\VoterListFactory> */
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
