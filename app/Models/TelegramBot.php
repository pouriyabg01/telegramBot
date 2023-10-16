<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramBot extends Model
{
    use HasFactory;
    protected $fillable = ['chat_id' , 'user_status'];

    public function userGroup()
    {
        return $this->belongsToMany(UserGroup::class);
    }
}
