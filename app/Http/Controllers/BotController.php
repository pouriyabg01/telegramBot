<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BotController extends Controller
{

    public function __construct()
    {

    }

    public function getBotInfo()
    {
        $result = Http::get("https://api.telegram.org/bot6409450477:AAFfaMbEqLvbQ81VNyOwzSdqmXqOFignt-c/getMe");

        return $result;
    }

    public function sendMessage(Request $request)
    {
        $result = Http::get("https://api.telegram.org/bot6409450477:AAFfaMbEqLvbQ81VNyOwzSdqmXqOFignt-c/sendMessage?chat_id=540263538&text=" . $request->message);

        if ($result){
            return response()->json(['status' => 'successfully']);
        }

    }


}
