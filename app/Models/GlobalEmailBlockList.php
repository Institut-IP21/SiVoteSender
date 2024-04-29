<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalEmailBlockList extends Model
{
    const STATUS_BOUNCE      = 'bounce';
    const STATUS_COMPLAINT   = 'complaint';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'global_email_block_lists';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email',
        'status',
        'status_msg'
    ];
}
