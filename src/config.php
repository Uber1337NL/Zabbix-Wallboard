<?php

date_default_timezone_set('Europe/Amsterdam');

return [
    'ZABBIX' => [
        'URL' => getenv('ZABBIX_URL') ?: 'https://zabbix.example.com/api_jsonrpc.php',
        'API_TOKEN' => getenv('ZABBIX_API_TOKEN') ?: '',
        'BASIC_AUTH' => (bool)getenv('ZABBIX_BASIC_AUTH') ?: false,
        'BASIC_AUTH_USER' => getenv('ZABBIX_BASIC_AUTH_USER') ?: '',
        'BASIC_AUTH_PASS' => getenv('ZABBIX_BASIC_AUTH_PASS') ?: '',
        'VERIFY_SSL' => true,
        'CONNECT_TIMEOUT' => 5
    ],

    'REVERSE_PROXY_PATH' => '',

    'DISPLAY' => [
        'TITLE' => 'Zabbix Wallboard',
        'PROBLEM_COUNT_SHOW' => 0,
        'AJAX_REFRESH_INTERVAL' => 30000,
        'LUNCH_REMINDERS' => [
            ['start' => 1200, 'end' => 1230],
            ['start' => 1730, 'end' => 1800], 
        ]
    ],

    'TRIGGER_SEARCH_PARAMS' => [
        'output' => 'extend',
        'selectHosts' => 'extend',
        'selectLastEvent' => 'extend',
        'expandData' => 'true',
        'expandDescription' => 'true',
        'min_severity' => 0,
        'groupids' => null,
        'withLastEventUnacknowledged' => null,
        'maintenance' => null,
        'monitored' => 'true',
        'only_true' => 'true',
        'skipDependent' => 'true',
        'sortfield' => 'lastchange',
        'sortorder' => 'DESC'
    ],

    'HOSTGROUP_SEARCH_PARAMS' => [
        'output' => 'extend',
        'sortfield' => 'name',
        'sortorder' => 'ASC'
    ],



    'SEVERITIES' => [
        0 => 'Not classified',
        1 => 'Information',
        2 => 'Warning',
        3 => 'Average',
        4 => 'High',
        5 => 'Disaster'
    ]
];
