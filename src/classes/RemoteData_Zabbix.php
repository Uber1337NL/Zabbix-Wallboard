<?php

/**
 * RemoteData_Zabbix
 * Handles all communication with the Zabbix API.
 * Optimized for performance: only fetches essential trigger and event status.
 */
class RemoteData_Zabbix
{
    public function __construct(
        private readonly string $url,
        private readonly string $apiToken,
        private readonly bool   $basicAuth      = false,
        private readonly string $basicAuthUser  = '',
        private readonly string $basicAuthPass  = '',
        private readonly bool   $verifySsl      = true,
        private readonly int    $connectTimeout = 5,
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            url:            $config['URL'],
            apiToken:       $config['API_TOKEN'],
            basicAuth:      $config['BASIC_AUTH']      ?? false,
            basicAuthUser:  $config['BASIC_AUTH_USER'] ?? '',
            basicAuthPass:  $config['BASIC_AUTH_PASS'] ?? '',
            verifySsl:      $config['VERIFY_SSL']      ?? true,
            connectTimeout: $config['CONNECT_TIMEOUT'] ?? 5,
        );
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
            'method'  => $method,
            'params'  => $params,
            'id'      => 1,
        ]);

        $data = json_decode((string) $this->curlRequest($body), true);

        return match (true) {
            !empty($data['result']) => $data['result'],
            !empty($data['error'])  => throw new RuntimeException(
                sprintf('API Error: [%s] %s - %s',
                    $data['error']['code'],
                    $data['error']['message'],
                    $data['error']['data'] ?? ''
                ), 12
            ),
            default => false,
        };
    }

    private function curlRequest(string $data): string|false
    {
        $ch = curl_init($this->url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json-rpc',
                "Authorization: Bearer {$this->apiToken}",
            ],
        ]);

        if ($this->basicAuth) {
            curl_setopt_array($ch, [
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD  => "{$this->basicAuthUser}:{$this->basicAuthPass}",
            ]);
        }

        return curl_exec($ch);
    }
}
