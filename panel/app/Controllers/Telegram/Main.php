<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Controllers\Telegram;



class Main
{

    private $telegram;

    public function __construct()
    {
        $botApiKey      = 'testtoken';
        $this->telegram = new \App\Libraries\Telegram($botApiKey);
    }

    public function handleRequest($request, $response, $args)
    {
        $telegram   = $this->telegram;
        $content    = $request->getBody()->getContents();
        $input      = json_decode($content, true);

        $telegram->setData($input);

        if (isset($input['callback_query'])) {
            $this->handleCallbackQuery($input['callback_query']);
        } else {
            $chatId        = $telegram->ChatID();
            $responseText  = 'Received an update from Telegram!' .  $chatId;

            $option = [["\xF0\x9F\x90\xAE"], ['Git', 'Credit']];
            // Create a permanent custom keyboard
            $keyb = $telegram->buildKeyBoard($option, $onetime = false);
            $content = ['chat_id' => $chatId, 'reply_markup' => $keyb, 'text' => "Welcome to CowBot \xF0\x9F\x90\xAE \nPlease type /cowsay or click the Cow button !"];
            $telegram->sendMessage($content);
        }
    }

    public function handleCallbackQuery($callbackQueryData)
    {
        $data = $callbackQueryData['data'];
        $chat_id = $callbackQueryData['message']['chat']['id'];

        // Handle the callback data
        switch ($data) {
            case 'option_1':
                $responseText = 'Button 1 clicked!';
                break;
            case 'option_2':
                $responseText = 'Button 2 clicked!';
                break;
            default:
                $responseText = 'Unknown callback';
        }

        $telegram  = $this->telegram;
        // Send a response
        $telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => $responseText,
        ]);
    }
}
