<?php

namespace App\Models;

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
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read VoterList $voterList
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SentMessage> $sentMessages
 */
class Verification extends Model
{
    /** @use HasFactory<\Database\Factories\VerificationFactory> */
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
