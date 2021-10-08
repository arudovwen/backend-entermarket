<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WishlistItem extends Model
{
    use HasFactory;

    protected $fillable = ['store_id', 'product_id', 'wishlist_id'];

    public function wishlist()
    {
        return  $this->belongsTo(Wishlist::class);
    }
    public function product()
    {
        return  $this->belongsTo(Product::class);
    }
    public function store()

    {
        return $this->belongsTo(Store::class);
    }
}
