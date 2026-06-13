<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $email
 * @property string $status
 * @property string|null $status_msg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder<static>|GlobalEmailBlockList newModelQuery()
 * @method static Builder<static>|GlobalEmailBlockList newQuery()
 * @method static Builder<static>|GlobalEmailBlockList query()
 * @method static Builder<static>|GlobalEmailBlockList whereCreatedAt($value)
 * @method static Builder<static>|GlobalEmailBlockList whereEmail($value)
 * @method static Builder<static>|GlobalEmailBlockList whereStatus($value)
 * @method static Builder<static>|GlobalEmailBlockList whereStatusMsg($value)
 * @method static Builder<static>|GlobalEmailBlockList whereUpdatedAt($value)
 * @mixin \Eloquent
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
