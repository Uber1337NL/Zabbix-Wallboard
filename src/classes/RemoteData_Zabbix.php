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

    /**
     * Fetch triggers via trigger.get.
     *
     * Sorting is deliberately NOT delegated to the Zabbix API: on Zabbix 8.0
     * with PostgreSQL, `sortfield: lastchange` makes the API generate
     * `SELECT DISTINCT ... COALESCE(tr.lastchange,'0') AS lastchange ... ORDER BY tr.lastchange`,
     * which PostgreSQL rejects ("for SELECT DISTINCT, ORDER BY expressions must
     * appear in select list"), surfacing as API error -32500.
     * Any `sortfield`/`sortorder` params are therefore stripped from the request
     * and applied client-side on the returned result instead.
     */
    public function getTriggers(array $params): array
    {
        $sortFields = (array) ($params['sortfield'] ?? []);
        $sortOrders = (array) ($params['sortorder'] ?? []);
        unset($params['sortfield'], $params['sortorder']);

        $triggers = $this->fetchArray('trigger.get', $params);

        return self::sortRecords($triggers, $sortFields, $sortOrders);
    }

    /**
     * Stable multi-field sort mimicking the Zabbix API sortfield/sortorder semantics.
     * Numeric values are compared numerically, everything else as strings.
     *
     * @param list<string> $fields
     * @param list<string> $orders 'ASC' or 'DESC' per field (defaults to ASC)
     */
    public static function sortRecords(array $records, array $fields, array $orders = []): array
    {
        if ($fields === [] || count($records) < 2) {
            return $records;
        }

        $records = array_values($records);
        $index = array_keys($records);

        usort($index, static function (int $a, int $b) use ($records, $fields, $orders): int {
            foreach (array_values($fields) as $i => $field) {
                $va = $records[$a][$field] ?? null;
                $vb = $records[$b][$field] ?? null;

                $cmp = (is_numeric($va) && is_numeric($vb))
                    ? ((float) $va <=> (float) $vb)
                    : strcmp((string) $va, (string) $vb);

                if ($cmp !== 0) {
                    $order = strtoupper((string) ($orders[$i] ?? $orders[0] ?? 'ASC'));
                    return $order === 'DESC' ? -$cmp : $cmp;
                }
            }
            return $a <=> $b; // keep original API order for ties (stable)
        });

        return array_map(static fn(int $i): mixed => $records[$i], $index);
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
