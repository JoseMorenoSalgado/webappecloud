<?php
declare(strict_types=1);

/**
 * Small Dokploy API transport. Do not log bodies: response objects can contain
 * database credentials. Requires PHP cURL and a valid HTTPS certificate.
 */
final class ECloudDokployClient
{
    private string $base;
    private string $apiKey;

    public function __construct(string $base, string $apiKey)
    {
        $url = parse_url($base);
        if (!$url || strtolower((string) ($url['scheme'] ?? '')) !== 'https'
            || empty($url['host']) || !empty($url['user']) || !empty($url['pass'])
            || !empty($url['query']) || !empty($url['fragment'])
            || isset($url['port']) || !in_array($url['path'] ?? '', ['', '/'], true)) {
            throw new RuntimeException('Configure an HTTPS Dokploy origin without credentials, paths or ports.');
        }
        if ($apiKey === '') {
            throw new RuntimeException('Dokploy API key is missing.');
        }
        $this->base = rtrim($base, '/');
        $this->apiKey = $apiKey;
    }

    public function post(string $operation, array $body): array
    {
        if (!preg_match('/^[a-z]+[.][a-zA-Z]+$/D', $operation)) {
            throw new InvalidArgumentException('Unsupported Dokploy operation.');
        }
        $handle = curl_init($this->base . '/api/' . $operation);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize Dokploy request.');
        }
        try {
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'x-api-key: ' . $this->apiKey,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_MAXREDIRS => 0,
            ]);
            $response = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            if (!is_string($response) || $status < 200 || $status >= 300) {
                throw new RuntimeException('Dokploy request failed: ' . $operation . ' (HTTP ' . $status . ').');
            }
            if (strlen($response) > 1048576) {
                throw new RuntimeException('Dokploy response exceeded size limit.');
            }
            $result = json_decode($response, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($result)) {
                throw new RuntimeException('Unexpected Dokploy response.');
            }
            // Be tolerant of Dokploy releases returning direct or wrapped data.
            if (isset($result['result']['data']['json']) && is_array($result['result']['data']['json'])) {
                return $result['result']['data']['json'];
            }
            if (isset($result['data']) && is_array($result['data'])) {
                return $result['data'];
            }
            return $result;
        } finally {
            curl_close($handle);
        }
    }

    public function get(string $operation, array $query): array
    {
        if (!preg_match('/^[a-z]+[.][a-zA-Z]+$/D', $operation)) {
            throw new InvalidArgumentException('Unsupported Dokploy operation.');
        }
        $handle = curl_init($this->base . '/api/' . $operation . '?' . http_build_query($query));
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize Dokploy request.');
        }
        try {
            curl_setopt_array($handle, [
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'x-api-key: ' . $this->apiKey],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            if (!is_string($response) || $status < 200 || $status >= 300 || strlen($response) > 1048576) {
                throw new RuntimeException('Dokploy read failed: ' . $operation . ' (HTTP ' . $status . ').');
            }
            $result = json_decode($response, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($result)) {
                throw new RuntimeException('Unexpected Dokploy response.');
            }
            if (isset($result['result']['data']['json']) && is_array($result['result']['data']['json'])) {
                return $result['result']['data']['json'];
            }
            if (isset($result['data']) && is_array($result['data'])) {
                return $result['data'];
            }
            return $result;
        } finally {
            curl_close($handle);
        }
    }
}
