<?php

return [
    // Motor de execução de comandos no equipamento.
    'execution' => [
        // Tempo limite (segundos) por conexão/passo quando o passo não define o seu próprio.
        'timeout' => (int) env('MAPDEVICE_EXEC_TIMEOUT', 20),
    ],
];
