<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Diogodourado\Ewelink\Ewelink\EwelinkClient;

session_start();

$config = require __DIR__ . '/config.php';

if (empty($_GET['code']) || empty($_GET['region']) || empty($_GET['state'])) {
    echo 'Parâmetros ausentes no callback.';
    exit;
}

if (empty($_SESSION['ewelink_state']) || $_GET['state'] !== $_SESSION['ewelink_state']) {
    echo 'State inválido.';
    exit;
}

$code   = (string) $_GET['code'];
$region = (string) $_GET['region'];

$client = new EwelinkClient($config);

$resp = $client->getTokenFromCode($code, $region);

if (($resp['httpCode'] ?? 0) !== 200 || empty($resp['json']['data'])) {
    echo '<pre>';
    var_dump($resp);
    echo '</pre>';
    exit;
}

$data = $resp['json']['data'];

$tokens = [
    'region'       => $region,
    'accessToken'  => $data['accessToken']  ?? null,
    'refreshToken' => $data['refreshToken'] ?? null,
    'expiresIn'    => $data['expiresIn']    ?? null,
    'obtainedAt'   => time(),
];

file_put_contents($config['tokensFile'], json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo 'Tokens salvos em ' . htmlspecialchars($config['tokensFile'], ENT_QUOTES, 'UTF-8');
