<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Uuids;

class Admin extends Authenticatable
{
    use HasFactory, Uuids;

    protected $connection = 'central';
    protected $table = 'admins';

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
