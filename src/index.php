<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
    ]);
}

require_once 'classes/RemoteData_Zabbix.php';
require_once 'classes/Wallboard.php';
require_once 'classes/ExceptionHandler.php';

$config = require 'config.php';
$config['SCRIPT_PATH'] = ($config['REVERSE_PROXY_PATH'] ?? '') . ($_SERVER['SCRIPT_NAME'] ?? '');

$exceptionHandler = new ExceptionHandler($config);

if (!function_exists('validateInput')) {
    function validateInput(string $key, string $type = 'string', mixed $default = null): mixed
    {
        $value = $_GET[$key] ?? $_POST[$key] ?? null;
        if ($value === null) {
            return $default;
        }

        return match ($type) {
            'int' => filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $default,
            'array' => array_map(
                static fn($item): string => is_string($item) ? htmlspecialchars($item, ENT_QUOTES, 'UTF-8') : (string)$item,
                is_array($value) ? $value : [$value]
            ),
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default,
            default => is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $default,
        };
    }
}

// Backend + hostgroups
$remoteData = RemoteData_Zabbix::fromConfig($config['ZABBIX']);
$hostgroups = $remoteData->getHostgroups($config['HOSTGROUP_SEARCH_PARAMS'] ?? []);

// Filter: Hostgroup
$groupIdRaw = validateInput('groupid', 'array');
if ($groupIdRaw !== null) {
    if (in_array('all', $groupIdRaw, true)) {
        unset($_SESSION['groupid'], $_SESSION['group_name']);
    } else {
        $validGroupIds = array_map('strval', array_column($hostgroups, 'groupid'));
        $filteredIds = array_values(array_filter(
            $groupIdRaw,
            fn($id): bool => in_array($id, $validGroupIds, true)
        ));

        if (!empty($filteredIds)) {
            $_SESSION['groupid'] = $filteredIds;
            $_SESSION['group_name'] = count($filteredIds) > 1
                ? 'Filtered'
                : ($hostgroups[array_search($filteredIds[0], $validGroupIds)]['name'] ?? 'Filtered');
        } else {
            unset($_SESSION['groupid'], $_SESSION['group_name']);
        }
    }
}

// Group filter to API params
if (!empty($_SESSION['groupid']) && !in_array('all', $_SESSION['groupid'], true)) {
    $config['TRIGGER_SEARCH_PARAMS']['groupids'] = $_SESSION['groupid'];
} else {
    unset($config['TRIGGER_SEARCH_PARAMS']['groupids']);
}

// Filter: Severity
$severity = validateInput('severity', 'int');
if ($severity !== null) {
    $_SESSION['severity'] = $severity;
}

// Severity to API params
if (!empty($_SESSION['severity'])) {
    $config['TRIGGER_SEARCH_PARAMS']['min_severity'] = (int)$_SESSION['severity'];
} else {
    unset($config['TRIGGER_SEARCH_PARAMS']['min_severity']);
}

// Filter: Hide Acknowledged
$hideAcked = validateInput('hide_acked', 'bool');
if ($hideAcked !== null) {
    $_SESSION['hide_acked'] = $hideAcked;
}

// Ack filter to API params
if (!empty($_SESSION['hide_acked'])) {
    $config['TRIGGER_SEARCH_PARAMS']['withLastEventUnacknowledged'] = true;
} else {
    unset($config['TRIGGER_SEARCH_PARAMS']['withLastEventUnacknowledged']);
}

// Filter: Hide Maintenance
$hideMaint = validateInput('hide_maint', 'bool');
if ($hideMaint !== null) {
    $_SESSION['hide_maint'] = $hideMaint;
}

// Maintenance filter to API params
if (!empty($_SESSION['hide_maint'])) {
    $config['TRIGGER_SEARCH_PARAMS']['maintenance'] = false;
} else {
    unset($config['TRIGGER_SEARCH_PARAMS']['maintenance']);
}

// Wallboard initialiseren
$wallboard = new Wallboard($config['SCRIPT_PATH'], $config['DISPLAY'] ?? []);

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Triggers ophalen en renderen
$triggers = $remoteData->getTriggers($config['TRIGGER_SEARCH_PARAMS'] ?? []);

if ($isAjax) {
    $wallboard->ajaxMainContent($triggers);
    $wallboard->publish();
    exit();
}

$wallboard->generateMainContent($triggers);
$wallboard->generateMenu($hostgroups, $config['SEVERITIES'] ?? []);
$wallboard->publish();
