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
        'db_password'
    ];

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

    protected $hidden = [
        'created_at',
        'updated_at',
        "connection",
        "api_endpoint",
        "db_host",
        "db_username",
        "db_password",
     
        'password'
    ];
}
