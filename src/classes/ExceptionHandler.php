<?php

declare(strict_types=1);

class ExceptionHandler
{
    private const int ERROR_SESSION_RESET = 10;
    private const int ERROR_API_AUTH = 11;
    private const int ERROR_API_GENERAL = 12;
    private const int ERROR_UNKNOWN = 100;

    private string $scriptPath = '/index.php';
    private array $displayConfig = [];

    public function setConfig(array $config): void
    {
        $this->scriptPath = $config['SCRIPT_PATH'] ?? '/index.php';
        $this->displayConfig = $config['DISPLAY'] ?? [];
    }

    public function error(Throwable $error): void
    {
        error_log(sprintf(
            "Error [%d]: %s\nTrace: %s",
            $error->getCode(),
            $error->getMessage(),
            $error->getTraceAsString()
        ));

        switch ($error->getCode()) {
            case self::ERROR_SESSION_RESET:
                $this->resetSession();
                break;
        }

        $wallboard = new Wallboard($this->scriptPath, $this->displayConfig);
        $wallboard->generateMenu([], []);
        $wallboard->displayError(
            $error->getCode(),
            $error->getMessage(),
            $error->getTraceAsString()
        );
        $wallboard->publish();

        if (!defined('PHPUNIT_RUNNING') || !PHPUNIT_RUNNING) {
            exit;
        }
    }

    private function resetSession(): void
    {
        if (isset($_COOKIE['zbxwallboard_token'])) {
            setcookie('zbxwallboard_token', '', time() - 3600, '/', '', true, true);
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
