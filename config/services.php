<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Assistente de IA: os provedores, chaves e modelos são cadastrados na tela Assistente > Provedores.
    'ai' => [
        'timeout' => (int) env('AI_TIMEOUT', 180),        // segundos aguardando a resposta do provedor
        'history' => (int) env('AI_HISTORY_MESSAGES', 20), // mensagens anteriores enviadas como contexto
        'ca_bundle' => env('AI_CA_BUNDLE'),               // opcional: caminho de um cacert.pem para HTTPS
    ],

];
