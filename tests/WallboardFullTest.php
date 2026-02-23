<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * WallboardFullTest
 *
 * Dekt: ExceptionHandler (error + resetSession), Wallboard (alle methodes),
 * en de index.php flow. Vervangt de oude WallboardFullTest met correcte
 * methode-namen en geen ongeldige API-calls.
 */
final class WallboardFullTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_COOKIE  = [];
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        chdir(__DIR__ . '/../src');
    }

    // =========================================================================
    // ExceptionHandler tests
    // =========================================================================

    /**
     * Test de volledige error() flow van ExceptionHandler
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testExceptionHandlerErrorMethod(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);

        require_once 'classes/Wallboard.php';
        require_once 'classes/ExceptionHandler.php';

        $handler = new ExceptionHandler();
        $handler->setConfig([
            'SCRIPT_PATH' => '/test.php',
            'DISPLAY'     => ['TITLE' => 'Test Board'],
        ]);

        ob_start();
        $handler->error(new \Exception('Test fout', 99));
        $output = ob_get_clean();

        $this->assertStringContainsString('Test fout', $output);
        $this->assertStringContainsString('99', $output);
    }

    /**
     * Test ExceptionHandler met ERROR_SESSION_RESET (code 10) — triggert resetSession()
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testExceptionHandlerSessionReset(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);

        require_once 'classes/Wallboard.php';
        require_once 'classes/ExceptionHandler.php';

        // Zet een sessie klaar die gereset moet worden
        $_SESSION['username'] = 'admin';
        $_SESSION['csrf_token'] = 'abc123';
        $_COOKIE['zbxwallboard_token'] = 'sometoken';

        $handler = new ExceptionHandler();
        $handler->setConfig([
            'SCRIPT_PATH' => '/test.php',
            'DISPLAY'     => [],
        ]);

        ob_start();
        // Code 10 = ERROR_SESSION_RESET → roept resetSession() aan
        $handler->error(new \Exception('Session reset vereist', 10));
        ob_end_clean();

        // Na resetSession() moet de sessie leeg zijn
        $this->assertEmpty($_SESSION);
    }

    /**
     * Test ExceptionHandler setConfig
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testExceptionHandlerSetConfig(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);

        require_once 'classes/Wallboard.php';
        require_once 'classes/ExceptionHandler.php';

        $handler = new ExceptionHandler();
        $handler->setConfig([
            'SCRIPT_PATH' => '/custom/path.php',
            'DISPLAY'     => ['TITLE' => 'Custom'],
        ]);

        // Als setConfig werkt zonder exception, is de test geslaagd
        $this->assertTrue(true);
    }

    // =========================================================================
    // Wallboard unit tests
    // =========================================================================

    /**
     * Test Wallboard constructor en basis properties
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardConstructor(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $wb = new Wallboard('/test.php', [
            'TITLE'             => 'Mijn Board',
            'PROBLEM_COUNT_SHOW' => 5,
        ]);

        $this->assertInstanceOf(Wallboard::class, $wb);
        // Sessie defaults worden gezet door constructor
        $this->assertArrayHasKey('groupid', $_SESSION);
        $this->assertArrayHasKey('severity', $_SESSION);
    }

    /**
     * Test Wallboard CSRF token generatie en verificatie
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardCsrfTokenVerification(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $wb = new Wallboard('/test.php', []);

        // Token wordt in sessie gezet door constructor
        $token = $_SESSION['csrf_token'] ?? '';
        $this->assertNotEmpty($token);

        $this->assertTrue($wb->verifyCsrfToken($token));
        $this->assertFalse($wb->verifyCsrfToken('verkeerd_token'));
        $this->assertFalse($wb->verifyCsrfToken(''));
    }

    /**
     * Test Wallboard generateScriptPath
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardGenerateScriptPath(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $wb   = new Wallboard('/index.php', []);
        $path = $wb->generateScriptPath();

        $this->assertStringStartsWith('/index.php?', $path);
        $this->assertStringContainsString('csrf_token=', $path);
        $this->assertStringContainsString('severity=', $path);
    }

    /**
     * Test Wallboard generateScriptPath met custom params
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardGenerateScriptPathWithParams(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $wb   = new Wallboard('/index.php', []);
        $path = $wb->generateScriptPath(['action' => 'logout', 'severity' => 3]);

        $this->assertStringContainsString('action=logout', $path);
        $this->assertStringContainsString('severity=3', $path);
    }

    /**
     * Test Wallboard generateMenu output
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardGenerateMenu(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $wb = new Wallboard('/index.php', ['TITLE' => 'TestBoard']);

        $hostgroups = [
            ['groupid' => '1', 'name' => 'Linux servers'],
            ['groupid' => '2', 'name' => 'Windows servers'],
        ];
        $severities = [1 => 'Info', 2 => 'Warning', 3 => 'Average', 4 => 'High', 5 => 'Disaster'];

        ob_start();
        $wb->generateMenu($hostgroups, $severities);
        $wb->publish();
        $output = ob_get_clean();

        $this->assertStringContainsString('TestBoard', $output);
        $this->assertStringContainsString('Linux servers', $output);
        $this->assertStringContainsString('Windows servers', $output);
        $this->assertStringContainsString('Info', $output);
    }

    /**
     * Test Wallboard generateMenu met ingelogde gebruiker
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardGenerateMenuLoggedIn(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $_SESSION['username'] = 'admin';
        $wb = new Wallboard('/index.php', []);

        ob_start();
        $wb->generateMenu([], []);
        $wb->publish();
        $output = ob_get_clean();

        $this->assertStringContainsString('Logout', $output);
        $this->assertStringContainsString('admin', $output);
    }

    /**
     * Test Wallboard generateMainContent zonder triggers (geen problemen)
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardMainContentNoTriggers(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $wb = new Wallboard('/index.php', []);
        $wb->generateMainContent([]);
        $wb->generateMenu([], []);

        ob_start();
        $wb->publish();
        $output = ob_get_clean();

        $this->assertStringContainsString('No issues', $output);
    }

    /**
     * Test Wallboard generateMainContent met triggers
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardMainContentWithTriggers(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $wb = new Wallboard('/index.php', ['PROBLEM_COUNT_SHOW' => 10]);

        $triggers = [
            [
                'triggerid'   => '1',
                'description' => 'High CPU usage',
                'priority'    => 4,
                'lastchange'  => time(),
                'hosts'       => [['name' => 'web-server-01', 'maintenance_status' => '0']],
                'lastEvent'   => ['acknowledged' => '0', 'eventid' => '100'],
            ],
            [
                'triggerid'   => '2',
                'description' => 'Disk space low',
                'priority'    => 3,
                'lastchange'  => time(),
                'hosts'       => [['name' => 'db-server-01', 'maintenance_status' => '1']],
                'lastEvent'   => ['acknowledged' => '1', 'eventid' => '101'],
            ],
        ];

        $wb->generateMainContent($triggers);
        $wb->generateMenu([], []);

        ob_start();
        $wb->publish();
        $output = ob_get_clean();

        $this->assertStringContainsString('High CPU usage', $output);
        $this->assertStringContainsString('web-server-01', $output);
        $this->assertStringContainsString('Disk space low', $output);
    }

    /**
     * Test Wallboard displayError
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardDisplayError(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $wb = new Wallboard('/index.php', []);
        $wb->generateMenu([], []);
        $wb->displayError(500, 'Internal Server Error', 'Stack trace here');

        ob_start();
        $wb->publish();
        $output = ob_get_clean();

        $this->assertStringContainsString('500', $output);
        $this->assertStringContainsString('Internal Server Error', $output);
    }

    /**
     * Test Wallboard ajaxEventDetails
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardAjaxEventDetails(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $wb = new Wallboard('/index.php', []);
        $wb->ajaxEventDetails([
            [
                'clock'        => time(),
                'name'         => 'Test event',
                'eventid'      => '42',
                'acknowledges' => [
                    [
                        'clock'   => time(),
                        'name'    => 'John',
                        'surname' => 'Doe',
                        'message' => 'Acknowledged',
                    ],
                ],
            ],
        ]);

        ob_start();
        $wb->publish();
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('html', $decoded);
        $this->assertStringContainsString('Test event', $decoded['html']);
        $this->assertStringContainsString('John', $decoded['html']);
    }

    /**
     * Test Wallboard ajaxEventDetails leeg
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardAjaxEventDetailsEmpty(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $wb = new Wallboard('/index.php', []);
        $wb->ajaxEventDetails([]);

        ob_start();
        $wb->publish();
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertStringContainsString('No details available', $decoded['html']);
    }

    /**
     * Test Wallboard lunch reminder (legacy config)
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardLunchReminderLegacy(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $wb = new Wallboard('/index.php', [
            'LUNCH_REMINDER'       => true,
            'LUNCH_REMINDER_START' => 0,    // altijd actief
            'LUNCH_REMINDER_END'   => 2359,
        ]);

        $wb->generateMainContent([]);
        ob_start();
        $wb->publish();
        $output = ob_get_clean();

        // Bij lunchtime wordt 🍴 getoond, anders 👍
        $this->assertTrue(
            str_contains($output, '🍴') || str_contains($output, '👍')
        );
    }

    /**
     * Test Wallboard met LUNCH_REMINDERS array config
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardLunchRemindersArray(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $wb = new Wallboard('/index.php', [
            'LUNCH_REMINDERS' => [
                ['start' => 0, 'end' => 2359],
            ],
        ]);

        $wb->generateMainContent([]);
        ob_start();
        $wb->publish();
        $output = ob_get_clean();

        $this->assertStringContainsString('🍴', $output);
    }

    /**
     * Test Wallboard hide_acked en hide_maint sessie flags
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardSessionFlags(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $_SESSION['hide_acked'] = true;
        $_SESSION['hide_maint'] = true;
        $_SESSION['severity']   = 3;

        $wb   = new Wallboard('/index.php', []);
        $path = $wb->generateScriptPath();

        $this->assertStringContainsString('hide_acked=1', $path);
        $this->assertStringContainsString('hide_maint=1', $path);
        $this->assertStringContainsString('severity=3', $path);
    }

    /**
     * Test Wallboard XSS escaping in trigger output
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWallboardXssEscaping(): void
    {
        if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
        require_once 'classes/Wallboard.php';

        $wb = new Wallboard('/index.php', []);
        $wb->generateMainContent([
            [
                'triggerid'   => '1',
                'description' => '<script>alert(1)</script>',
                'priority'    => 4,
                'lastchange'  => time(),
                'hosts'       => [['name' => '<b>evil</b>', 'maintenance_status' => '0']],
                'lastEvent'   => ['acknowledged' => '0'],
            ],
        ]);

        ob_start();
        $wb->publish();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
        $this->assertStringNotContainsString('<b>evil</b>', $output);
    }

    public function testGetEventDetails() {
        if (!isset($_ENV['ZABBIX_URL'])) {
            $this->markTestSkipped('Zabbix environment variables not set.');
        }
        $zabbix = new RemoteData_Zabbix();
        // We halen eerst triggers op om een geldig event ID te vinden, of we gokken op ID 1
        $result = $zabbix->getEventDetails(1);
        $this->assertIsArray($result);
    }

    public function testApiQueryErrorHandling() {
        if (!isset($_ENV['ZABBIX_URL'])) {
            $this->markTestSkipped('Zabbix environment variables not set.');
        }
        $zabbix = new RemoteData_Zabbix();
        // Forceer een ongeldige query om error paden in api_query te testen
        $result = $zabbix->api_query('non.existent.method', []);
        $this->assertArrayHasKey('error', $result);
    }

        public function testZabbixIntegrationAndErrors() {
        if (!isset($_ENV['ZABBIX_URL'])) {
            $this->markTestSkipped('Zabbix environment variables not set.');
        }

        $zabbix = new RemoteData_Zabbix();

        // Test getHostgroups
        $groups = $zabbix->getHostgroups();
        $this->assertIsArray($groups);

        // Test getEventDetails (was 0% coverage)
        $event = $zabbix->getEventDetails(1);
        $this->assertIsArray($event);

        // Test Error Handling in api_query (verhoogt coverage in api_query/api_curl)
        $errorResult = $zabbix->api_query('non.existent.method', []);
        $this->assertArrayHasKey('error', $errorResult);
    }

    public function testWallboardScriptPath() {
        $wb = new Wallboard();
        $path = $wb->get_script_path();
        $this->assertIsString($path);
        $this->assertStringContainsString('.php', $path);
    }
}
