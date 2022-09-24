<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Store extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $fillable = [
        'name',
        'email',
        'password',
        'location',
        'image',
        'connection',
        'api_endpoint',
        'db_host',
        'db_username',
        'db_password',
        'email',
        'password',
        'lat',
        'long',
        'place',
        'status',
        'lga_id'
    ];
    public function lga()
    {
        return $this->belongsTo(Lga::class);
    }

    public function products()

    {
        return $this->hasMany(Product::class);
    }
   
    public function categories()
    {
        return $this->hasMany(Category::class);
    }
    public function brands()
    {
        return $this->hasMany(Brand::class);
    }

    public function storeorders()
    {
        return $this->hasMany(StoreOrder::class)->where('payment_status', 'paid');
    }

    protected $hidden = [
        'updated_at',
        "connection",
        "api_endpoint",
        "db_host",
        "db_username",
        "db_password",
        'password'
    ];
    protected $attributes = [
        "connection" => 'null',
        "api_endpoint" => 'null',
        "db_host" => 'null',
        "db_username" => 'null',
        "db_password" => 'null',
    ];
}
