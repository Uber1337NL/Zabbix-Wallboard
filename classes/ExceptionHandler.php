<?php

class ExceptionHandler
{
    private const ERROR_SESSION_RESET = 10;
    // ... (andere constants blijven gelijk)

    public function error(Throwable $error): void
    {
        // Log de error altijd naar de systeemlog
        error_log(sprintf(
            "Wallboard Error [%d]: %s in %s:%d",
            $error->getCode(),
            $error->getMessage(),
            $error->getFile(),
            $error->getLine()
        ));

        if ($error->getCode() === self::ERROR_SESSION_RESET) {
            $this->resetSession();
        }

        // Gebruik lege waarden als fallback voor de constructor
        // zodat de error pagina altijd kan renderen
        $wallboard = new Wallboard('', []); 
        
        // Genereer een minimaal menu (leeg)
        $wallboard->generateMenu([], []);
        
        $wallboard->displayError(
            (int)$error->getCode(),
            $error->getMessage(),
            $error->getTraceAsString()
        );
        
        $wallboard->publish();
        exit;
    }

    private function resetSession(): void
    {
        // Start sessie alleen als die nog niet bestaat, om destroy te kunnen aanroepen
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
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
