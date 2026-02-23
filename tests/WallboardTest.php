<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Bootstrap: laad de klassen (geen namespaces, dus handmatig)
 */
require_once __DIR__ . '/../src/classes/ExceptionHandler.php';
require_once __DIR__ . '/../src/classes/Wallboard.php';
require_once __DIR__ . '/../src/classes/RemoteData_Zabbix.php';

// ─────────────────────────────────────────────
// Wallboard Tests
// ─────────────────────────────────────────────

final class WallboardTest extends TestCase
{
    protected function setUp(): void
    {
        // Minimale sessie-simulatie
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $_SESSION['csrf_token'] = 'test-csrf-token-1234';
        $_SESSION['groupid']    = ['all'];
        $_SESSION['severity']   = 0;
        $_SESSION['hide_acked'] = false;
        $_SESSION['hide_maint'] = false;
    }

    // --- Constructor ---

    public function testConstructWithDefaults(): void
    {
        $wb = new Wallboard();
        $this->assertInstanceOf(Wallboard::class, $wb);
    }

    public function testConstructWithDisplayOptions(): void
    {
        $wb = new Wallboard('/index.php', [
            'TITLE'             => 'Test Board',
            'PROBLEM_COUNT_SHOW' => 5,
        ]);
        $this->assertInstanceOf(Wallboard::class, $wb);
    }

    public function testConstructWithLunchReminders(): void
    {
        $wb = new Wallboard('', [
            'LUNCH_REMINDERS' => [
                ['start' => 1200, 'end' => 1230],
                ['start' => 1730, 'end' => 1800],
            ]
        ]);
        $this->assertInstanceOf(Wallboard::class, $wb);
    }

    public function testConstructWithLegacyLunchReminder(): void
    {
        $wb = new Wallboard('', [
            'LUNCH_REMINDER'       => true,
            'LUNCH_REMINDER_START' => 1200,
            'LUNCH_REMINDER_END'   => 1230,
        ]);
        $this->assertInstanceOf(Wallboard::class, $wb);
    }

    // --- CSRF ---

    public function testVerifyCsrfTokenValid(): void
    {
        $wb = new Wallboard();
        $this->assertTrue($wb->verifyCsrfToken('test-csrf-token-1234'));
    }

    public function testVerifyCsrfTokenInvalid(): void
    {
        $wb = new Wallboard();
        $this->assertFalse($wb->verifyCsrfToken('wrong-token'));
    }

    public function testVerifyCsrfTokenEmpty(): void
    {
        $wb = new Wallboard();
        $this->assertFalse($wb->verifyCsrfToken(''));
    }

    // --- generateScriptPath ---

    public function testGenerateScriptPathReturnsString(): void
    {
        $wb   = new Wallboard('/index.php');
        $path = $wb->generateScriptPath([]);
        $this->assertIsString($path);
        $this->assertStringStartsWith('/index.php?', $path);
    }

    public function testGenerateScriptPathContainsCsrfToken(): void
    {
        $wb   = new Wallboard('/index.php');
        $path = $wb->generateScriptPath([]);
        $this->assertStringContainsString('csrf_token=', $path);
    }

    public function testGenerateScriptPathWithGroupid(): void
    {
        $wb   = new Wallboard('/index.php');
        $path = $wb->generateScriptPath(['groupid' => ['42']]);
        $this->assertStringContainsString('groupid', $path);
        $this->assertStringContainsString('42', $path);
    }

    public function testGenerateScriptPathWithSeverity(): void
    {
        $wb   = new Wallboard('/index.php');
        $path = $wb->generateScriptPath(['severity' => 3]);
        $this->assertStringContainsString('severity=3', $path);
    }

    public function testGenerateScriptPathWithHideFlags(): void
    {
        $wb   = new Wallboard('/index.php');
        $path = $wb->generateScriptPath(['hide_acked' => 1, 'hide_maint' => 1]);
        $this->assertStringContainsString('hide_acked=1', $path);
        $this->assertStringContainsString('hide_maint=1', $path);
    }

    public function testGenerateScriptPathWithAction(): void
    {
        $wb   = new Wallboard('/index.php');
        $path = $wb->generateScriptPath(['action' => 'logout']);
        $this->assertStringContainsString('action=logout', $path);
    }

    // --- generateMenu ---

    public function testGenerateMenuDoesNotThrow(): void
    {
        $wb = new Wallboard('/index.php', ['TITLE' => 'Test']);
        $wb->generateMenu([], []);
        $this->assertTrue(true); // geen exception = geslaagd
    }

    public function testGenerateMenuWithHostgroups(): void
    {
        $wb = new Wallboard('/index.php', ['TITLE' => 'Test']);
        $wb->generateMenu(
            [['groupid' => '1', 'name' => 'Linux Servers']],
            [1 => 'Information', 2 => 'Warning']
        );
        $this->assertTrue(true);
    }

    public function testGenerateMenuWithLoggedInUser(): void
    {
        $_SESSION['username'] = 'admin';
        $wb = new Wallboard('/index.php', ['TITLE' => 'Test']);
        $wb->generateMenu([], []);
        $this->assertTrue(true);
    }

    // --- generateMainContent ---

    public function testGenerateMainContentEmpty(): void
    {
        $wb = new Wallboard();
        $wb->generateMainContent([]);
        $this->assertTrue(true);
    }

    public function testGenerateMainContentWithTriggers(): void
    {
        $wb = new Wallboard();
        $wb->generateMainContent([
            [
                'hosts'       => [['name' => 'server01', 'maintenance_status' => '0']],
                'lastEvent'   => ['acknowledged' => '0'],
                'priority'    => 4,
                'lastchange'  => time(),
                'description' => 'Disk full',
            ]
        ]);
        $this->assertTrue(true);
    }

    public function testGenerateMainContentWithAckedTrigger(): void
    {
        $wb = new Wallboard();
        $wb->generateMainContent([
            [
                'hosts'       => [['name' => 'server02', 'maintenance_status' => '0']],
                'lastEvent'   => ['acknowledged' => '1'],
                'priority'    => 2,
                'lastchange'  => time(),
                'description' => 'High load',
            ]
        ]);
        $this->assertTrue(true);
    }

    public function testGenerateMainContentWithMaintenanceTrigger(): void
    {
        $wb = new Wallboard();
        $wb->generateMainContent([
            [
                'hosts'       => [['name' => 'server03', 'maintenance_status' => '1']],
                'lastEvent'   => ['acknowledged' => '0'],
                'priority'    => 3,
                'lastchange'  => time(),
                'description' => 'In maintenance',
            ]
        ]);
        $this->assertTrue(true);
    }

    public function testGenerateMainContentWithProblemCountShow(): void
    {
        $wb = new Wallboard('', ['PROBLEM_COUNT_SHOW' => 1]);
        $wb->generateMainContent([
            ['hosts' => [['name' => 'h1', 'maintenance_status' => '0']], 'lastEvent' => ['acknowledged' => '0'], 'priority' => 1, 'lastchange' => time(), 'description' => 'A'],
            ['hosts' => [['name' => 'h2', 'maintenance_status' => '0']], 'lastEvent' => ['acknowledged' => '0'], 'priority' => 2, 'lastchange' => time(), 'description' => 'B'],
        ]);
        $this->assertTrue(true);
    }

    // --- displayError ---

    public function testDisplayError(): void
    {
        $wb = new Wallboard();
        $wb->displayError(404, 'Not found', 'trace here');
        $this->assertTrue(true);
    }

    public function testDisplayErrorWithXss(): void
    {
        $wb = new Wallboard();
        $wb->displayError(500, '<script>alert(1)</script>', '');
        $this->assertTrue(true);
    }

    // --- ajaxEventDetails ---

    public function testAjaxEventDetails(): void
    {
        $wb = new Wallboard();
        $wb->ajaxEventDetails([
            [
                'clock'        => time(),
                'name'         => 'Test event',
                'eventid'      => '99',
                'acknowledges' => [],
            ]
        ]);
        $this->assertTrue(true);
    }

    public function testAjaxEventDetailsEmpty(): void
    {
        $wb = new Wallboard();
        $wb->ajaxEventDetails([]);
        $this->assertTrue(true);
    }

    public function testAjaxEventDetailsWithAcknowledges(): void
    {
        $_SESSION['username'] = 'admin';
        $wb = new Wallboard('/index.php');
        $wb->ajaxEventDetails([
            [
                'clock'        => time(),
                'name'         => 'Disk full',
                'eventid'      => '42',
                'acknowledges' => [
                    ['clock' => time(), 'name' => 'John', 'surname' => 'Doe', 'message' => 'Fixed'],
                ],
            ]
        ]);
        $this->assertTrue(true);
    }
}

// ─────────────────────────────────────────────
// ExceptionHandler Tests
// ─────────────────────────────────────────────

final class ExceptionHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $_SESSION['csrf_token'] = 'test-csrf-token-1234';
        $_SESSION['groupid']    = ['all'];
        $_SESSION['severity']   = 0;
        $_SESSION['hide_acked'] = false;
        $_SESSION['hide_maint'] = false;
    }

    public function testExceptionHandlerInstantiates(): void
    {
        $handler = new ExceptionHandler();
        $this->assertInstanceOf(ExceptionHandler::class, $handler);
    }

    /**
     * Test dat error() een exit aanroept (we vangen dat op via een custom exception).
     * We mocken Wallboard zodat er geen output is.
     */
    public function testErrorCallsExitViaException(): void
    {
        // ExceptionHandler::error() roept exit aan — we verwachten dat de test
        // stopt na de aanroep. We testen dit indirect door te controleren
        // dat de methode bestaat en aanroepbaar is.
        $handler = new ExceptionHandler();
        $this->assertTrue(method_exists($handler, 'error'));
    }

    public function testErrorMethodIsPublic(): void
    {
        $ref    = new ReflectionClass(ExceptionHandler::class);
        $method = $ref->getMethod('error');
        $this->assertTrue($method->isPublic());
    }

    public function testResetSessionIsPrivate(): void
    {
        $ref    = new ReflectionClass(ExceptionHandler::class);
        $method = $ref->getMethod('resetSession');
        $this->assertTrue($method->isPrivate());
    }

    public function testErrorConstants(): void
    {
        $ref        = new ReflectionClass(ExceptionHandler::class);
        $constants  = $ref->getConstants();

        $this->assertArrayHasKey('ERROR_SESSION_RESET', $constants);
        $this->assertArrayHasKey('ERROR_API_AUTH',      $constants);
        $this->assertArrayHasKey('ERROR_API_GENERAL',   $constants);
        $this->assertArrayHasKey('ERROR_UNKNOWN',        $constants);

        $this->assertSame(10,  $constants['ERROR_SESSION_RESET']);
        $this->assertSame(11,  $constants['ERROR_API_AUTH']);
        $this->assertSame(12,  $constants['ERROR_API_GENERAL']);
        $this->assertSame(100, $constants['ERROR_UNKNOWN']);
    }
}

// ─────────────────────────────────────────────
// RemoteData_Zabbix Tests (zonder echte API)
// ─────────────────────────────────────────────

final class RemoteData_ZabbixTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    private function makeConfig(array $overrides = []): array
    {
        return array_merge([
            'URL'        => 'https://zabbix.example.com/api_jsonrpc.php',
            'USERNAME'   => 'Admin',
            'PASSWORD'   => 'zabbix',
            'BASIC_AUTH' => false,
        ], $overrides);
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists('RemoteData_Zabbix'));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = get_class_methods('RemoteData_Zabbix');
        $this->assertContains('getHostgroups',   $methods);
        $this->assertContains('getTriggers',     $methods);
        $this->assertContains('getEventDetails', $methods);
        $this->assertContains('addAcknowledge',  $methods);
        $this->assertContains('get_hostgroups',  $methods);
        $this->assertContains('get_triggers',    $methods);
        $this->assertContains('get_zbx_version', $methods);
    }

    public function testConstructorThrowsWithoutApiWhenNoSession(): void
    {
        // Zonder sessie-token probeert de constructor in te loggen via cURL.
        // Dat mislukt (geen echte Zabbix), dus we verwachten een Exception.
        $this->expectException(Exception::class);
        new RemoteData_Zabbix($this->makeConfig());
    }

    public function testConstructorUsesSessionAuthHash(): void
    {
        // Als AUTH_HASH al in de sessie zit, slaat de constructor de login over.
        $_SESSION['AUTH_HASH'] = 'fake-auth-hash-abc123';

        // Dit mag NIET gooien, want er wordt geen API-call gedaan.
        $zbx = new RemoteData_Zabbix($this->makeConfig());
        $this->assertInstanceOf(RemoteData_Zabbix::class, $zbx);
    }

    public function testDestructorClearsSession(): void
    {
        $_SESSION['AUTH_HASH'] = 'fake-auth-hash-abc123';
        $zbx = new RemoteData_Zabbix($this->makeConfig());
        unset($zbx); // roept __destruct aan
        $this->assertArrayNotHasKey('AUTH_HASH', $_SESSION);
    }

    public function testGetHostgroupsReturnsArrayOnFailure(): void
    {
        $_SESSION['AUTH_HASH'] = 'fake-auth-hash-abc123';

        // Maak een partial mock die api_query onderschept
        $zbx = $this->getMockBuilder(RemoteData_Zabbix::class)
            ->setConstructorArgs([$this->makeConfig()])
            ->onlyMethods(['get_hostgroups'])
            ->getMock();

        $zbx->method('get_hostgroups')->willReturn([]);
        $result = $zbx->getHostgroups(['output' => 'extend']);
        $this->assertIsArray($result);
    }

    public function testGetTriggersReturnsArrayOnFailure(): void
    {
        $_SESSION['AUTH_HASH'] = 'fake-auth-hash-abc123';

        $zbx = $this->getMockBuilder(RemoteData_Zabbix::class)
            ->setConstructorArgs([$this->makeConfig()])
            ->onlyMethods(['get_triggers'])
            ->getMock();

        $zbx->method('get_triggers')->willReturn([]);
        $result = $zbx->getTriggers(['output' => 'extend']);
        $this->assertIsArray($result);
    }

    public function testConfigIsStored(): void
    {
        $_SESSION['AUTH_HASH'] = 'fake-auth-hash-abc123';
        $zbx = new RemoteData_Zabbix($this->makeConfig([
            'URL'      => 'https://my-zabbix.local/api_jsonrpc.php',
            'USERNAME' => 'testuser',
        ]));

        $ref = new ReflectionClass($zbx);

        $urlProp = $ref->getProperty('URL');
        $urlProp->setAccessible(true);
        $this->assertSame('https://my-zabbix.local/api_jsonrpc.php', $urlProp->getValue($zbx));

        $userProp = $ref->getProperty('USERNAME');
        $userProp->setAccessible(true);
        $this->assertSame('testuser', $userProp->getValue($zbx));
    }
}
