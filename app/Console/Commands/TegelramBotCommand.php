<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TegelramBotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tg:update';

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
        $telegramService = app(TelegramService::class);
        $updateID = 0;
        $updates = $telegramService->getUpdate(0);
        $updates = $telegramService->getUpdate($updateID);
        foreach ($updates as $update){
            $telegramService->sendMessage($update['message']['chat']['id'] , 'hello');
            $updateID = $update['update_id'];
        }
        $updates = $telegramService->getUpdate($updateID+1);
        return Command::SUCCESS;
    }
}
