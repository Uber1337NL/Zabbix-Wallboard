<?php

declare(strict_types=1);

use App\Classes\RemoteData_Zabbix;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/RemoteData_Zabbix.php';

/**
 * Client-side trigger sorting (replaces server-side sortfield, which breaks
 * Zabbix 8.0 + PostgreSQL with "for SELECT DISTINCT, ORDER BY expressions must appear in select list").
 */
final class TriggerSortTest extends TestCase
{
    public function testSortsByLastchangeDescNumerically(): void
    {
        $rows = [
            ['triggerid' => '1', 'lastchange' => '100'],
            ['triggerid' => '2', 'lastchange' => '300'],
            ['triggerid' => '3', 'lastchange' => '1000000000'],
        ];
        $sorted = RemoteData_Zabbix::sortRecords($rows, ['lastchange'], ['DESC']);
        $this->assertSame(['3', '2', '1'], array_column($sorted, 'triggerid'));
    }

    public function testDefaultsToAscAndIsStableOnTies(): void
    {
        $rows = [
            ['id' => 'a', 'lastchange' => '5'],
            ['id' => 'b', 'lastchange' => '1'],
            ['id' => 'c', 'lastchange' => '5'],
        ];
        $sorted = RemoteData_Zabbix::sortRecords($rows, ['lastchange']);
        $this->assertSame(['b', 'a', 'c'], array_column($sorted, 'id'));
    }

    public function testNoSortFieldsOrEmptyInputReturnsUnchanged(): void
    {
        $rows = [['id' => 2], ['id' => 1]];
        $this->assertSame($rows, RemoteData_Zabbix::sortRecords($rows, []));
        $this->assertSame([], RemoteData_Zabbix::sortRecords([], ['lastchange'], ['DESC']));
    }

    public function testMultiFieldSort(): void
    {
        $rows = [
            ['p' => 1, 'n' => 'x'],
            ['p' => 1, 'n' => 'z'],
            ['p' => 2, 'n' => 'y'],
        ];
        $sorted = RemoteData_Zabbix::sortRecords($rows, ['p', 'n'], ['DESC', 'ASC']);
        $this->assertSame(['y', 'x', 'z'], array_column($sorted, 'n'));
    }
}
