<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['id', 'username', 'name', 'email', 'role'];

    public $incrementing = false;

    protected $casts = [
        'id' => 'integer',
    ];
}
