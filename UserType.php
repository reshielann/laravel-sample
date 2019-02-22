<?php

namespace App\Models\Components;

use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    protected $table = 'user_type';
    protected $guarded = ['id'];

    public function users()
    {
        return $this->hasMany(Users::class, 'user_type');
    }
}
