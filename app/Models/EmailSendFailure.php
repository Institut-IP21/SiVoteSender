<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $recipient
 * @property string|null $mailable
 * @property int|null $voter_id
 * @property int|null $sent_message_id
 * @property string|null $error
 * @property int $attempts
 * @property Carbon|null $alerted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder<static>|EmailSendFailure newModelQuery()
 * @method static Builder<static>|EmailSendFailure newQuery()
 * @method static Builder<static>|EmailSendFailure query()
 * @method static Builder<static>|EmailSendFailure pendingAlert()
 * @method static Builder<static>|EmailSendFailure whereAlertedAt($value)
 * @method static Builder<static>|EmailSendFailure whereId($value)
 * @mixin \Eloquent
 */
class EmailSendFailure extends Model
{
    protected $table = 'email_send_failures';

    /** @var array<string, string> */
    protected $casts = [
        'attempts'   => 'integer',
        'alerted_at' => 'datetime',
    ];

    /** @var list<string> */
    protected $fillable = [
        'recipient',
        'mailable',
        'voter_id',
        'sent_message_id',
        'error',
        'attempts',
        'alerted_at',
    ];

    /**
     * @param  Builder<EmailSendFailure>  $query
     * @return Builder<EmailSendFailure>
     */
    public function scopePendingAlert(Builder $query): Builder
    {
        return $query->whereNull('alerted_at');
    }
}
