<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Diogodourado\Ewelink\EwelinkClient;

session_start();

$config = require __DIR__ . '/config.php';

$client = new EwelinkClient($config);

$state = 'ewelink-' . bin2hex(random_bytes(4));
$_SESSION['ewelink_state'] = $state;

$authUrl = $client->getAuthUrl($state);

header('Location: ' . $authUrl);
exit;
