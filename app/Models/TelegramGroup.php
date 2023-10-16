<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramGroup extends Model
{
    use HasFactory;
    protected $fillable = ['group_id' , 'user_adder'];

    public function userGroup()
    {
        return $this->belongsToMany(UserGroup::class);
    }
}
