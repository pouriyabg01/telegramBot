<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService{
    private $token;
    public function __construct()
    {
        $this->token = "6409450477:AAFwX2pMawQypCZtQiSoXKwQiT4TtCc2Caw";
    }

    public function execute($method , $param=[])
    {
        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;
        $request = Http::post($url,$param);
        return $request->json('result' , []);
    }

    public function getUpdate($offset)
    {
        $response = $this->execute('getUpdates' ,[
            'offset' => $offset
        ]);
        return $response;
    }

    public function sendMessage($chatID , $text){
        $response = $this->execute('sendMessage' , [
            'chat_id' => $chatID,
            'text' => $text
        ]);
        return $response;
    }
}
