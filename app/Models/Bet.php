<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bet extends Model
{
    protected $fillable = [
        'row',
       'col',
        'lat',
        'lon',
        'round_id',
        'created_at',
        'updated_at',
    ];
   public function round()
{
    return $this->belongsTo(Round::class);
}
}
