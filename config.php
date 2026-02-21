<?php

return [
    'ZABBIX' => [
        'URL' => getenv('ZABBIX_URL') ?: 'https://zabbix.example.com/api_jsonrpc.php',
        'USERNAME' => getenv('ZABBIX_USERNAME') ?: '',
        'PASSWORD' => getenv('ZABBIX_PASSWORD') ?: '',
        'BASIC_AUTH' => (bool)getenv('ZABBIX_BASIC_AUTH') ?: false,
        'ENABLED' => true,
        'VERIFY_SSL' => true,
        'TIMEOUT' => 30,
        'CONNECT_TIMEOUT' => 5
    ],

    'REVERSE_PROXY_PATH' => '',

    'SESSION' => [
        'LIFETIME' => 3600,
        'COOKIE_HTTPONLY' => true,
        'COOKIE_SECURE' => true,
        'COOKIE_SAMESITE' => 'Strict'
    ],

    'DISPLAY' => [
        'TITLE' => 'Zabbix Wallboard',
        'PROBLEM_COUNT_SHOW' => 0,
        'LUNCH_REMINDER' => true,
        'LUNCH_REMINDER_START' => 1200,
        'LUNCH_REMINDER_END' => 1230
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

    'EVENT_SEARCH_PARAMS' => [
        'eventids' => null,
        'output' => 'extend',
        'select_acknowledges' => 'extend'
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
