<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Diogodourado\Ewelink\EwelinkClient;
use Diogodourado\Ewelink\TokenManager;

$config = require __DIR__ . '/config.php';

$client = new EwelinkClient($config);
$tm     = new TokenManager($config, $client);

header('Content-Type: text/plain; charset=utf-8');

try {
    $familiesResp = $tm->withAccessToken(function (string $accessToken) use ($client) {
        return $client->getFamilies($accessToken);
    });

    $familiesJson  = $familiesResp['json'] ?? [];
    $familiesData  = $familiesJson['data']['familyList'] ?? [];

    echo "FAMILIES:\n";
    foreach ($familiesData as $f) {
        $fid   = $f['id']   ?? 'sem-id';
        $fname = $f['name'] ?? 'sem-nome';
        echo "- {$fname} ({$fid})\n";
    }

    if (empty($familiesData[0]['id'])) {
        echo "\nNenhuma family encontrada.\n";
        exit;
    }

    $familyId = (string) $familiesData[0]['id'];

    echo "\nUsando family: {$familyId}\n\n";

    $thingsResp = $tm->withAccessToken(function (string $accessToken) use ($client, $familyId) {
        return $client->getThings($accessToken, $familyId);
    });

    $thingsJson = $thingsResp['json'] ?? [];
    $thingsData = $thingsJson['data']['thingList'] ?? [];

    echo "DEVICES:\n";

    foreach ($thingsData as $t) {
        $itemType = $t['itemType'] ?? null;
        if ($itemType === 3) {
            continue;
        }

        $item     = $t['itemData'] ?? [];
        $name     = $item['name']      ?? 'sem-nome';
        $deviceId = $item['deviceid']  ?? '';
        $params   = $item['params']    ?? [];
        $online   = $item['online']    ?? null;
        $thingId  = $t['itemId']       ?? '';

        if ($thingId === '' && $deviceId !== '') {
            $thingId = $deviceId;
        }

        $state = null;
        if (isset($params['switch'])) {
            $state = $params['switch'];
        } elseif (isset($params['switches'][0]['switch'])) {
            $state = $params['switches'][0]['switch'];
        } elseif (isset($params['powerState'])) {
            $state = $params['powerState'];
        }

        echo "Name: {$name}\n";
        echo "  thingId: {$thingId}\n";
        echo "  deviceId: {$deviceId}\n";
        echo "  online: " . var_export($online, true) . "\n";
        echo "  state: " . var_export($state, true) . "\n";
        echo "\n";
    }

} catch (\Throwable $e) {
    echo 'Erro: ' . $e->getMessage() . "\n";
}
