<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sent message
 *
 * @Bind("sentMessage")
 */
class SentMessage extends Model
{
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
     * @var array
     */
    protected $casts = [
        'successful' => 'boolean'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'voter_id',
        'adrema_id',
        'batch_uuid',
        'verification_id',
        'successful',
        'status',
        'status_msg'
    ];

    /**
     * All of the relationships to be touched.
     *
     * @var array
     */
    protected $touches = ['voter', 'verification'];

    //

    public function getContactAttribute()
    {
        switch ($this->type) {
            case self::TYPE_EMAIL:
                return $this->voter->email;
                break;

            case self::TYPE_SMS:
                return $this->voter->phone;
                break;
        }
    }

    public function voter()
    {
        return $this->belongsTo(Voter::class);
    }

    public function adrema()
    {
        return $this->belongsTo(Adrema::class);
    }

    public function verification()
    {
        return $this->belongsTo(Verification::class);
    }

    //

    public function scopeBatch($query, $batch)
    {
        return $query->where('batch_uuid', $batch);
    }

    public function scopePhoneOnly($query)
    {
        return $query->where('type', self::TYPE_SMS);
    }

    public function scopeEmailOnly($query)
    {
        return $query->where('type', self::TYPE_SMS);
    }
}
