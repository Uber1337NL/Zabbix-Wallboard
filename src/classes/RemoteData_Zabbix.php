<?php

/**
 * RemoteData_Zabbix
 * Handles all communication with the Zabbix API.
 * Optimized for performance: only fetches essential trigger and event status.
 */
class RemoteData_Zabbix
{
    protected string $URL;
    protected string $API_TOKEN;
    protected bool $BASIC_AUTH;
    protected string $BASIC_AUTH_USER;
    protected string $BASIC_AUTH_PASS;
    protected bool $VERIFY_SSL;
    protected int $CONNECT_TIMEOUT;

    public function __construct(array $config)
    {
        $this->URL = $config['URL'];
        $this->API_TOKEN = $config['API_TOKEN'];
        $this->BASIC_AUTH = $config['BASIC_AUTH'] ?? false;
        $this->BASIC_AUTH_USER = $config['BASIC_AUTH_USER'] ?? '';
        $this->BASIC_AUTH_PASS = $config['BASIC_AUTH_PASS'] ?? '';
        $this->VERIFY_SSL = $config['VERIFY_SSL'] ?? true;
        $this->CONNECT_TIMEOUT = $config['CONNECT_TIMEOUT'] ?? 5;
    }

    public function getHostgroups(array $params): array
    {
        return $this->api_fetch_array('hostgroup.get', $params);
    }

    public function getTriggers(array $params): array
    {
        return $this->api_fetch_array('trigger.get', $params);
    }

    private function api_fetch_array(string $METHOD, array $PARAMS): array
    {
        $RESULT = $this->api_query($METHOD, $PARAMS);
        return is_array($RESULT) ? $RESULT : [];
    }

    private function api_query(string $METHOD, array $PARAMS = []): mixed
    {
        $BODY = json_encode([
            'jsonrpc' => '2.0',
            'method' => $METHOD,
            'params' => $PARAMS,
            'id' => 1,
        ]);

        $DATA_JSON = $this->api_curl($this->URL, $BODY);
        $DATA = json_decode((string) $DATA_JSON, true);

        if (!empty($DATA['result'])) {
            return $DATA['result'];
        } elseif (!empty($DATA['error'])) {
            throw new Exception(
                'API Error: [' . $DATA['error']['code'] . '] ' . $DATA['error']['message'] . ' - ' . ($DATA['error']['data'] ?? ''),
                12
            );
        }
        return false;
    }

    private function api_curl(string $URL, string $DATA): string|false
    {
        $CURL = curl_init($URL);
        $HEADERS = [
            'Content-Type: application/json-rpc',
            "Authorization: Bearer {$this->API_TOKEN}"
        ];

        curl_setopt_array($CURL, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->CONNECT_TIMEOUT,
            CURLOPT_HTTPHEADER => $HEADERS,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $DATA,
            CURLOPT_SSL_VERIFYPEER => $this->VERIFY_SSL,
        ]);

        if ($this->BASIC_AUTH) {
            curl_setopt($CURL, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($CURL, CURLOPT_USERPWD, "{$this->BASIC_AUTH_USER}:{$this->BASIC_AUTH_PASS}");
        }

        $RESULT = curl_exec($CURL);
        return $RESULT;
    }
}
