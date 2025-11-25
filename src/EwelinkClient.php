<?php
declare(strict_types=1);

namespace Diogodourado\Ewelink;

class EwelinkClient
{
    private string $appId;
    private string $appSecret;
    private string $redirectUrl;
    private ?string $region = null;
    private ?string $apiBase = null;

    public function __construct(array $config)
    {
        $this->appId       = (string)($config['appId'] ?? '');
        $this->appSecret   = (string)($config['appSecret'] ?? '');
        $this->redirectUrl = (string)($config['redirectUrl'] ?? '');

        if ($this->appId === '' || $this->appSecret === '' || $this->redirectUrl === '') {
            throw new \InvalidArgumentException('Config inválido: appId, appSecret e redirectUrl são obrigatórios.');
        }
    }

    public function setRegion(string $region): void
    {
        $this->region  = $region;
        $this->apiBase = sprintf('https://%s-apia.coolkit.cc', $region);
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function getAuthUrl(string $state = 'state'): string
    {
        $seq   = (string) (int) (microtime(true) * 1000);
        $nonce = substr(bin2hex(random_bytes(8)), 0, 8);

        $msg  = $this->appId . '_' . $seq;
        $sign = base64_encode(hash_hmac('sha256', $msg, $this->appSecret, true));

        $params = [
            'clientId'      => $this->appId,
            'seq'           => $seq,
            'authorization' => $sign,
            'redirectUrl'   => $this->redirectUrl,
            'grantType'     => 'authorization_code',
            'state'         => $state,
            'nonce'         => $nonce,
        ];

        return 'https://c2ccdn.coolkit.cc/oauth/index.html?' . http_build_query($params);
    }

    public function getTokenFromCode(string $code, string $region): array
    {
        $this->setRegion($region);

        $body = [
            'clientId'     => $this->appId,
            'clientSecret' => $this->appSecret,
            'code'         => $code,
            'grantType'    => 'authorization_code',
            'redirectUrl'  => $this->redirectUrl,
        ];

        $url = sprintf('https://%s-apia.coolkit.cc/v2/user/oauth/token', $region);

        return $this->requestWithSign('POST', $url, $body);
    }

    public function refreshToken(string $refreshToken, string $region): array
    {
        $this->setRegion($region);

        $body = [
            'clientId'     => $this->appId,
            'clientSecret' => $this->appSecret,
            'refreshToken' => $refreshToken,
            'grantType'    => 'refresh_token',
        ];

        $url = sprintf('https://%s-apia.coolkit.cc/v2/user/refresh', $region);

        return $this->requestWithSign('POST', $url, $body);
    }

    public function getFamilies(string $accessToken): array
    {
        if ($this->apiBase === null) {
            throw new \RuntimeException('Região não definida. Chame setRegion() antes.');
        }

        $url = $this->apiBase . '/v2/family';
        return $this->requestWithBearer('GET', $url, $accessToken);
    }

    public function getThings(string $accessToken, string $familyId): array
    {
        if ($this->apiBase === null) {
            throw new \RuntimeException('Região não definida. Chame setRegion() antes.');
        }

        $url = $this->apiBase . '/v2/device/thing?num=0&familyid=' . urlencode($familyId);
        return $this->requestWithBearer('GET', $url, $accessToken);
    }

    public function setThingPower(string $accessToken, string $id, bool $onOff): array
    {
        if ($this->apiBase === null) {
            throw new \RuntimeException('Região não definida. Chame setRegion() antes.');
        }

        $url = $this->apiBase . '/v2/device/thing/status';

        $body = [
            'type'  => 1,
            'id'    => $id,
            'params'=> [
                'switch' => $onOff ? 'on' : 'off',
            ],
        ];

        return $this->requestWithBearer('POST', $url, $accessToken, $body);
    }

    private function requestWithSign(string $method, string $url, array $body): array
    {
        $json = json_encode($body, JSON_UNESCAPED_SLASHES);
        $sign = base64_encode(hash_hmac('sha256', $json, $this->appSecret, true));

        $headers = [
            'Content-Type: application/json',
            'Authorization: Sign ' . $sign,
            'X-CK-Appid: ' . $this->appId,
        ];

        return $this->curl($method, $url, $headers, $json);
    }

    private function requestWithBearer(string $method, string $url, string $accessToken, ?array $body = null): array
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'X-CK-Appid: ' . $this->appId,
        ];

        $json = $body ? json_encode($body, JSON_UNESCAPED_SLASHES) : null;

        return $this->curl($method, $url, $headers, $json);
    }

    private function curl(string $method, string $url, array $headers, ?string $body = null): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'httpCode' => $code,
            'error'    => $err ?: null,
            'raw'      => $resp,
            'json'     => $resp ? json_decode($resp, true) : null,
        ];
    }
}
