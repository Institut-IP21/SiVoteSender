<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\CascadeSoftDeletes;

/**
 * Adrema
 *
 * @Bind("adrema")
 */
class Adrema extends Model
{
    use HasFactory;
    use SoftDeletes;
    use CascadeSoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'adremas';

    protected $cascadeDeletes = ['voters', 'sentMessages', 'verifications'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'owner',
        'title'
    ];

    /**
     * Checks if adrema has any emails that are blocked
     *
     * @return boolean
     */
    public function checkAdremaHasBlockedVoters(): bool
    {
        $emails = $this->voters->pluck('email');

        $result = GlobalEmailBlockList::whereIn('email', $emails)->get();

        return $result->isNotEmpty();
    }

    public function voters()
    {
        return $this->belongsToMany(Voter::class)->withTimestamps();
    }

    public function sentMessages()
    {
        return $this->hasMany(SentMessage::class);
    }

    public function verifications()
    {
        return $this->hasMany(Verification::class);
    }

    //

    public function scopeOwner($query, $id)
    {
        return $query->where('owner', $id);
    }
}
