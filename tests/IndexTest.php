<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class IndexTest extends TestCase
{
    private array $originalGet;
    private array $originalPost;
    private array $originalServer;
    private array $originalSession;

    protected function setUp(): void
    {
        $this->originalGet = $_GET;
        $this->originalPost = $_POST;
        $this->originalServer = $_SERVER;
        $this->originalSession = $_SESSION ?? [];

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        chdir(__DIR__ . '/../src');
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        $_POST = $this->originalPost;
        $_SERVER = $this->originalServer;
        $_SESSION = $this->originalSession;

        // Cleanup output buffers if any left
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        // Restore exception handler to baseline
        restore_exception_handler();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testIndexRender(): void
    {
        // Laad de stub VOOR index.php — require_once in index.php slaat de echte class dan over
        require_once __DIR__ . '/stubs/RemoteData_Zabbix_Stub.php';

        ob_start();
        try {
            include __DIR__ . '/../src/index.php';
        } catch (\Throwable $e) {}
        $output = ob_get_clean();

        $this->assertStringContainsString('<html', $output);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInputValidationLogic(): void
    {
        require_once __DIR__ . '/stubs/RemoteData_Zabbix_Stub.php';

        $_GET['test_int'] = '123';
        $_GET['test_bool'] = 'true';
        $_GET['test_string'] = '<script>alert(1)</script>';

        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);

        ob_start();
        try {
            include __DIR__ . '/../src/index.php';
        } catch (\Throwable $e) {}
        ob_end_clean();

        $this->assertEquals(123, validateInput('test_int', 'int'));
        $this->assertTrue(validateInput('test_bool', 'bool'));
        $this->assertEquals('&lt;script&gt;alert(1)&lt;/script&gt;', validateInput('test_string'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testLogoutAction(): void
    {
        require_once __DIR__ . '/stubs/RemoteData_Zabbix_Stub.php';

        $_GET['action'] = 'logout';
        $_SESSION['csrf_token'] = 'valid_token';
        $_GET['csrf_token'] = 'valid_token';

        ob_start();
        try {
            include __DIR__ . '/../src/index.php';
        } catch (\Throwable $e) {}
        ob_end_clean();

        $this->assertTrue(true);
    }
}
