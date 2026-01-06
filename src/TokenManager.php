<?php

declare(strict_types=1);

namespace Diogodourado\Ewelink;

final class TokenManager
{
    private string $tokensFile;
    private EwelinkClient $client;

    public function __construct(array $config, EwelinkClient $client)
    {
        $this->tokensFile = (string)($config['tokensFile'] ?? '');
        if ($this->tokensFile === '') {
            throw new \InvalidArgumentException('Config tokensFile é obrigatório.');
        }

        $this->client = $client;
    }

    public function withAccessToken(callable $fn): array
    {
        $tokens = $this->loadTokens();

        $this->client->setRegion((string)$tokens['region']);
        $resp = $fn((string)$tokens['accessToken']);

        if (!$this->isTokenExpired($resp)) {
            return $resp;
        }

        $this->refreshTokens();

        $tokens = $this->loadTokens();
        $this->client->setRegion((string)$tokens['region']);

        return $fn((string)$tokens['accessToken']);
    }

    public function refreshTokens(): array
    {
        $tokens = $this->loadTokens();

        $this->client->setRegion((string)$tokens['region']);
        $new = $this->client->refreshToken(
            (string)$tokens['refreshToken'],
            (string)$tokens['region']
        );

        if (($new['httpCode'] ?? 0) !== 200 || empty($new['json']['data'])) {
            return $new;
        }

        $data = $new['json']['data'];

        $tokens['accessToken']  = $data['accessToken']  ?? $tokens['accessToken'];
        $tokens['refreshToken'] = $data['refreshToken'] ?? $tokens['refreshToken'];
        $tokens['expiresIn']    = $data['expiresIn']    ?? ($tokens['expiresIn'] ?? null);
        $tokens['obtainedAt']   = time();

        $this->saveTokens($tokens);

        return $new;
    }

    private function loadTokens(): array
    {
        if (!file_exists($this->tokensFile)) {
            throw new \RuntimeException('Arquivo de tokens não encontrado: ' . $this->tokensFile);
        }

        $json = file_get_contents($this->tokensFile);
        $tokens = json_decode((string)$json, true);

        if (
            !is_array($tokens) ||
            empty($tokens['accessToken']) ||
            empty($tokens['refreshToken']) ||
            empty($tokens['region'])
        ) {
            throw new \RuntimeException('Conteúdo de tokens inválido em ' . $this->tokensFile);
        }

        return $tokens;
    }

    private function saveTokens(array $tokens): void
    {
        $json = json_encode(
            $tokens,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        file_put_contents($this->tokensFile, (string)$json);
    }

    private function isTokenExpired(array $resp): bool
    {
        if (($resp['httpCode'] ?? 0) === 401) {
            return true;
        }

        $j = $resp['json'] ?? null;

        if (is_array($j)) {
            $msg = strtolower(json_encode($j));
            if (
                strpos($msg, 'token') !== false &&
                (strpos($msg, 'expire') !== false || strpos($msg, 'invalid') !== false)
            ) {
                return true;
            }
        }

        return false;
    }
}
