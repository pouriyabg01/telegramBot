<?php

namespace App\Console\Commands;

use App\Models\TelegramBot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Morilog\Jalali\Jalalian;
use SebastianBergmann\Diff\Exception;
use Telegram\Bot\Laravel\Facades\Telegram;
use function App\Http\Controllers\formatPercent;

class TelbotSendPrice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tel:send-price';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //get dollar price in toman
        try {
            $dollar = Http::get('http://api.navasan.tech/latest/?api_key=free6R94wfCN5z9drfTrJWGh4WpbaLEW&item=usd');
        }catch (Exception $e){
            return $e;
        }

        if ($dollar->successful()){
            $value = $dollar->json()['usd']['value'];
            if ($value){
                Cache::rememberForever('dollar' , function() use ($value){
                    return $value;
                });
                $dollar = $dollar->json()['usd']['value'];
            }else{
                $dollar = Cache::get('dollar');
            }
        }



        /**
         * get crypto price in dollar
         */
        try {
            $crypto = Http::get('https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest?CMC_PRO_API_KEY=aa500575-bf9e-4d79-ae88-8c8d6d0eaaa0');
            $crypto = $crypto->json();
        }catch (Exception $e){
            return $e;
        }

        function frmtPrcnt($number){
            return $number > 0 ? "\u{1F7E2} افزایش" : "\u{1F534} کاهش";
        }

        foreach (TelegramBot::all() as $chatID) {
            $message =  Jalalian::forge('today')->format('%A, %d %B %Y') ."\n";
            $counter = 0;
            foreach ($crypto['data'] as $items) {
                $message .= $items['name'] .
                    " \n به دلار \u{1F1FA}\u{1F1F8} : " .//USA flag emoji
                    number_format($items['quote']['USD']['price'] , 2) .//dollar price
                    " \n به تومان \u{1F1EE}\u{1F1F7} :" .//IRAN flag emoji
                    preg_replace('/(\d)(?=(\d{3})+$)/', '$1.', number_format($items['quote']['USD']['price'] * $dollar , 0))//toman price
                    .
                    "\n".
                    abs(number_format($items['quote']['USD']['percent_change_1h'] , 5)) . ' % ' .
                    frmtPrcnt($items['quote']['USD']['percent_change_1h'])
                    ." در یک ساعت گذشته ".
                    "\n_____________________\n";
                $counter++;
                if ($counter >= 10) {
                    break;
                }
            }
            Telegram::sendMessage([
                'chat_id' => $chatID['chat_id'],
                'text' => $message
            ]);
        }
    }
}
