<?php

namespace App\Models\Order;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'from_status', 'to_status', 'author_id'])]
class OrderStatusHistory extends Model
{
    protected $table = 'order_status_history';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'author_id');
    }
}
