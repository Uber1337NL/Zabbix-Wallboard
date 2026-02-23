<?php

/**
 * RemoteData_Zabbix
 * Handles all communication with the Zabbix API.
 * Optimized for performance: only fetches essential trigger and event status.
 */
class RemoteData_Zabbix {

    protected $URL;
    protected $USERNAME;
    protected $PASSWORD;
    protected $BASIC_AUTH;
    protected $AUTH_HASH;
    protected $ZBX_VERSION;

    public function __construct(array $config) {
        $this->URL        = $config['URL'];
        $this->USERNAME   = $config['USERNAME'];
        $this->PASSWORD   = $config['PASSWORD'];
        $this->BASIC_AUTH = $config['BASIC_AUTH'];

        if (isset($_SESSION['AUTH_HASH'])) {
            $this->AUTH_HASH = $_SESSION['AUTH_HASH'];
        } else {
            $this->AUTH_HASH   = $this->api_query('user.login', ['password' => $this->PASSWORD, 'username' => $this->USERNAME]);
            $this->ZBX_VERSION = $this->get_zbx_version();
        }
    }

    public function __destruct() {
        $this->AUTH_HASH = null;
    }

    public function getHostgroups($params) {
        return $this->api_fetch_array('hostgroup.get', $params);
    }

    public function getTriggers($params) {
        // Zabbix geeft bij triggers via 'selectLastEvent' al aan of een event 'acknowledged' is (0 of 1)
        // en via 'selectHosts' de 'maintenance_status'.
        return $this->api_fetch_array('trigger.get', $params);
    }

    public function getEventDetails($params) {
        // Voor event details is nu alleen de rauwe array van belang.
        // De UI (Wallboard.php) handelt de weergave van de acknowledge-berichten af.
        return $this->api_fetch_array('event.get', $params);
    }

    public function get_zbx_version() {
        $version = $this->api_query('apiinfo.version', []);
        return explode(".", (string)$version);
    }

    private function api_fetch_array($METHOD, $PARAMS) {
        $RESULT = $this->api_query($METHOD, $PARAMS);
        return is_array($RESULT) ? $RESULT : [];
    }

    private function api_query($METHOD, $PARAMS = []) {
        if ($this->AUTH_HASH == null && !in_array($METHOD, ['user.login', 'apiinfo.version'])) {
            throw new Exception('No active API login', 11);
        }

        $BODY = json_encode([
            'jsonrpc' => '2.0',
            'method'  => $METHOD,
            'params'  => $PARAMS,
            'id'      => 1,
        ]);

        $AUTH_TOKEN = in_array($METHOD, ['user.login', 'apiinfo.version']) ? null : $this->AUTH_HASH;
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

    private function api_curl($URL, $DATA, $AUTH_TOKEN = null) {
        $CURL = curl_init($URL);
        $HEADERS = ['Content-Type: application/json-rpc', 'User-Agent: ZbxWallboard'];
        if ($AUTH_TOKEN !== null) {
            $HEADERS[] = 'Authorization: Bearer ' . $AUTH_TOKEN;
        }

        curl_setopt_array($CURL, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $HEADERS,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $DATA,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        if ($this->BASIC_AUTH) {
            curl_setopt($CURL, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($CURL, CURLOPT_USERPWD, "{$this->USERNAME}:{$this->PASSWORD}");
        }

        $RESULT = curl_exec($CURL);
        return $RESULT;
    }
}
