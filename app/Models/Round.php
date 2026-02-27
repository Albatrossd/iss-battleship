<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Round extends Model
{
    protected $fillable = [
    'starts_at',
    'locks_at',
    'ends_at',
    'status',
];
    public function bets()
{
    return $this->hasMany(Bet::class);
}
}
