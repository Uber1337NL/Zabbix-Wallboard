<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

/**
 * Start secure session
 */
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
 * Validate input helper
 * - 'array' returns raw values (no intval) to preserve sentinel 'all'
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
            // FILTER_VALIDATE_BOOLEAN returns true/false; if value not present returns false
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
        default:
            return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $default;
    }
}

/**
 * If user previously logged in via session, decrypt stored password into config
 */
if (isset($_SESSION['encrypted_password'], $_SESSION['username'], $_SESSION['encryption_key'], $_SESSION['iv'], $_SESSION['tag'])) {
    $config['ZABBIX']['USERNAME'] = $_SESSION['username'];
    $decrypted = openssl_decrypt(
        $_SESSION['encrypted_password'],
        'aes-256-gcm',
        $_SESSION['encryption_key'],
        OPENSSL_RAW_DATA,
        $_SESSION['iv'],
        $_SESSION['tag']
    );
    if ($decrypted !== false) {
        $config['ZABBIX']['PASSWORD'] = $decrypted;
    }
}

// Initialize backend and get hostgroups
$backendZbx = new RemoteData_Zabbix($config['ZABBIX']);
$hostgroups = $backendZbx->getHostgroups($config['HOSTGROUP_SEARCH_PARAMS']);

// --- Hostgroup multi-select handling (preserve 'all' sentinel) ---
$groupIdRaw = validateInput('groupid', 'array');
if ($groupIdRaw !== null) {
    // If user chose the sentinel 'all' clear session filters
    if (in_array('all', $groupIdRaw, true)) {
        unset($_SESSION['groupid'], $_SESSION['group_name']);
    } else {
        // Compare as strings to be safe
        $validGroupIds = array_map('strval', array_column($hostgroups, 'groupid'));
        $groupIdStrs = array_map('strval', $groupIdRaw);
        $filteredIds = array_values(array_intersect($groupIdStrs, $validGroupIds));

        if (!empty($filteredIds)) {
            $_SESSION['groupid'] = $filteredIds;
            $_SESSION['group_name'] = count($filteredIds) > 1
                ? 'Filtered'
                : ($hostgroups[array_search($filteredIds[0], $validGroupIds)]['name'] ?? 'Filtered');
        } else {
            // if nothing valid matched, clear session selection
            unset($_SESSION['groupid'], $_SESSION['group_name']);
        }
    }
}

// If session has groupid, ensure TRIGGER_SEARCH_PARAMS is set
if (isset($_SESSION['groupid'])) {
    $config['TRIGGER_SEARCH_PARAMS']['groupids'] = $_SESSION['groupid'];
}

// --- Severity filter ---
$severity = validateInput('severity', 'int');
if ($severity !== null) {
    $_SESSION['severity'] = $severity;
    $config['TRIGGER_SEARCH_PARAMS']['min_severity'] = $severity;
} elseif (isset($_SESSION['severity'])) {
    $config['TRIGGER_SEARCH_PARAMS']['min_severity'] = $_SESSION['severity'];
}

// --- hide acknowledged ---
$hideAcked = validateInput('hide_acked', 'bool');
if ($hideAcked !== null) {
    $_SESSION['hide_acked'] = (bool)$hideAcked;
}
if (isset($_SESSION['hide_acked'])) {
    // Zabbix param expects boolean-like for withLastEventUnacknowledged
    $config['TRIGGER_SEARCH_PARAMS']['withLastEventUnacknowledged'] = $_SESSION['hide_acked'];
}

// --- hide maintenance ---
$hideMaint = validateInput('hide_maint', 'bool');
if ($hideMaint !== null) {
    $_SESSION['hide_maint'] = (bool)$hideMaint;
}
if (isset($_SESSION['hide_maint'])) {
    // We invert because config param 'maintenance' may represent "include in maintenance"
    $config['TRIGGER_SEARCH_PARAMS']['maintenance'] = !$_SESSION['hide_maint'];
}

// Instantiate Wallboard renderer
$wallboard = new Wallboard($config['SCRIPT_PATH'], $config['DISPLAY']);

// Handle actions (login/logout). We intentionally do NOT handle 'details' or ack actions here.
$action = validateInput('action');
if ($action) {
    $csrfToken = (string)validateInput('csrf_token');

    // Skip CSRF validation only for login (user does not yet have a token)
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
            // Make sure session is destroyed and redirect to clean wallboard URL
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            header('Location: ' . $wallboard->generateScriptPath());
            exit;

        default:
            // Unknown actions are ignored for the wallboard (keep a stable read-only display)
            header('Location: ' . $wallboard->generateScriptPath());
            exit;
    }
} else {
    // No action: fetch triggers and generate the main content
    $triggers = $backendZbx->getTriggers($config['TRIGGER_SEARCH_PARAMS']);
    $wallboard->generateMainContent($triggers);
}

// Always generate menu and publish (menu uses $hostgroups)
$wallboard->generateMenu($hostgroups, $config['SEVERITIES']);
$wallboard->publish();
