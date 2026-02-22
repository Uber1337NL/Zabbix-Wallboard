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

require_once 'classes/RemoteData_Zabbix.php';
require_once 'classes/Wallboard.php';
require_once 'classes/ExceptionHandler.php';

$config = require 'config.php';
$config['SCRIPT_PATH'] = ($config['REVERSE_PROXY_PATH'] ?? '') . ($_SERVER['SCRIPT_NAME'] ?? '');

$exceptionHandler = new ExceptionHandler();
set_exception_handler([$exceptionHandler, 'error']);

/**
 * Validates input from GET or POST.
 * Note: 'array' type now returns raw values to preserve 'all' string.
 */
function validateInput(string $key, string $type = 'string', $default = null)
{
    $value = $_GET[$key] ?? $_POST[$key] ?? null;
    if ($value === null) return $default;

    switch ($type) {
        case 'int':
            return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int)$value : $default;
        case 'array':
            return is_array($value) ? $value : [$value];
        case 'bool':
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        default:
            return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $default;
    }
}

// Handle Session-based Zabbix Authentication
if (isset($_SESSION['encrypted_password'], $_SESSION['username'], $_SESSION['encryption_key'])) {
    $config['ZABBIX']['USERNAME'] = $_SESSION['username'];
    $config['ZABBIX']['PASSWORD'] = openssl_decrypt(
        $_SESSION['encrypted_password'],
        'aes-256-gcm',
        $_SESSION['encryption_key'],
        OPENSSL_RAW_DATA,
        $_SESSION['iv'],
        $_SESSION['tag']
    );
}

// Initialize Backend and Fetch Hostgroups
$backendZbx = new RemoteData_Zabbix($config['ZABBIX']);
$hostgroups = $backendZbx->getHostgroups($config['HOSTGROUP_SEARCH_PARAMS']);

// --- Filter Logic: Hostgroups (Multi-select) ---
$groupIdRaw = validateInput('groupid', 'array');
if ($groupIdRaw !== null) {
    if (in_array('all', $groupIdRaw, true)) {
        unset($_SESSION['groupid'], $_SESSION['group_name']);
    } else {
        $validGroupIds = array_column($hostgroups, 'groupid');
        $filteredIds = array_values(array_intersect(array_map('strval', $groupIdRaw), $validGroupIds));

        if (!empty($filteredIds)) {
            $_SESSION['groupid'] = $filteredIds;
            $_SESSION['group_name'] = count($filteredIds) > 1 ? 'Filtered' :
                ($hostgroups[array_search($filteredIds[0], $validGroupIds)]['name'] ?? 'Filtered');
        } else {
            unset($_SESSION['groupid'], $_SESSION['group_name']);
        }
    }
}

if (isset($_SESSION['groupid'])) {
    $config['TRIGGER_SEARCH_PARAMS']['groupids'] = $_SESSION['groupid'];
}

// --- Filter Logic: Severity ---
$severity = validateInput('severity', 'int');
if ($severity !== null) {
    $_SESSION['severity'] = $severity;
    $config['TRIGGER_SEARCH_PARAMS']['min_severity'] = $severity;
} elseif (isset($_SESSION['severity'])) {
    $config['TRIGGER_SEARCH_PARAMS']['min_severity'] = $_SESSION['severity'];
}

// --- Filter Logic: Acknowledged ---
$hideAcked = validateInput('hide_acked', 'bool');
if ($hideAcked !== null) {
    $_SESSION['hide_acked'] = $hideAcked;
}
if (isset($_SESSION['hide_acked'])) {
    $config['TRIGGER_SEARCH_PARAMS']['withLastEventUnacknowledged'] = $_SESSION['hide_acked'];
}

// --- Filter Logic: Maintenance ---
$hideMaint = validateInput('hide_maint', 'bool');
if ($hideMaint !== null) {
    $_SESSION['hide_maint'] = $hideMaint;
}
if (isset($_SESSION['hide_maint'])) {
    $config['TRIGGER_SEARCH_PARAMS']['maintenance'] = !$_SESSION['hide_maint'];
}

$wallboard = new Wallboard($config['SCRIPT_PATH'], $config['DISPLAY']);

$action = validateInput('action');
if ($action) {
    $csrfToken = (string)validateInput('csrf_token');

    // CSRF check: Alleen overslaan voor 'login' (omdat je dan nog geen sessie/token hebt soms)
    // of 'details' (als die nog aangeroepen zou worden).
    if (!in_array($action, ['login'], true) && !$wallboard->verifyCsrfToken($csrfToken)) {
        throw new Exception('Invalid CSRF token', 100);
    }

    switch ($action) {
        case 'login':
            $username = validateInput('username');
            $password = $_POST['password'] ?? '';
            if ($username && $password) {
                $_SESSION['username'] = $username;
                $_SESSION['iv'] = random_bytes(16);
                $_SESSION['encryption_key'] = random_bytes(32);
                $encrypted = openssl_encrypt($password, 'aes-256-gcm', $_SESSION['encryption_key'], OPENSSL_RAW_DATA, $_SESSION['iv'], $tag);
                $_SESSION['encrypted_password'] = $encrypted;
                $_SESSION['tag'] = $tag;
                header('Location: ' . $wallboard->generateScriptPath());
                exit;
            }
            break;

        case 'logout':
            session_destroy();
            // Na logout direct naar de schone URL zonder actie-parameters
            header('Location: index.php'); 
            exit;

        // 'details' en 'add_acknowledge' zijn hier nu verwijderd, 
        // dus die doen niets meer op de server.
        
        default:
            // Optioneel: negeer onbekende acties ipv een error te gooien op een wallboard
            header('Location: index.php');
            exit;
    }
}

$wallboard->generateMenu($hostgroups, $config['SEVERITIES']);
$wallboard->publish();
