<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoverageBoosterTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        chdir(__DIR__ . '/../src');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testExceptionHandlerFullFlow(): void
    {
        require_once 'classes/Wallboard.php';
        require_once 'classes/ExceptionHandler.php';

        $handler = new ExceptionHandler();
        $handler->setConfig([
            'SCRIPT_PATH' => '/test.php',
            'DISPLAY' => ['theme' => 'dark']
        ]);

        ob_start();
        $handler->error(new Exception("Test Exception", 123));
        $output = ob_get_clean();

        $this->assertStringContainsString('Test Exception', $output);
        $this->assertStringContainsString('123', $output);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testIndexInputValidationDeep(): void
    {
        $_GET['a'] = '123';
        $_GET['b'] = 'not-an-int';
        $_GET['c'] = ['val1', 'val2'];
        $_GET['d'] = 'true';
        $_GET['e'] = 'false';
        $_GET['f'] = 'invalid-bool';

        ob_start();
        include 'index.php';
        set_exception_handler(null);
        ob_end_clean();

        $this->assertEquals(123, validateInput('a', 'int'));
        $this->assertNull(validateInput('b', 'int'));
        $this->assertEquals(['val1', 'val2'], validateInput('c', 'array'));
        $this->assertTrue(validateInput('d', 'bool'));
        $this->assertFalse(validateInput('e', 'bool'));
        $this->assertNull(validateInput('f', 'bool', null));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testLoginFlowCoverage(): void
    {
        $_POST['action'] = 'login';
        $_POST['username'] = 'admin';
        $_POST['password'] = 'zabbix';

        // We moeten headers mocken of negeren omdat we in CLI zitten
        ob_start();
        try {
            include 'index.php';
            set_exception_handler(null);
        } catch (\Exception $e) {}
        ob_end_clean();

        $this->assertEquals('admin', $_SESSION['username']);
        $this->assertArrayHasKey('encrypted_password', $_SESSION);
        $this->assertArrayHasKey('encryption_key', $_SESSION);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testLogoutFlowCoverage(): void
    {
        $_SESSION['username'] = 'admin';
        $_GET['action'] = 'logout';
        $_GET['csrf_token'] = 'mock_token';

        // Mock CSRF in session zodat validatie slaagt
        $_SESSION['csrf_token'] = 'mock_token';

        ob_start();
        try {
            include 'index.php';
            set_exception_handler(null);
        } catch (\Exception $e) {}
        ob_end_clean();

        $this->assertEmpty($_SESSION);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardCsrfLogic(): void
    {
        require_once 'classes/Wallboard.php';
        $wb = new Wallboard('/test', []);

        // Genereer token
        $token = $wb->generateScriptPath(); // Dit triggert token generatie in de achtergrond

        // We halen het token uit de sessie (die door Wallboard is gezet)
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        $this->assertTrue($wb->verifyCsrfToken($sessionToken));
        $this->assertFalse($wb->verifyCsrfToken('wrong_token'));
    }
}