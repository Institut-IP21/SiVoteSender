<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $email
 * @property string $status
 * @property string|null $status_msg
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'status',
        'status_msg'
    ];
}
