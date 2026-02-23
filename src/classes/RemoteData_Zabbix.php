<?php

/**
 * RemoteData_Zabbix
 * Handles all communication with the Zabbix API.
 * Optimized for performance: only fetches essential trigger and event status.
 */
class RemoteData_Zabbix {

    protected string $URL;
    protected string $USERNAME;
    protected string $PASSWORD;
    protected bool $BASIC_AUTH;
    protected bool $VERIFY_SSL;
    protected int $CONNECT_TIMEOUT;
    protected ?string $AUTH_HASH = null;

    public function __construct(array $config) {
        $this->URL             = $config['URL'];
        $this->USERNAME        = $config['USERNAME'];
        $this->PASSWORD        = $config['PASSWORD'];
        $this->BASIC_AUTH      = $config['BASIC_AUTH'];
        $this->VERIFY_SSL      = $config['VERIFY_SSL'] ?? true;
        $this->CONNECT_TIMEOUT = $config['CONNECT_TIMEOUT'] ?? 5;

        if (isset($_SESSION['AUTH_HASH'])) {
            $this->AUTH_HASH = $_SESSION['AUTH_HASH'];
        } else {
            $this->AUTH_HASH = $this->api_query('user.login', ['password' => $this->PASSWORD, 'username' => $this->USERNAME]);
        }
    }

    public function __destruct() {
        $this->AUTH_HASH = null;
    }

    public function getHostgroups(array $params): array {
        return $this->api_fetch_array('hostgroup.get', $params);
    }

    public function getTriggers(array $params): array {
        return $this->api_fetch_array('trigger.get', $params);
    }

    private function api_fetch_array(string $METHOD, array $PARAMS): array {
        $RESULT = $this->api_query($METHOD, $PARAMS);
        return is_array($RESULT) ? $RESULT : [];
    }

    private function api_query(string $METHOD, array $PARAMS = []): mixed {
        if ($this->AUTH_HASH === null && !in_array($METHOD, ['user.login', 'apiinfo.version'], true)) {
            throw new Exception('No active API login', 11);
        }

        $BODY = json_encode([
            'jsonrpc' => '2.0',
            'method'  => $METHOD,
            'params'  => $PARAMS,
            'id'      => 1,
        ]);

        $AUTH_TOKEN = in_array($METHOD, ['user.login', 'apiinfo.version'], true) ? null : $this->AUTH_HASH;
        $DATA_JSON = $this->api_curl($this->URL, $BODY, $AUTH_TOKEN);
        $DATA      = json_decode((string)$DATA_JSON, true);

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

    private function api_curl(string $URL, string $DATA, ?string $AUTH_TOKEN = null): string|false {
        $CURL = curl_init($URL);
        $HEADERS = ['Content-Type: application/json-rpc', 'User-Agent: ZbxWallboard'];
        if ($AUTH_TOKEN !== null) {
            $HEADERS[] = 'Authorization: Bearer ' . $AUTH_TOKEN;
        }

        curl_setopt_array($CURL, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->CONNECT_TIMEOUT,
            CURLOPT_HTTPHEADER     => $HEADERS,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $DATA,
            CURLOPT_SSL_VERIFYPEER => $this->VERIFY_SSL,
        ]);

        if ($this->BASIC_AUTH) {
            curl_setopt($CURL, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($CURL, CURLOPT_USERPWD, "{$this->USERNAME}:{$this->PASSWORD}");
        }

        $RESULT = curl_exec($CURL);
        curl_close($CURL);
        return $RESULT;
    }
}
