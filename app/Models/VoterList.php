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
use Illuminate\Support\Facades\DB;
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

    /**
     * Cheap pre-launch aggregates for this list (no voter models are loaded).
     *
     * - without_email: voters with a null/blank email
     * - blocked:       voters flagged email_blocked, or whose address is on the global block list
     * - duplicates:    surplus rows sharing a (case-insensitive) email, plus surplus
     *                  email-less rows sharing a normalised phone
     *
     * @return array{total: int, without_email: int, blocked: int, duplicates: int}
     */
    public function launchStats(): array
    {
        $row = DB::table('voters')
            ->join('voterlist_voter', 'voterlist_voter.voter_id', '=', 'voters.id')
            ->where('voterlist_voter.voterlist_id', $this->id)
            ->whereNull('voters.deleted_at')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN voters.email IS NULL OR TRIM(voters.email) = '' THEN 1 ELSE 0 END) AS without_email")
            ->selectRaw(
                'SUM(CASE WHEN voters.email_blocked = 1 OR EXISTS ('
                . 'SELECT 1 FROM global_email_block_lists g WHERE LOWER(g.email) = LOWER(TRIM(voters.email))'
                . ') THEN 1 ELSE 0 END) AS blocked'
            )
            ->selectRaw("COUNT(CASE WHEN voters.email IS NOT NULL AND TRIM(voters.email) <> '' THEN 1 END) AS with_email")
            ->selectRaw("COUNT(DISTINCT CASE WHEN voters.email IS NOT NULL AND TRIM(voters.email) <> '' THEN LOWER(TRIM(voters.email)) END) AS distinct_emails")
            ->first();

        $emailDuplicates = (int) ($row->with_email ?? 0) - (int) ($row->distinct_emails ?? 0);

        // Email-less voters are only identifiable by phone; these are rare, so normalise in PHP.
        $phones = DB::table('voters')
            ->join('voterlist_voter', 'voterlist_voter.voter_id', '=', 'voters.id')
            ->where('voterlist_voter.voterlist_id', $this->id)
            ->whereNull('voters.deleted_at')
            ->where(fn ($q) => $q->whereNull('voters.email')->orWhereRaw("TRIM(voters.email) = ''"))
            ->whereNotNull('voters.phone')
            ->pluck('voters.phone')
            ->map(fn ($phone) => Voter::normalizePhone((string) $phone))
            ->filter()
            ->all();
        $phoneDuplicates = count($phones) - count(array_unique($phones));

        return [
            'total'         => (int) ($row->total ?? 0),
            'without_email' => (int) ($row->without_email ?? 0),
            'blocked'       => (int) ($row->blocked ?? 0),
            'duplicates'    => $emailDuplicates + $phoneDuplicates,
        ];
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
