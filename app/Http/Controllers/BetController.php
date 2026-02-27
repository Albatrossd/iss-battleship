<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Round;
use App\Models\Bet;

class BetController extends Controller
{
    public function store(Request $request, Round $round)
    {
    $validated = $request->validate([
        'row' => 'required|integer|min:0|max:17',
        'col' => 'required|integer|min:0|max:35',
    ]);

    $bet = $round->bets()->create($validated);

    return response()->json($bet);
}
}
