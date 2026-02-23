<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integratie test die verbinding maakt met de echte Zabbix server
 * zoals geconfigureerd in src/config.php
 */
require_once __DIR__ . '/../src/classes/ExceptionHandler.php';
require_once __DIR__ . '/../src/classes/Wallboard.php';
require_once __DIR__ . '/../src/classes/RemoteData_Zabbix.php';

final class ZabbixIntegrationTest extends TestCase
{
    private array $config;
    private ?RemoteData_Zabbix $zbx = null;

    protected function setUp(): void
    {
        // Laad de echte config.php
        $this->config = require __DIR__ . '/../src/config.php';

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Forceer nieuwe login
        unset($_SESSION['AUTH_HASH']);

        try {
            $this->zbx = new RemoteData_Zabbix($this->config['ZABBIX']);
        } catch (Exception $e) {
            $this->markTestSkipped('Skipping: Kan geen verbinding maken met Zabbix API. Controleer src/config.php of omgevingsvariabelen. Error: ' . $e->getMessage());
        }
    }

    public function testZabbixConnectionAndVersion(): void
    {
        $version = $this->zbx->get_zbx_version();
        $this->assertIsArray($version);
        $this->assertGreaterThanOrEqual(1, (int)$version[0], 'Zabbix versie moet minimaal 1.x zijn');
        fwrite(STDOUT, "\n[INFO] Verbonden met Zabbix versie: " . implode('.', $version) . "\n");
    }

    public function testGetHostgroupsRealData(): void
    {
        $groups = $this->zbx->getHostgroups($this->config['HOSTGROUP_SEARCH_PARAMS']);
        $this->assertIsArray($groups);
        fwrite(STDOUT, "[INFO] " . count($groups) . " hostgroepen opgehaald.\n");
    }

    public function testGetTriggersAndGenerateHtml(): void
    {
        $triggers = $this->zbx->getTriggers($this->config['TRIGGER_SEARCH_PARAMS']);
        $this->assertIsArray($triggers);

        $wb = new Wallboard('/index.php', $this->config['DISPLAY']);
        $wb->generateMainContent($triggers);

        // We checken of er content is gegenereerd (geen lege string)
        $ref = new ReflectionClass($wb);
        $prop = $ref->getProperty('mainContent');
        $prop->setAccessible(true);
        $html = $prop->getValue($wb);

        $this->assertStringContainsString('id="main-content"', $html);
        fwrite(STDOUT, "[INFO] Wallboard HTML succesvol gegenereerd met " . count($triggers) . " triggers.\n");
    }
}
