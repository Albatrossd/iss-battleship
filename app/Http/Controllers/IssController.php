<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IssController extends Controller
{
    public function now()
    {
        // Cache for a couple seconds so you don't hammer the public API
        $data = Cache::remember('iss_now', 3, function () {
            $res = Http::timeout(20)->get('https://api.wheretheiss.at/v1/satellites/25544');
            $res->throw();

            $json = $res->json();

            return [
                'lat' => $json['latitude'],
                'lon' => $json['longitude'],
                'timestamp' => $json['timestamp'] ?? null,
            ];
        });

        return response()->json($data);
    }
}
