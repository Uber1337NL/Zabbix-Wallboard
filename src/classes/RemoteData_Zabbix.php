<?php

declare(strict_types=1);

namespace App\Classes;

use RuntimeException;
use function is_array;
use function sprintf;

readonly class RemoteData_Zabbix
{
    public function __construct(
        private string $url,
        #[\SensitiveParameter] private string $apiToken,
        private bool $basicAuth = false,
        private string $basicAuthUser = '',
        #[\SensitiveParameter] private string $basicAuthPass = '',
        private bool $verifySsl = true,
        private int $connectTimeout = 5,
    ) {}

    public static function fromConfig(array $config): self
    {
        $mapping = [
            'URL' => 'url',
            'API_TOKEN' => 'apiToken',
            'BASIC_AUTH' => 'basicAuth',
            'BASIC_AUTH_USER' => 'basicAuthUser',
            'BASIC_AUTH_PASS' => 'basicAuthPass',
            'VERIFY_SSL' => 'verifySsl',
            'CONNECT_TIMEOUT' => 'connectTimeout',
        ];

        $args = [];
        foreach ($mapping as $configKey => $paramName) {
            if (isset($config[$configKey])) {
                $args[$paramName] = $config[$configKey];
            }
        }

        return new self(...$args);
    }

    public function getHostgroups(array $params): array
    {
        return $this->fetchArray('hostgroup.get', $params);
    }

    public function getTriggers(array $params): array
    {
        return $this->fetchArray('trigger.get', $params);
    }

    private function fetchArray(string $method, array $params): array
    {
        $result = $this->query($method, $params);
        return is_array($result) ? $result : [];
    }

    private function query(string $method, array $params = []): mixed
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1,
        ], JSON_THROW_ON_ERROR);

        $response = $this->curlRequest($body);
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        return match (true) {
            !empty($data['result']) => $data['result'],
            !empty($data['error']) => throw new RuntimeException(
                sprintf(
                    'API Error: [%s] %s - %s',
                    $data['error']['code'],
                    $data['error']['message'],
                    $data['error']['data'] ?? ''
                ),
                12
            ),
            default => [],
        };
    }

    private function curlRequest(string $data): string
    {
        $ch = curl_init($this->url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json-rpc',
                "Authorization: Bearer {$this->apiToken}",
            ],
        ];

        if ($this->basicAuth) {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERPWD] = "{$this->basicAuthUser}:{$this->basicAuthPass}";
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            throw new RuntimeException("cURL Error: $error");
        }
        return $response;
    }
}
