# eWeLink Control PHP

SDK em PHP para integrar e controlar dispositivos eWeLink (Sonoff etc.) via OAuth2 e API v2.

Pacote: `diogodourado/ewelink-control-php`

## Instalação

Via Composer:

```bash
composer require diogodourado/ewelink-control-php
```

## Uso básico

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Diogodourado\Ewelink\EwelinkClient;
use Diogodourado\Ewelink\TokenManager;

$config = [
    'appId'       => 'SEU_APP_ID',
    'appSecret'   => 'SEU_APP_SECRET',
    'redirectUrl' => 'https://seu-dominio.com/ewelink/callback.php',
    'tokensFile'  => __DIR__ . '/tokens.json',
];



$client = new EwelinkClient($config);
$tm     = new TokenManager($config, $client);

// Exemplo: listar famílias
$familiesResp = $tm->withAccessToken(function (string $accessToken) use ($client) {
    return $client->getFamilies($accessToken);
});

var_dump($familiesResp['json']);
```



## Fluxo OAuth2 resumido

1. Crie um app no painel eWeLink, configure o Redirect URL (por exemplo `https://seu-dominio.com/ewelink/callback.php`);
2. Use `EwelinkClient::getAuthUrl()` para gerar o link de login;
3. Usuário faz login, o eWeLink redireciona para o `callback.php` com `code` e `region`;
4. No `callback.php`, troque o `code` pelo par `accessToken` + `refreshToken` com `getTokenFromCode()` e salve no `tokensFile`;
5. Use `TokenManager::withAccessToken()` para chamar qualquer endpoint com auto-refresh de token.

ATENÇÃO: Sugiro migrar os tokens e credenciais para variáveis de ambiente (.env) ou um cofre seguro, evitando deixá-los em arquivos do projeto. Isso facilita futuras melhorias e aumenta a segurança

Exemplos completos estão em `examples/`.
