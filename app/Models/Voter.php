<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\CascadeSoftDeletes;

/**
 * Voter
 */
class Voter extends Model
{
    use HasFactory;
    use SoftDeletes, CascadeSoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'voters';

    protected $cascadeDeletes = ['sentMessages'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'email_verified',
        'phone_verified',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'email',
        'email_verified',
        'phone',
        'phone_verified',
        'email_blocked',
        'phone_blocked'
    ];

    /**
     * All of the relationships to be touched.
     *
     * @var array
     */
    protected $touches = ['voterLists'];

    public function voterLists()
    {
        return $this->belongsToMany(VoterList::class, 'voterlist_voter', 'voter_id', 'voterlist_id')->withTimestamps();
    }

    public function sentMessages()
    {
        return $this->hasMany(SentMessage::class);
    }

    //

    public function scopeVerifiedEmail($query)
    {
        return $query->whereNotNull('email_verified');
    }

    public function scopeVerifiedPhone($query)
    {
        return $query->whereNotNull('phone_verified');
    }
}
