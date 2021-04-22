<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\CascadeSoftDeletes;

/**
 * Personalization
 *
 * @Bind("personalization")
 */
class Personalization extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'personalizations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'owner',
        'photo_url'
    ];

    //

    public function scopeOwner($query, $id)
    {
        return $query->where('owner', $id);
    }
}
