<?php

/**
 * RemoteData_Zabbix
 * Handles all communication with the Zabbix API.
 */
class RemoteData_Zabbix {

    protected $URL;
    protected $USERNAME;
    protected $PASSWORD;
    protected $BASIC_AUTH;
    protected $AUTH_HASH;
    protected $ZBX_VERSION;

    /**
     * @param array $config Associative array with keys: URL, USERNAME, PASSWORD, BASIC_AUTH
     */
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
        if (isset($_SESSION['AUTH_HASH'])) {
            unset($_SESSION['AUTH_HASH']);
        }
        $this->AUTH_HASH = null;
    }

    // ----------------------------------------------------------------
    // camelCase public API (used by index.php)
    // ----------------------------------------------------------------

    public function getHostgroups($params) {
        return $this->get_hostgroups($params);
    }

    public function getTriggers($params) {
        return $this->get_triggers($params);
    }

    public function getEventDetails($params) {
        return $this->get_eventdetails($params);
    }

    public function addAcknowledge($eventId, $message) {
        return $this->add_acknowledge($eventId, $message);
    }

    // ----------------------------------------------------------------
    // snake_case public methods (legacy / internal)
    // ----------------------------------------------------------------

    public function get_hostgroups($PARAMS) {
        return $this->api_fetch_array('hostgroup.get', $PARAMS);
    }

    public function get_triggers($PARAMS) {
        return $this->api_fetch_array('trigger.get', $PARAMS);
    }

    public function get_eventdetails($PARAMS) {
        $EVENTDETAILS = $this->api_fetch_array('event.get', $PARAMS);
        foreach ($EVENTDETAILS[0]['acknowledges'] as $ACKED_KEY => $ACKED_FIELD) {
            if (!isset($EVENTDETAILS[0]['acknowledges'][$ACKED_KEY]['alias'])) {
                $EVENTDETAILS[0]['acknowledges'][$ACKED_KEY]['name']    = "Inaccessible UserID";
                $EVENTDETAILS[0]['acknowledges'][$ACKED_KEY]['surname'] = $EVENTDETAILS[0]['acknowledges'][$ACKED_KEY]['userid'];
            } else {
                if (!isset($EVENTDETAILS[0]['acknowledges'][$ACKED_KEY]['name'])) {
                    $EVENTDETAILS[0]['acknowledges'][$ACKED_KEY]['name'] = '';
                }
                if (!isset($EVENTDETAILS[0]['acknowledges'][$ACKED_KEY]['surname'])) {
                    $EVENTDETAILS[0]['acknowledges'][$ACKED_KEY]['surname'] = '';
                }
                if ($EVENTDETAILS[0]['acknowledges'][$ACKED_KEY]['name'] === ''
                    && $EVENTDETAILS[0]['acknowledges'][$ACKED_KEY]['surname'] === '') {
                    $EVENTDETAILS[0]['acknowledges'][$ACKED_KEY]['name'] = $EVENTDETAILS[0]['acknowledges'][$ACKED_KEY]['alias'];
                }
            }
        }
        return $EVENTDETAILS;
    }

    public function add_acknowledge($EVENTID, $MESSAGE) {
        if ($this->ZBX_VERSION[0] >= 4) {
            $this->api_query('event.acknowledge', ['eventids' => $EVENTID, 'message' => $MESSAGE, 'action' => 6]);
        } else {
            $this->api_query('event.acknowledge', ['eventids' => $EVENTID, 'message' => $MESSAGE]);
        }
    }

    public function get_zbx_version() {
        return explode(".", $this->api_query('apiinfo.version', []));
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function api_fetch_array($METHOD, $PARAMS) {
        $RESULT = $this->api_query($METHOD, $PARAMS);
        if ($RESULT === false || $RESULT === null) {
            return [];
        }
        return is_array($RESULT) ? $RESULT : [];
    }

    private function api_query($METHOD, $PARAMS = []) {
        if ($this->AUTH_HASH == null && $METHOD !== 'user.login') {
            throw new Exception('No active API login', 11);
        }

        $BODY = json_encode([
            'jsonrpc' => '2.0',
            'method'  => $METHOD,
            'params'  => $PARAMS,
            'id'      => 1,
        ]);

        $AUTH_TOKEN = ($METHOD === 'user.login' || $METHOD === 'apiinfo.version') ? null : $this->AUTH_HASH;

        $DATA_JSON = $this->api_curl($this->URL, $BODY, $AUTH_TOKEN);
        $DATA      = json_decode($DATA_JSON, true);

        if (!empty($DATA['result'])) {
            return $DATA['result'];
        } elseif (!empty($DATA['error'])) {
            throw new Exception(
                'API Error: [' . $DATA['error']['code'] . '] ' . $DATA['error']['message'] . ' - ' . $DATA['error']['data'],
                12
            );
        } else {
            return false;
        }
    }

    private function api_curl($URL, $DATA, $AUTH_TOKEN = null) {
        $CURL = curl_init($URL);

        $HEADERS   = [];
        $HEADERS[] = 'Content-Type: application/json-rpc';
        $HEADERS[] = 'User-Agent: ZbxWallboard';
        if ($AUTH_TOKEN !== null) {
            $HEADERS[] = 'Authorization: Bearer ' . $AUTH_TOKEN;
        }

        $CURL_OPTS = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_FRESH_CONNECT  => true,
            CURLOPT_ENCODING       => 'gzip',
            CURLOPT_HTTPHEADER     => $HEADERS,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => (is_array($DATA) ? http_build_query($DATA) : $DATA),
        ];

        if ($this->BASIC_AUTH === true || $this->BASIC_AUTH === 1) {
            $CURL_OPTS[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $CURL_OPTS[CURLOPT_USERPWD]  = "{$this->USERNAME}:{$this->PASSWORD}";
        }

        curl_setopt_array($CURL, $CURL_OPTS);
        $RESULT = @curl_exec($CURL);
        curl_close($CURL);
        return $RESULT;
    }
}
