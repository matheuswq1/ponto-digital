<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Versão do texto / política exibida no aceite do espelho
    |--------------------------------------------------------------------------
    |
    | Incrementar quando alterar a declaração legal ou o fluxo que o colaborador
    | confirma na app (serve como referência na auditoria).
    |
    */
    'terms_version' => env('PAY_MIRROR_TERMS_VERSION', 'v1'),

];
