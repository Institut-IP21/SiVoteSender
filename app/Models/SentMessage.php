<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Database\Factories\SentMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $type
 * @property int $voter_id
 * @property int $voterlist_id
 * @property string|null $batch_uuid
 * @property int|null $verification_id
 * @property bool $successful
 * @property string $status
 * @property string|null $status_msg
 * @property Carbon|null $failed_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Voter $voter
 * @property-read VoterList $voterList
 * @property-read Verification|null $verification
 * @property-read string|null $contact
 * @method static Builder<static>|SentMessage batch(?mixed $batch)
 * @method static Builder<static>|SentMessage emailOnly()
 * @method static SentMessageFactory factory($count = null, $state = [])
 * @method static Builder<static>|SentMessage newModelQuery()
 * @method static Builder<static>|SentMessage newQuery()
 * @method static Builder<static>|SentMessage onlyTrashed()
 * @method static Builder<static>|SentMessage phoneOnly()
 * @method static Builder<static>|SentMessage query()
 * @method static Builder<static>|SentMessage whereBatchUuid($value)
 * @method static Builder<static>|SentMessage whereCreatedAt($value)
 * @method static Builder<static>|SentMessage whereDeletedAt($value)
 * @method static Builder<static>|SentMessage whereId($value)
 * @method static Builder<static>|SentMessage whereStatus($value)
 * @method static Builder<static>|SentMessage whereStatusMsg($value)
 * @method static Builder<static>|SentMessage whereSuccessful($value)
 * @method static Builder<static>|SentMessage whereType($value)
 * @method static Builder<static>|SentMessage whereUpdatedAt($value)
 * @method static Builder<static>|SentMessage whereVerificationId($value)
 * @method static Builder<static>|SentMessage whereVoterId($value)
 * @method static Builder<static>|SentMessage whereVoterlistId($value)
 * @method static Builder<static>|SentMessage withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|SentMessage withoutTrashed()
 * @mixin \Eloquent
 */
class SentMessage extends Model
{
    /** @use HasFactory<SentMessageFactory> */
    use HasFactory;
    use SoftDeletes;

    const TYPE_SMS   = 'sms';
    const TYPE_EMAIL = 'email';

    const TYPES = [
        self::TYPE_SMS,
        self::TYPE_EMAIL
    ];

    const STATUS_SENT        = 'sent';
    const STATUS_DELIVERED   = 'delivered';
    const STATUS_BOUNCE_SOFT = 'soft-bounce';
    const STATUS_BOUNCE      = 'bounce';
    const STATUS_COMPLAINT   = 'complaint';
    const STATUS_BLOCKED     = 'blocked';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sent_messages';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'successful' => 'boolean',
        'failed_at'  => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'voter_id',
        'voterlist_id',
        'batch_uuid',
        'verification_id',
        'successful',
        'status',
        'status_msg',
        'failed_at'
    ];

    /**
     * All of the relationships to be touched.
     *
     * @var list<string>
     */
    protected $touches = ['voter', 'verification', 'voterList'];

    //

    public function getContactAttribute(): ?string
    {
        return match ($this->type) {
            self::TYPE_EMAIL => $this->voter->email,
            self::TYPE_SMS => $this->voter->phone,
            default => null,
        };
    }

    /** @return BelongsTo<Voter, $this> */
    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    /** @return BelongsTo<VoterList, $this> */
    public function voterList(): BelongsTo
    {
        return $this->belongsTo(VoterList::class, 'voterlist_id');
    }

    /** @return BelongsTo<Verification, $this> */
    public function verification(): BelongsTo
    {
        return $this->belongsTo(Verification::class);
    }

    //

    /**
     * @param Builder<SentMessage> $query
     * @param mixed $batch
     * @return Builder<SentMessage>
     */
    public function scopeBatch(Builder $query, mixed $batch): Builder
    {
        return $query->where('batch_uuid', $batch);
    }

    /**
     * @param Builder<SentMessage> $query
     * @return Builder<SentMessage>
     */
    public function scopePhoneOnly(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SMS);
    }

    /**
     * @param Builder<SentMessage> $query
     * @return Builder<SentMessage>
     */
    public function scopeEmailOnly(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_EMAIL);
    }
}
