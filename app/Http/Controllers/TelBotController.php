<?php

namespace App\Http\Controllers;

use AmrShawky\LaravelCurrency\Facade\Currency;
use App\Models\TelegramBot;
use App\Models\TelegramGroup;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Morilog\Jalali\Jalalian;
use SebastianBergmann\Diff\Exception;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Keyboard\Keyboard;
class TelBotController extends Controller
{


    public function index()
    {
        $telegram = Telegram::getWebhookUpdate();

        /**
         * save group ID in DB
         */
        if ($telegram->myChatMember) {

            $groupId = $telegram->myChatMember->chat->id;
            if (!TelegramGroup::where('group_id', $groupId)->first()) {
                TelegramGroup::create(['group_id' => $groupId , 'user_adder' => $telegram->myChatMember->from->id]);
//                UserGroup::create(['chat_id' => $telegram->myChatMember->from->id , 'group_id' => $groupId]);
            }
        }

        /**
         * delete group ID from DB
         */
        $update = Telegram::commandsHandler(true);
        if ($update->message and $update->message->leftChatMember) {
            $leftChatMember = $update->message->leftChatMember;
            $botId = env('TELEGRAM_BOT_ID');
            if ($leftChatMember->id == $botId) {
                TelegramGroup::where('group_id', $telegram->message->chat->id)->delete();
            }
        }


        /**
         * game rps
         */
        if ($telegram->message and $telegram->message->text == '/game') {

            Cache::flush();
            $id = $telegram->message->from->id;

            $userGame = TelegramBot::firstOrCreate([
                'chat_id' => $id
            ]);

            if (!is_null($userGame->play))
                $userGame->play = null;

            $userGame->user_status = 'waiting-for-player';
            $userGame->save();
            if ($userGame) {
                Telegram::sendMessage([
                    'chat_id' => $id,
                    'text' => 'شما در صف بازی هستید'
                ]);
            }
            if ($readyPlayer = TelegramBot::where('user_status', 'waiting-for-player')->whereNotIn('chat_id', [$id])->first()) {
                TelegramBot::where('chat_id', $id)->update(['play' => $readyPlayer->chat_id , 'user_status' => 'matched']);
                TelegramBot::where('chat_id', $readyPlayer->chat_id)->update(['play' => $id , 'user_status' => 'matched']);

                $rps = Keyboard::make()->inline()->row(
                    [Keyboard::inlineButton(['text' => 'سنگ', 'callback_data' => 'rock']),],
                )->row(
                    [Keyboard::inlineButton(['text' => 'کاغذ', 'callback_data' => 'paper']),],
                )->row(
                    [Keyboard::inlineButton(['text' => 'قیچی', 'callback_data' => 'scissors']),],
                );
                Telegram::sendMessage([
                    'chat_id' => $userGame->chat_id,
                    'text' => 'انتخاب کنید',
                    'reply_markup' => $rps
                ]);
                Telegram::sendMessage([
                    'chat_id' => $readyPlayer->chat_id,
                    'text' => 'انتخاب کنید',
                    'reply_markup' => $rps
                ]);
            }
        }elseif($telegram->callbackQuery AND in_array($telegram->callbackQuery->data,['rock','paper','scissors'])) {
            TelegramBot::where('chat_id', $telegram->callbackQuery->from->id)->update(['user_status' => $telegram->callbackQuery->data]);

            Telegram::deleteMessage([
                'chat_id' => $telegram->callbackQuery->from->id,
                'message_id' => $telegram->callbackQuery->message->messageId
            ]);
            if (    $userOne = TelegramBot::where('chat_id', $telegram->callbackQuery->from->id)->whereIn('user_status', ['rock', 'paper', 'scissors'])->first()
                and $userTwo = TelegramBot::where('chat_id', $userOne->play)->whereIn('user_status', ['rock', 'paper', 'scissors'])->first()) {

                $userOneChoose = $userOne->user_status;
                $userTwoChoose = $userTwo->user_status;

                $rps = Keyboard::make()->inline()->row(
                    [Keyboard::inlineButton(['text' => 'سنگ', 'callback_data' => 'rock']),],
                )->row(
                    [Keyboard::inlineButton(['text' => 'کاغذ', 'callback_data' => 'paper']),],
                )->row(
                    [Keyboard::inlineButton(['text' => 'قیچی', 'callback_data' => 'scissors']),],
                );

                if ($userOneChoose == $userTwoChoose) {
                    Telegram::sendMessage([
                        'chat_id' => $userTwo->chat_id,
                        'text' => "you :  {$userTwoChoose}\n player 2 : {$userOneChoose} \n draw \n pick again",
                        'reply_markup' => $rps
                    ]);
                    Telegram::sendMessage([
                        'chat_id' => $userOne->chat_id,
                        'text' => "you :  {$userOneChoose}\n player 2 : {$userTwoChoose} \n draw \n pick again",
                        'reply_markup' => $rps
                    ]);
                    TelegramBot::whereIn('chat_id' , [$userOne->chat_id , $userTwo->chat_id])->update(['user_status' => 'matched']);
                }elseif ($userOneChoose == 'rock' and $userTwoChoose == 'paper'
                    or $userOneChoose == 'paper' and $userTwoChoose == 'scissors'
                    or $userOneChoose == 'scissors' and $userTwoChoose == 'rock') {
                    Telegram::sendMessage([
                        'chat_id' => $userTwo->chat_id,
                        'text' => "you :  {$userTwoChoose}\n player 2 : {$userOneChoose} \n you won"
                    ]);
                    Telegram::sendMessage([
                        'chat_id' => $userOne->chat_id,
                        'text' => "you :  {$userOneChoose}\n player 2 : {$userTwoChoose} \n you lost"
                    ]);
                    TelegramBot::whereIn('chat_id' , [$userOne->chat_id , $userTwo->chat_id])->update(['play' => null , 'user_status' => null]);
                } else {
                    Telegram::sendMessage([
                        'chat_id' => $userTwo->chat_id,
                        'text' => "you :  {$userTwoChoose}\n player 2 : {$userOneChoose} \n you lost"
                    ]);
                    Telegram::sendMessage([
                        'chat_id' => $userOne->chat_id,
                        'text' => "you :  {$userOneChoose}\n player 2 : {$userTwoChoose} \n you won"
                    ]);
                    TelegramBot::whereIn('chat_id' , [$userOne->chat_id , $userTwo->chat_id])->update(['play' => null , 'user_status' => null]);
                }
            }
        }

        /**
         *
         * END game
         */


        /**
         * game rps with bot
         */
//        if ($telegram->message and $telegram->message->text == '/game') {
//            Cache::flush();
//            Cache::put('user_status' , 'rps_game');
//            $rps = Keyboard::make()->inline()->row(
//                [Keyboard::inlineButton(['text' => 'rock', 'callback_data' => 'rock']),],
//            )->row(
//                [Keyboard::inlineButton(['text' => 'paper', 'callback_data' => 'paper']),],
//            )->row(
//                [Keyboard::inlineButton(['text' => 'scissors', 'callback_data' => 'scissors']),],
//            );
//            Cache::put('rpsKeyboard' , $rps);
//            $rpsBotChoose = ['rock' , 'paper', 'scissors'];
//            Cache::put('rps' , $rpsBotChoose[array_rand($rpsBotChoose)]);
//            Telegram::sendMessage([
//                'chat_id' => $telegram->message->from->id,
//                'text' => 'choose',
//                'reply_markup' => $rps
//            ]);
//        }elseif ($telegram->callbackQuery AND Cache::get('user_status') == 'rps_game') {
//
//            $callback = $telegram->callbackQuery;
//            $data = $callback->data;
//            $id = $callback->from->id;
//
//            if ($data == Cache::get('rps')) {
//                $rpsBotChoose = ['rock', 'paper', 'scissors'];
//                Cache::put('rps', $rpsBotChoose[array_rand($rpsBotChoose)]);
//                Telegram::deleteMessage([
//                    'chat_id' => $id,
//                    'message_id' => $callback->message->messageId
//                ]);
//                Telegram::sendMessage([
//                    'chat_id' => $id,
//                    'text' => "draw \n choose again",
//                    'reply_markup' => Cache::get('rpsKeyboard')
//                ]);
//            }elseif (Cache::get('rps') == 'rock' and $data == 'paper'
//                or Cache::get('rps') == 'paper' and $data == 'scissors'
//                or Cache::get('rps') == 'scissors' and $data == 'rock') {
//                Telegram::deleteMessage([
//                    'chat_id' => $id,
//                    'message_id' => $callback->message->messageId
//                ]);
//                Telegram::sendMessage(
//                    ['chat_id' => $id,
//                        'text' => "you :  {$data}\n bot : " . Cache::get('rps') . " \n you won"
//                    ]);
//            } else {
//                Telegram::deleteMessage([
//                    'chat_id' => $id,
//                    'message_id' => $callback->message->messageId
//                ]);
//                Telegram::sendMessage([
//                    'chat_id' => $telegram->callbackQuery->from->id,
//                    'text' => "you : {$telegram->callbackQuery->data} \n bot : " . Cache::get('rps') . " \n you lost"
//                ]);
//            }
//        }
        /**
         * end game rps with bot
         */




        /**
         * welcome to BOT
         */
        if ($telegram->message and $telegram->message->text == '/start') {
            Cache::flush();
            if (!TelegramBot::where('chat_id', $telegram->message->from->id)->first()) {
                TelegramBot::create(['chat_id' => $telegram->message->from->id]);
                Telegram::sendMessage([
                    'chat_id' => $telegram->message->from->id,
                    'text' => "  خوش امدید  \n شما هر ساعت قیمت های ارز دیجیتال را دریافت میکنید "
                ]);
            }
        }

        /**
         * command /sendToGp
         */
        if ($telegram->message and $telegram->message->text == '/sendtogp') {
            Cache::flush();
            $message = $telegram->message;
            $chatId = $message->from->id;
            if ($chatId == env('TELEGRAM_BOT_ADMIN')) {  // check for admin
                Cache::put("user_status{$telegram->message->from->id}" , 'are-u-sure');
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "(اگر میخواهید command یا دسنوری را ارسال کنید لطفا قبل از command یا دستور متنی را اضافه کنید) \n پیام را ارسال کنید"
                ]);
            } else {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'شما ادمین نیستید!'
                ]);
            }
        }elseif ($telegram->message
            and Cache::get("user_status{$telegram->message->from->id}") == 'are-u-sure') { // ask user for confirmation message
            if ($telegram->message->has('photo')
            or $telegram->message->has('text')
            or $telegram->message->has('video')) {

                $chatId = $telegram->message->from->id;
                $message = $telegram->message;
                $keyboard = Keyboard::make()->inline()->row([
                    Keyboard::inlineButton(['text' => 'بله', 'callback_data' => 'send_message_confirm_yes']),
                ])->row([
                    Keyboard::inlineButton(['text' => 'خیر', 'callback_data' => 'send_message_confirm_no'])
                ]);
                Cache::flush();
                /**
                 * to send audio , voice , document (dont forget to uncomment send section)
                 */
//            if ($message->has('audio')){
//                Cache::put('audio' , $message->audio->fileId);
//            }elseif ($message->has('voice')){
//                Cache::put('voice' , $message->voice->fileId);
//            }else
//                if ($message->has('document')){
//                Cache::put('document' ,$message->document->fileId);
//            }else
                /**
                 * end to send audio , voice , document
                 */


                /**
                 * forward message (dont forget to uncomment send section)
                 */
//                if($message->has('forward_from_chat') and $message->forwardFromChat->type == 'channel'){
//
//                    Cache::put('other_from_chat_id', $message->from->id);
//                    Cache::put('other', $message->messageId);
//                }else
                    if ($message->has('photo')) {
                    $photo = $telegram->message->photo;
                    $files = json_decode($photo, true);
                    Cache::put('photo', end($files)['file_id']);
                    Cache::put('photo-caption', $message->caption);
                } elseif ($message->has('text')) {
                    Cache::put('message', $message->text);
                } elseif ($message->has('video')) {

                    Cache::put('video-caption', $message->caption);
                    Cache::put('video', $message->video->fileId);
                }


                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'reply_markup' => $keyboard,
                    'text' => "آیا مطمن هستید؟"
                ]);
                Cache::forget("user_status{$message->from->id}");
                Cache::put("user_status{$message->from->id}", 'waiting-for-msg');
            }else{
                Telegram::sendMessage([
                    'chat_id' => $telegram->message->from->id,
                    'text' => "از این عمل ساپورت نمیشود\n پیام خود را وارد کنید"
                ]);
            }
        }


        if($telegram->callbackQuery AND Cache::get("user_status{$telegram->callbackQuery->from->id}") == 'waiting-for-msg') { // send to groups


            switch ($telegram->callbackQuery->data) {
                    case 'send_message_confirm_yes':
                        if (Cache::has('photo')) {
                            foreach (TelegramGroup::where('user_adder' , $telegram->callbackQuery->from->id)->get() as $groups) {

                                $sendStatus = Telegram::sendPhoto([ // send photo
                                    'chat_id' => $groups->group_id,
                                    'photo' => Cache::get('photo'),
                                    'caption' => Cache::get('photo-caption')
                                ]);
                            }
                            Cache::forget('photo');
                            Cache::forget('photo-caption');
                        } elseif(Cache::has('message')) {

                            foreach (TelegramGroup::where('user_adder' , $telegram->callbackQuery->from->id)->get() as $groups) {
                                $sendStatus = Telegram::sendMessage([ // send message
                                    'chat_id' => $groups->group_id,
                                    'text' => Cache::get('message')
                                ]);
                            }
                            Cache::forget('message');
                        } elseif(Cache::has('video')) {

                            foreach (TelegramGroup::where('user_adder' , $telegram->callbackQuery->from->id)->get() as $groups) {
                                $sendStatus = Telegram::sendVideo([
                                    'chat_id' => $groups->group_id,
                                    'video' => Cache::get('video'),
                                    'caption' => Cache::get('video-caption')
                                ]);
                            }
                            Cache::forget('video');
                        }

                        /**
                         * audio , voice , document , forwardMessage send ability
                         */
//                        elseif (Cache::has('other')){
//                            foreach (TelegramGroup::where('user_adder' , $telegram->callbackQuery->from->id)->get() as $groups) {
//                                $sendStatus = Telegram::forwardMessage([
//                                    'chat_id' => $groups->group_id,
//                                    'from_chat_id' => Cache::get('other_from_chat_id'),
//                                    'message_id' => Cache::get('other')
//                                ]);
//                            }
//                            Cache::forget('other_from_chat_id');
//                            Cache::forget('other');
//                        }
//                        elseif(Cache::has('document')) {
//                            foreach (TelegramGroup::where('user_adder' , $telegram->callbackQuery->from->id)->get() as $groups) {
//                                $sendStatus = Telegram::sendDocument([
//                                    'chat_id' => $groups->group_id,
//                                    'document' => Cache::get('document')
//                                ]);
//                            }
//                            Cache::forget('document');
//                        }elseif(Cache::has('audio')) {
//                            foreach (TelegramGroup::where('user_adder' , $telegram->callbackQuery->from->id)->get() as $groups) {
//                                $sendStatus = Telegram::sendAudio([
//                                    'chat_id' => $groups->group_id,
//                                    'audio' => Cache::get('audio')
//                                ]);
//                            }
//                            Cache::forget('audio');
//                        } elseif(Cache::has('voice')) {
//                            foreach (TelegramGroup::where('user_adder' , $telegram->callbackQuery->from->id)->get() as $groups) {
//                                $sendStatus = Telegram::sendVoice([
//                                    'chat_id' => $groups->group_id,
//                                    'voice' => Cache::get('voice')
//                                ]);
//                            }
//                            Cache::forget('voice');
//                        }

                        Cache::forget("user_status{$telegram->callbackQuery->from->id}");
                        if ($sendStatus) {
                            Telegram::deleteMessage([   // delete confirmation message
                                'chat_id' => $telegram->callbackQuery->from->id,
                                'message_id' => $telegram->callbackQuery->message->messageId
                            ]);
                            Telegram::sendMessage([
                                'chat_id' => $telegram->callbackQuery->from->id,
                                'text' => 'با موفقیت ارسال شد'
                            ]);
                        }
                        break;
                    case 'send_message_confirm_no':
                        Telegram::deleteMessage([
                            'chat_id' => $telegram->callbackQuery->from->id,
                            'message_id' => $telegram->callbackQuery->message->messageId
                        ]);
                        Telegram::sendMessage([
                            'chat_id' => $telegram->callbackQuery->from->id,
                            'text' => 'پیام خود را وارد کنید'
                        ]);
                        Cache::put("user_status{$telegram->callbackQuery->from->id}", 'are-u-sure');
                        break;
                }
        }
    }

    public function setWebhook()
    {
        $telegram = Telegram::getWebhookUpdate();
        if ($telegram->message and $telegram->message->text == '/game') {
            Cache::flush();

            if (!TelegramBot::where('chat_id', $telegram->message->from->id)->first())
                TelegramBot::create(['chat_id' => $telegram->message->from->id]);

            $id = $telegram->message->from->id;
            $userGame = TelegramBot::where('chat_id', $id)->first();
            $userGame->user_status = 'waiting-for-player';
            $userGame->save();
//            $userGame = TelegramBot::where('chat_id' , $id)->update(['user_status' => 'waiting-for-player']);
            if ($userGame) {
                Telegram::sendMessage([
                    'chat_id' => $id,
                    'text' => 'شما در صف بازی هستید'
                ]);
            }
            if ($readyPlayer = TelegramBot::where('user_status', 'waiting-for-player')->whereNotIn('chat_id', $id)->first()) {
                TelegramBot::where('chat_id', $id)->update(['play' => $readyPlayer->chat_id]);
                TelegramBot::where('chat_id', $readyPlayer->chat_id)->update(['play' => $userGame->chat_id]);

                $rps = Keyboard::make()->inline()->row(
                    [Keyboard::inlineButton(['text' => 'سنگ', 'callback_data' => 'rock']),],
                )->row(
                    [Keyboard::inlineButton(['text' => 'کاغذ', 'callback_data' => 'paper']),],
                )->row(
                    [Keyboard::inlineButton(['text' => 'قیچی', 'callback_data' => 'scissors']),],
                );
                Telegram::sendMessage([
                    'chat_id' => $userGame->chat_id,
                    'text' => 'انتخاب کنید',
                    'reply_markup' => $rps
                ]);
                Telegram::sendMessage([
                    'chat_id' => $readyPlayer->chat_id,
                    'text' => 'انتخاب کنید',
                    'reply_markup' => $rps
                ]);
            }
        }elseif($telegram->callbackQuery AND in_array($telegram->callbackQuery->data,['rock','paper','scissors'])) {
            TelegramBot::where('chat_id', $telegram->callbackQuery->from->id)->update(['user_status', $telegram->callbackQuery->data]);

            if (    $userOne = TelegramBot::where('chat_id', $telegram->callbackQuery->from->id)->whereIn('user_status', ['rock', 'paper', 'scissors'])->first()
                and $userTwo = TelegramBot::where('chat_id', $userOne->play)->whereIn('user_status', ['rock', 'paper', 'scissors'])->first()) {

                $userOneChoose = $userOne->user_status;
                $userTwoChoose = $userTwo->user_status;

                $rps = Keyboard::make()->inline()->row(
                    [Keyboard::inlineButton(['text' => 'سنگ', 'callback_data' => 'rock']),],
                )->row(
                    [Keyboard::inlineButton(['text' => 'کاغذ', 'callback_data' => 'paper']),],
                )->row(
                    [Keyboard::inlineButton(['text' => 'قیچی', 'callback_data' => 'scissors']),],
                );

                if ($userOneChoose == $userTwoChoose) {
                    Telegram::sendMessage([
                        'chat_id' => $userTwo->chat_id,
                        'text' => "you :  {$userTwoChoose}\n player 2 : {$userOneChoose} \n draw \n pick again",
                        'reply_markup' => $rps
                    ]);
                    Telegram::sendMessage([
                        'chat_id' => $userOne->chat_id,
                        'text' => "you :  {$userOneChoose}\n player 2 : {$userTwoChoose} \n draw \n pick again",
                        'reply_markup' => $rps
                    ]);
                }
                if ($userOneChoose == 'rock' and $userTwoChoose == 'paper'
                    or $userOneChoose == 'paper' and $userTwoChoose == 'scissors'
                    or $userOneChoose == 'scissors' and $userTwoChoose == 'rock') {
                    Telegram::sendMessage([
                        'chat_id' => $userTwo->chat_id,
                        'text' => "you :  {$userTwoChoose}\n player 2 : {$userOneChoose} \n you lost"
                    ]);
                    Telegram::sendMessage([
                        'chat_id' => $userOne->chat_id,
                        'text' => "you :  {$userOneChoose}\n player 2 : {$userTwoChoose} \n you won"
                    ]);
                    TelegramBot::where('chat_id' , $userOne->chat_id)->update(['play' => '' , 'user_status' => '']);
                    TelegramBot::where('chat_id' , $userTwo->chat_id)->update(['play' => '' , 'user_status' => '']);
                } else {
                    Telegram::sendMessage([
                        'chat_id' => $userTwo->chat_id,
                        'text' => "you :  {$userTwoChoose}\n player 2 : {$userOneChoose} \n you won"
                    ]);
                    Telegram::sendMessage([
                        'chat_id' => $userOne->chat_id,
                        'text' => "you :  {$userOneChoose}\n player 2 : {$userTwoChoose} \n you lost"
                    ]);
                    TelegramBot::where('chat_id' , $userOne->chat_id)->update(['play' => '' , 'user_status' => '']);
                    TelegramBot::where('chat_id' , $userTwo->chat_id)->update(['play' => '' , 'user_status' => '']);
                }
            }
        }
    }



}
