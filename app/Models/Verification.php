<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Verification
 */
class Verification extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'verifications';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'sent_at',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
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
     * @var array
     */
    protected $touches = ['voterList'];

    //

    public function voterList()
    {
        return $this->belongsTo(VoterList::class, 'voterlist_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(SentMessage::class);
    }
}
