<?php

class RemoteData_Zabbix
{
    private string $url;
    private string $username;
    private string $password;
    private bool $basicAuth;
    private bool $verifySSL;
    private int $timeout;
    private int $connectTimeout;
    private ?string $authHash = null;
    private array $zbxVersion = [];

    public function __construct(array $config)
    {
        $this->url = $config['URL'];
        $this->username = $config['USERNAME'];
        $this->password = $config['PASSWORD'];
        $this->basicAuth = $config['BASIC_AUTH'];
        $this->verifySSL = $config['VERIFY_SSL'] ?? true;
        $this->timeout = $config['TIMEOUT'] ?? 30;
        $this->connectTimeout = $config['CONNECT_TIMEOUT'] ?? 5;

        if (isset($_SESSION['AUTH_HASH'])) {
            $this->authHash = $_SESSION['AUTH_HASH'];
        } else {
            $this->login();
        }
    }

    public function __destruct()
    {
        if (isset($_SESSION['AUTH_HASH'])) {
            unset($_SESSION['AUTH_HASH']);
        }
        $this->authHash = null;
    }

    private function login(): void
    {
        $this->authHash = $this->apiQuery('user.login', [
            'username' => $this->username,
            'password' => $this->password
        ]);
        $this->zbxVersion = $this->getZabbixVersion();
        $_SESSION['AUTH_HASH'] = $this->authHash;
    }

    public function getHostgroups(array $params): array
    {
        return $this->apiFetchArray('hostgroup.get', $params);
    }

    public function getTriggers(array $params): array
    {
        return $this->apiFetchArray('trigger.get', $params);
    }

    public function getEventDetails(array $params): array
    {
        $eventDetails = $this->apiFetchArray('event.get', $params);
        
        if (empty($eventDetails[0]['acknowledges'])) {
            return $eventDetails;
        }

        foreach ($eventDetails[0]['acknowledges'] as $key => $ack) {
            if (!isset($ack['alias'])) {
                $eventDetails[0]['acknowledges'][$key]['name'] = 'Inaccessible UserID';
                $eventDetails[0]['acknowledges'][$key]['surname'] = $ack['userid'];
            } else {
                $eventDetails[0]['acknowledges'][$key]['name'] = $ack['name'] ?? '';
                $eventDetails[0]['acknowledges'][$key]['surname'] = $ack['surname'] ?? '';
                
                if (empty($ack['name']) && empty($ack['surname'])) {
                    $eventDetails[0]['acknowledges'][$key]['name'] = $ack['alias'];
                }
            }
        }

        return $eventDetails;
    }

    public function addAcknowledge(string $eventId, string $message): void
    {
        $params = [
            'eventids' => $eventId,
            'message' => $message
        ];

        if (!empty($this->zbxVersion) && (int)$this->zbxVersion[0] >= 4) {
            $params['action'] = 6;
        }

        $this->apiQuery('event.acknowledge', $params);
    }

    private function getZabbixVersion(): array
    {
        $version = $this->apiQuery('apiinfo.version', []);
        return explode('.', $version);
    }

    private function apiFetchArray(string $method, array $params): array
    {
        $result = $this->apiQuery($method, $params);
        return is_array($result) ? $result : [$result];
    }

    private function apiQuery(string $method, array $params = [])
    {
        if ($this->authHash === null && $method !== 'user.login') {
            throw new Exception('No active API login', 11);
        }

        $requestData = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'id' => 1,
            'params' => $params
        ];

        if ($method !== 'apiinfo.version' && $method !== 'user.login') {
            $requestData['auth'] = $this->authHash;
        }

        $response = $this->apiCurl($this->url, json_encode($requestData));
        $data = json_decode($response, true);

        if (!empty($data['result'])) {
            return $data['result'];
        }

        if (!empty($data['error'])) {
            throw new Exception(
                sprintf(
                    'API Error: [%s] %s - %s',
                    $data['error']['code'],
                    $data['error']['message'],
                    $data['error']['data']
                ),
                12
            );
        }

        return false;
    }

    private function apiCurl(string $url, string $data): string
    {
        $curl = curl_init($url);

        $headers = [
            'Content-Type: application/json-rpc',
            'User-Agent: ZbxWallboard/2.0'
        ];

        $curlOpts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYHOST => $this->verifySSL ? 2 : 0,
            CURLOPT_SSL_VERIFYPEER => $this->verifySSL,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_ENCODING => 'gzip',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data
        ];

        if ($this->basicAuth) {
            $curlOpts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $curlOpts[CURLOPT_USERPWD] = "{$this->username}:{$this->password}";
        }

        curl_setopt_array($curl, $curlOpts);
        $result = curl_exec($curl);

        if ($result === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new Exception("CURL Error: {$error}", 12);
        }

        curl_close($curl);
        return $result;
    }
}
