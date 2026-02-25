<?php

declare(strict_types=1);

namespace App\Classes;

use Throwable;
use function defined;

readonly class ExceptionHandler
{
    private const ERROR_SESSION_RESET = 10;

    public function __construct(
        private string $scriptPath = '/index.php',
        private array $displayConfig = []
    ) {}

    public function error(Throwable $error): void
    {
        error_log(sprintf(
            "Error [%d]: %s\nTrace: %s",
            $error->getCode(),
            $error->getMessage(),
            $error->getTraceAsString()
        ));

        if ($error->getCode() === self::ERROR_SESSION_RESET) {
            $this->resetSession();
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
            exit(1);
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

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
