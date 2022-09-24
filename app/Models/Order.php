<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use phpDocumentor\Reflection\Types\Boolean;

class Order extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'order_no',
        'weight',
        'ref',
        'logistic_status',
        'logistic',
        'name',
        'payment_status',
        'status',
        'total_amount',
        'tax',
        'commission',
        'shipping_charges',
        'promo',
        'discount',
        'grand_total',
        'shipping_method',
        'user_id',
        'items',
        'title', 'isScheduled', 'schedule_time','view_at'
    ];

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function transaction()
    {
        return $this->hasMany(Transaction::class);
    }
    public function orderhistories()
    {
        return $this->hasMany(OrderHistory::class)->with('store');
    }
    public function orderinfo()
    {
        return $this->hasOne(OrderInformation::class);
    }

    protected $casts = [
        'isScheduled'=> 'boolean'
    ];

}
