<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once 'config.php';
require_once 'classes/RemoteData_Zabbix.php';
require_once 'classes/Wallboard.php';
require_once 'classes/ExceptionHandler.php';

$config = require 'config.php';
$config['SCRIPT_PATH'] = ($config['REVERSE_PROXY_PATH'] ?? '') . ($_SERVER['SCRIPT_NAME'] ?? '');

$exceptionHandler = new ExceptionHandler();
set_exception_handler([$exceptionHandler, 'error']);

function validateInput(string $key, string $type = 'string', $default = null)
{
    if (!isset($_GET[$key]) && !isset($_POST[$key])) {
        return $default;
    }

    $value = $_GET[$key] ?? $_POST[$key];

    switch ($type) {
        case 'int':
            return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int)$value : $default;
        case 'array':
            return is_array($value) ? array_map('intval', $value) : $default;
        case 'bool':
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        default:
            return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $default;
    }
}

if (isset($_SESSION['encrypted_password']) && isset($_SESSION['username']) && isset($_SESSION['encryption_key'])) {
    $encKey = $_SESSION['encryption_key'];
    $iv = $_SESSION['iv'];
    $config['ZABBIX']['USERNAME'] = $_SESSION['username'];
    $config['ZABBIX']['PASSWORD'] = openssl_decrypt(
        $_SESSION['encrypted_password'],
        'aes-256-gcm',
        $encKey,
        OPENSSL_RAW_DATA,
        $iv,
        $_SESSION['tag']
    );
}

$backendZbx = null;
$hostgroups = [];

if ($config['ZABBIX']['ENABLED']) {
    $backendZbx = new RemoteData_Zabbix($config['ZABBIX']);
    $hostgroups = $backendZbx->getHostgroups($config['HOSTGROUP_SEARCH_PARAMS']);
}

$groupId = validateInput('groupid', 'array');
if ($groupId !== null) {
    if (in_array('all', $groupId, true)) {
        unset($_SESSION['groupid'], $_SESSION['group_name']);
    } else {
        $validGroupIds = array_column($hostgroups, 'groupid');
        $filteredIds = array_intersect($groupId, $validGroupIds);
        
        if (!empty($filteredIds)) {
            $_SESSION['groupid'] = $filteredIds;
            $_SESSION['group_name'] = count($filteredIds) > 1 ? 'Filtered' : 
                $hostgroups[array_search($filteredIds[0], $validGroupIds)]['name'];
            $config['TRIGGER_SEARCH_PARAMS']['groupids'] = $filteredIds;
        }
    }
} elseif (isset($_SESSION['groupid'])) {
    $config['TRIGGER_SEARCH_PARAMS']['groupids'] = $_SESSION['groupid'];
}

$severity = validateInput('severity', 'int');
if ($severity !== null && isset($config['SEVERITIES'][$severity])) {
    $_SESSION['severity'] = $severity;
    $_SESSION['severity_name'] = $config['SEVERITIES'][$severity];
    $config['TRIGGER_SEARCH_PARAMS']['min_severity'] = $severity;
} elseif (isset($_SESSION['severity'])) {
    $config['TRIGGER_SEARCH_PARAMS']['min_severity'] = $_SESSION['severity'];
}

$hideAcked = validateInput('hide_acked', 'bool');
if ($hideAcked !== null) {
    $_SESSION['hide_acked'] = $hideAcked;
    $config['TRIGGER_SEARCH_PARAMS']['withLastEventUnacknowledged'] = $hideAcked;
} elseif (isset($_SESSION['hide_acked'])) {
    $config['TRIGGER_SEARCH_PARAMS']['withLastEventUnacknowledged'] = $_SESSION['hide_acked'];
}

$hideMaint = validateInput('hide_maint', 'bool');
if ($hideMaint !== null) {
    $_SESSION['hide_maint'] = $hideMaint;
    $config['TRIGGER_SEARCH_PARAMS']['maintenance'] = !$hideMaint;
} elseif (isset($_SESSION['hide_maint'])) {
    $config['TRIGGER_SEARCH_PARAMS']['maintenance'] = !$_SESSION['hide_maint'];
}

$wallboard = new Wallboard($config['SCRIPT_PATH'], $config['DISPLAY']);

$action = validateInput('action');
if ($action) {
    $csrfToken = validateInput('csrf_token');
    
    if (!in_array($action, ['login', 'details'], true) && !$wallboard->verifyCsrfToken($csrfToken)) {
        throw new Exception('Invalid CSRF token', 100);
    }

    switch ($action) {
        case 'details':
            $eventId = validateInput('eventid', 'int');
            if ($eventId) {
                $config['EVENT_SEARCH_PARAMS']['eventids'] = $eventId;
                $details = $backendZbx->getEventDetails($config['EVENT_SEARCH_PARAMS']);
                $wallboard->ajaxEventDetails($details);
            }
            break;

        case 'add_acknowledge':
            $eventId = validateInput('eventid', 'int');
            $ackMsg = validateInput('ack_msg');
            
            if ($eventId && $ackMsg && isset($_SESSION['username'])) {
                $backendZbx->addAcknowledge((string)$eventId, $ackMsg);
                header('Location: ' . $wallboard->generateScriptPath());
                exit;
            }
            break;

        case 'login':
            $username = validateInput('username');
            $password = $_POST['password'] ?? '';
            
            if ($username && $password) {
                $_SESSION['username'] = $username;
                $_SESSION['iv'] = random_bytes(16);
                $_SESSION['encryption_key'] = random_bytes(32);
                
                $encrypted = openssl_encrypt(
                    $password,
                    'aes-256-gcm',
                    $_SESSION['encryption_key'],
                    OPENSSL_RAW_DATA,
                    $_SESSION['iv'],
                    $tag
                );
                
                $_SESSION['encrypted_password'] = $encrypted;
                $_SESSION['tag'] = $tag;
                
                header('Location: ' . $wallboard->generateScriptPath());
                exit;
            }
            break;

        case 'logout':
            session_destroy();
            header('Location: ' . $wallboard->generateScriptPath());
            exit;

        default:
            throw new Exception('Unknown action', 100);
    }
} else {
    $triggers = $config['ZABBIX']['ENABLED'] 
        ? $backendZbx->getTriggers($config['TRIGGER_SEARCH_PARAMS']) 
        : [];
    
    $wallboard->generateMainContent($triggers);
}

$wallboard->generateMenu($hostgroups, $config['SEVERITIES']);
$wallboard->publish();
