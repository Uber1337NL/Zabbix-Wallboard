<?php

class Wallboard
{
    private const SEVERITY_COLORS = [
        0 => 'text-shadow',
        1 => 'fg-white bg-emerald text-shadow',
        2 => 'fg-white bg-amber text-shadow',
        3 => 'fg-white bg-orange text-shadow',
        4 => 'fg-white bg-red text-shadow',
        5 => 'fg-white bg-darkMagenta text-shadow',
    ];

    private readonly string $title;
    private readonly int $problemCountShow;
    private readonly int $ajaxRefreshInterval;
    private readonly array $lunchReminders;
    private readonly string $csrfToken;

    private string $menu        = '';
    private string $mainContent = '';
    private bool   $isAjaxRequest = false;
    private string $ajaxOutput  = '';

    public function __construct(
        private readonly string $scriptPath = '',
        array $display = []
    ) {
        $this->title               = $display['TITLE'] ?? 'ZbxWallboard';
        $this->problemCountShow    = $display['PROBLEM_COUNT_SHOW'] ?? 0;
        $this->ajaxRefreshInterval = $display['AJAX_REFRESH_INTERVAL'] ?? 15000;

        $reminders = [];
        foreach ($display['LUNCH_REMINDERS'] ?? [] as $period) {
            if (isset($period['start'], $period['end'])) {
                $reminders[] = [
                    'start' => (int) $period['start'],
                    'end'   => (int) $period['end'],
                ];
            }
        }
        $this->lunchReminders = $reminders;

        $this->csrfToken = $_SESSION['csrf_token'] ?? $this->generateCsrfToken();

        $_SESSION['groupid'] = match (true) {
            !isset($_SESSION['groupid'])    => ['all'],
            !is_array($_SESSION['groupid']) => [(string) $_SESSION['groupid']],
            default                         => $_SESSION['groupid'],
        };

        $_SESSION['severity']   ??= 0;
        $_SESSION['hide_acked'] ??= false;
        $_SESSION['hide_maint'] ??= false;
    }

    private function generateCsrfToken(): string
    {
        return $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public function verifyCsrfToken(string $token): bool
    {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function getSeverityColor(int $severity): string
    {
        return self::SEVERITY_COLORS[$severity] ?? '';
    }

    public function generateScriptPath(array $reqParams = []): string
    {
        $params = [
            'groupid'    => $reqParams['groupid']    ?? $_SESSION['groupid']    ?? ['all'],
            'severity'   => $reqParams['severity']   ?? $_SESSION['severity']   ?? 0,
            'hide_acked' => (int) ($reqParams['hide_acked'] ?? $_SESSION['hide_acked'] ?? 0),
            'hide_maint' => (int) ($reqParams['hide_maint'] ?? $_SESSION['hide_maint'] ?? 0),
            'csrf_token' => $this->csrfToken,
        ];

        return "{$this->scriptPath}?" . http_build_query($params);
    }

    public function generateMainContent(array $triggers): void
    {
        $this->mainContent = '<div id="main-content">'
            . $this->generateTiles($triggers)
            . '</div>';
    }

    public function ajaxMainContent(array $triggers): void
    {
        $this->isAjaxRequest = true;
        $this->ajaxOutput    = $this->generateTiles($triggers);
    }

    private function generateTiles(array $triggers): string
    {
        $output = '<div id="wallboard-grid">';

        if (empty($triggers)) {
            $icon = $this->isLunchTime() ? '🍴' : '👍';
            return $output . sprintf(
                '<div class="no-issues-panel"><div style="font-size: 15vh;">%s</div><p>No issues! Good Job!</p></div></div>',
                $icon
            );
        }

        $slice = $this->problemCountShow > 0
            ? array_slice($triggers, 0, $this->problemCountShow)
            : $triggers;

        foreach ($slice as $trigger) {
            if (!is_array($trigger))
                continue;

            $hosts     = $trigger['hosts']     ?? [];
            $lastEvent = $trigger['lastEvent'] ?? [];

            $isMaint = in_array('1', array_column($hosts, 'maintenance_status'), true);
            $isAck   = ($lastEvent['acknowledged'] ?? '0') === '1';
            $color   = ($isMaint || $isAck) ? '' : $this->getSeverityColor((int) $trigger['priority']);

            $output .= sprintf('<div class="tile-wide %s shadow"><div class="tile-content">', $color);
            $output .= sprintf(
                '<p class="align-center text-date">%s</p>',
                date('Y-m-d H:i:s', $trigger['lastchange'] ?? time())
            );
            $output .= sprintf('<p class="align-center text-accent">%s</p>', $this->escape($hosts[0]['name'] ?? 'N/A'));
            $output .= sprintf('<p class="align-center text-default">%s</p>', $this->escape($trigger['description'] ?? ''));

            if ($isMaint || $isAck) {
                $badges = ($isMaint ? '🔧 ' : '') . ($isAck ? '✅' : '');
                $output .= sprintf('<span class="tile-badge bg-emerald">%s</span>', $badges);
            }

            $output .= '</div></div>';
        }

        return $output . '</div>';
    }

    private function generateHostgroupMenu(array $hostgroups): string
    {
        $selectedIds = array_map('strval', $_SESSION['groupid'] ?? ['all']);

        $label = in_array('all', $selectedIds, true)
            ? 'All Hosts'
            : count($selectedIds) . ' Selected';

        $menu = sprintf('<li><a href="#" class="dropdown-toggle">%s</a><ul class="d-menu">', $this->escape($label));
        $menu .= sprintf(
            '<li><a href="%s" style="border-bottom:1px solid #eee; font-weight:bold;">❌ Clear Filters</a></li>',
            $this->escape($this->generateScriptPath(['groupid' => ['all']]))
        );

        foreach ($hostgroups as $group) {
            $gid      = (string) ($group['groupid'] ?? '');
            $isActive = in_array($gid, $selectedIds, true);

            $newSelection = array_values(array_diff($selectedIds, ['all']));
            if ($isActive) {
                $newSelection = array_values(array_diff($newSelection, [$gid]));
            } else {
                $newSelection[] = $gid;
            }
            if (empty($newSelection))
                $newSelection = ['all'];

            $menu .= sprintf(
                '<li><a href="%s" %s>%s %s</a></li>',
                $this->escape($this->generateScriptPath(['groupid' => $newSelection])),
                $isActive ? 'class="active-selection"' : '',
                $isActive ? '✅' : '▫️',
                $this->escape($group['name'] ?? '')
            );
        }

        return $menu . '</ul></li>';
    }

    private function generateSeverityMenu(array $severities): string
    {
        $currentSeverity = (string) ($_SESSION['severity'] ?? '0');
        $label = ($currentSeverity === '' || $currentSeverity === '0')
            ? 'All Severities'
            : ($severities[$currentSeverity] ?? ('Severity ' . $currentSeverity));

        $menu = sprintf('<li><a href="#" class="dropdown-toggle">%s</a><ul class="d-menu">', $this->escape($label));
        $menu .= sprintf(
            '<li><a href="%s" style="border-bottom:1px solid #eee; font-weight:bold;">❌ All Severities</a></li>',
            $this->escape($this->generateScriptPath(['severity' => 0]))
        );

        foreach ($severities as $level => $name) {
            $isActive = ((string) $level === $currentSeverity);
            $menu .= sprintf(
                '<li><a href="%s" %s>%s %s</a></li>',
                $this->escape($this->generateScriptPath(['severity' => $level])),
                $isActive ? 'class="active-selection"' : '',
                $isActive ? '✅' : '▫️',
                $this->escape($name)
            );
        }

        return $menu . '</ul></li>';
    }

    public function generateMenu(array $hostgroups, array $severities): void
    {
        $hideAcked = $_SESSION['hide_acked'] ?? false;
        $hideMaint = $_SESSION['hide_maint'] ?? false;

        $this->menu = sprintf('<div class="app-bar"><a href="#" class="brand">%s</a>', $this->escape($this->title));
        $this->menu .= '<ul class="app-bar-menu">';
        $this->menu .= $this->generateHostgroupMenu($hostgroups);
        $this->menu .= $this->generateSeverityMenu($severities);
        $this->menu .= sprintf(
            '<li><a href="%s">%s Acked</a></li>',
            $this->escape($this->generateScriptPath(['hide_acked' => $hideAcked ? 0 : 1])),
            $hideAcked ? 'Show' : 'Hide'
        );
        $this->menu .= sprintf(
            '<li><a href="%s">%s Maint</a></li>',
            $this->escape($this->generateScriptPath(['hide_maint' => $hideMaint ? 0 : 1])),
            $hideMaint ? 'Show' : 'Hide'
        );
        $this->menu .= '</ul><div class="place-right"><span id="clock"></span></div></div>';
    }

    public function displayError(int $code, string $message, string $trace = ''): void
    {
        $traceHtml = $trace !== '' ? sprintf('<pre>%s</pre>', $this->escape($trace)) : '';
        $this->mainContent = sprintf(
            '<div style="padding:20px;"><div style="background:#fff0f0;border:1px solid #ce352c;padding:20px;"><h3>Error %d</h3><p>%s</p>%s</div></div>',
            $code,
            $this->escape($message),
            $traceHtml
        );
    }

    public function publish(): void
    {
        if ($this->isAjaxRequest) {
            header('Content-Type: application/json');
            echo json_encode(['html' => $this->ajaxOutput]);
            return;
        }

        echo $this->generateHeader();
        echo sprintf('<body>%s%s</body></html>', $this->menu, $this->mainContent);
    }

    private function generateHeader(): string
    {
        $nonce = base64_encode(random_bytes(16));
        $_SESSION['csp_nonce'] = $nonce;

        $csp = sprintf(
            "default-src 'self'; script-src 'self' 'nonce-%s'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self';",
            $nonce
        );

        return sprintf(
            "<!DOCTYPE html>\n<html lang='en'>\n<head>\n<title>%s</title>
<meta charset='utf-8'>
<meta name='viewport' content='width=device-width, initial-scale=1'>
<meta name='csrf-token' content='%s'>
<meta name='refresh-interval' content='%d'>
<meta http-equiv=\"Content-Security-Policy\" content=\"%s\">
<link href='css/style.css' rel='stylesheet'>
<script src='js/jquery-4.0.0.min.js' nonce='%s'></script>
<script src='js/wallboard.js' nonce='%s'></script>
</head>\n",
            $this->escape($this->title),
            $this->escape($this->csrfToken),
            $this->ajaxRefreshInterval,
            $csp,
            $nonce,
            $nonce
        );
    }

    private function isLunchTime(): bool
    {
        if (empty($this->lunchReminders))
            return false;

        $now = (int) date('Hi');

        foreach ($this->lunchReminders as ['start' => $start, 'end' => $end]) {
            if ($start <= $end ? ($now >= $start && $now <= $end) : ($now >= $start || $now <= $end))
                return true;
        }

        return false;
    }
}
