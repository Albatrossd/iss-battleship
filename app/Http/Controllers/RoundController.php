<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Round;

class RoundController extends Controller
{
public function current()
{
    $round = Round::first();

    if (!$round) {
        $round = Round::create([
            'starts_at' => now(),
            'locks_at' => now()->addMinutes(4),
            'ends_at' => now()->addMinutes(5),
        ]);
    }

    return response()->json($round);
}
public function board(Round $round)
{
    return response()->json([
        'round' => $round,
        'bets' => $round->bets()->get(['id','row','col','created_at']),
    ]);
}
}
