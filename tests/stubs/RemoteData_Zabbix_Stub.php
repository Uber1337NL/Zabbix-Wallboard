<?php
// Stub voor IndexTest — vervangt de echte RemoteData_Zabbix zonder API calls.
// Alleen geladen in @runInSeparateProcess tests, zodat er geen conflict is.
if (!class_exists('RemoteData_Zabbix', false)) {
    class RemoteData_Zabbix {
        public function __construct(array $c) {}
        public function getHostgroups(array $p): array { return []; }
        public function getTriggers(array $p): array { return []; }
    }
}
