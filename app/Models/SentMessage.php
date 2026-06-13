<?php

namespace App\Models;

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
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Voter $voter
 * @property-read VoterList $voterList
 * @property-read Verification|null $verification
 * @property-read string|null $contact
 */
class SentMessage extends Model
{
    /** @use HasFactory<\Database\Factories\SentMessageFactory> */
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
        'successful' => 'boolean'
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
        'status_msg'
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
        switch ($this->type) {
            case self::TYPE_EMAIL:
                return $this->voter->email;

            case self::TYPE_SMS:
                return $this->voter->phone;
        }

        return null;
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
