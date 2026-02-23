<?php

class ExceptionHandler
{
    private const ERROR_SESSION_RESET = 10;
    private const ERROR_API_AUTH = 11;
    private const ERROR_API_GENERAL = 12;
    private const ERROR_UNKNOWN = 100;

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

        $wallboard = new Wallboard();
        $wallboard->generateMenu([], []);
        $wallboard->displayError(
            $error->getCode(),
            $error->getMessage(),
            $error->getTraceAsString()
        );
        $wallboard->publish();

        exit;
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
