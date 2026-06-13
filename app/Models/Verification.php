<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Database\Factories\VerificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $voterlist_id
 * @property string $template
 * @property string|null $subject
 * @property string|null $redirect_url
 * @property Carbon|null $sent_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read VoterList $voterList
 * @property-read Collection<int, SentMessage> $sentMessages
 * @property-read int|null $sent_messages_count
 * @method static VerificationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Verification newModelQuery()
 * @method static Builder<static>|Verification newQuery()
 * @method static Builder<static>|Verification onlyTrashed()
 * @method static Builder<static>|Verification query()
 * @method static Builder<static>|Verification whereCreatedAt($value)
 * @method static Builder<static>|Verification whereDeletedAt($value)
 * @method static Builder<static>|Verification whereId($value)
 * @method static Builder<static>|Verification whereRedirectUrl($value)
 * @method static Builder<static>|Verification whereSentAt($value)
 * @method static Builder<static>|Verification whereSubject($value)
 * @method static Builder<static>|Verification whereTemplate($value)
 * @method static Builder<static>|Verification whereUpdatedAt($value)
 * @method static Builder<static>|Verification whereVoterlistId($value)
 * @method static Builder<static>|Verification withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Verification withoutTrashed()
 * @mixin \Eloquent
 */
class Verification extends Model
{
    /** @use HasFactory<VerificationFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'verifications';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'voterlist_id',
        'template',
        'subject',
        'redirect_url'
        //'sent_at',
    ];

    /**
     * All of the relationships to be touched.
     *
     * @var list<string>
     */
    protected $touches = ['voterList'];

    //

    /** @return BelongsTo<VoterList, $this> */
    public function voterList(): BelongsTo
    {
        return $this->belongsTo(VoterList::class, 'voterlist_id');
    }

    /** @return HasMany<SentMessage, $this> */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(SentMessage::class);
    }
}
