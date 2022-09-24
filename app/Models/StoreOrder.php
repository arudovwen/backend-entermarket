<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreOrder extends Model
{
    use HasFactory;

    protected $fillable = ['quantity','price','subtotal','user_id','name', 'store_id', 'product_id','order_no','payment_status', 'status', 'order_id'];
    public function store(){
        return $this->belongsTo(Store::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function product(){
        return $this->belongsTo(Product::class);
    }
    public function orderinfo()
    {
        return $this->belongsTo(OrderInformation::class,'order_id', 'order_id');
    }
    public function myorder()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
    public function orderhistories()
    {
        return $this->hasMany(OrderHistory::class, 'order_id', 'order_id');
    }
}
