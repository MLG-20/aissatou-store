<?php

namespace App\Models\Order;

use App\Models\Admin;
use App\Models\Delivery\Courier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'from_status', 'to_status', 'author_id', 'courier_id'])]
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

    /** Livreur qui a validé depuis sa page (« je pars », « livrée »). */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function authorLabel(): string
    {
        return match (true) {
            $this->courier_id !== null => ($this->courier?->name ?? 'Livreur supprimé').' (livreur)',
            $this->author_id !== null => $this->author?->name ?? 'Admin supprimé',
            default => 'système',
        };
    }
}
