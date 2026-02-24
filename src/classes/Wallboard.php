<?php

class Wallboard
{
    private const SEVERITY_COLORS = [
        0 => 'text-shadow',
        1 => 'fg-white bg-emerald text-shadow',
        2 => 'fg-white bg-amber text-shadow',
        3 => 'fg-white bg-orange text-shadow',
        4 => 'fg-white bg-red text-shadow',
        5 => 'fg-white bg-darkMagenta text-shadow'
    ];

    private string $scriptPath;
    private string $title;
    private int $problemCountShow;
    private int $ajaxRefreshInterval;

    private array $lunchReminders = [];

    private string $menu = '';
    private string $mainContent = '';
    private bool $isAjaxRequest = false;
    private string $ajaxOutput = '';
    private string $csrfToken;

    public function __construct(string $scriptPath = '', array $display = [])
    {
        $this->scriptPath = $scriptPath;
        $this->title = $display['TITLE'] ?? 'ZbxWallboard';
        $this->problemCountShow = $display['PROBLEM_COUNT_SHOW'] ?? 0;
        $this->ajaxRefreshInterval = $display['AJAX_REFRESH_INTERVAL'] ?? 30000;

        if (!empty($display['LUNCH_REMINDERS']) && is_array($display['LUNCH_REMINDERS'])) {
            foreach ($display['LUNCH_REMINDERS'] as $period) {
                if (!is_array($period))
                    continue;
                $start = isset($period['start']) ? (int) $period['start'] : null;
                $end = isset($period['end']) ? (int) $period['end'] : null;
                if ($start !== null && $end !== null) {
                    $this->lunchReminders[] = ['start' => $start, 'end' => $end];
                }
            }
        } elseif (!empty($display['LUNCH_REMINDER'])) {
            $start = isset($display['LUNCH_REMINDER_START']) ? (int) $display['LUNCH_REMINDER_START'] : 1200;
            $end = isset($display['LUNCH_REMINDER_END']) ? (int) $display['LUNCH_REMINDER_END'] : 1230;
            $this->lunchReminders[] = ['start' => $start, 'end' => $end];
        }

        $this->csrfToken = $_SESSION['csrf_token'] ?? $this->generateCsrfToken();

        if (!isset($_SESSION['groupid'])) {
            $_SESSION['groupid'] = ['all'];
        } elseif (!is_array($_SESSION['groupid'])) {
            $_SESSION['groupid'] = [(string) $_SESSION['groupid']];
        }

        if (!isset($_SESSION['severity']))
            $_SESSION['severity'] = 0;
        if (!isset($_SESSION['hide_acked']))
            $_SESSION['hide_acked'] = false;
        if (!isset($_SESSION['hide_maint']))
            $_SESSION['hide_maint'] = false;
    }

    private function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
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
        $params = [];

        if (array_key_exists('groupid', $reqParams)) {
            $params['groupid'] = $reqParams['groupid'];
        } else {
            $params['groupid'] = $_SESSION['groupid'] ?? ['all'];
        }

        if (array_key_exists('severity', $reqParams)) {
            $params['severity'] = $reqParams['severity'];
        } else {
            $params['severity'] = $_SESSION['severity'] ?? 0;
        }

        $params['hide_acked'] = array_key_exists('hide_acked', $reqParams)
            ? (int) $reqParams['hide_acked']
            : ((int) ($_SESSION['hide_acked'] ?? 0));

        $params['hide_maint'] = array_key_exists('hide_maint', $reqParams)
            ? (int) $reqParams['hide_maint']
            : ((int) ($_SESSION['hide_maint'] ?? 0));

        $params['csrf_token'] = $this->csrfToken;

        return $this->scriptPath . '?' . http_build_query($params);
    }

    public function generateMainContent(array $triggers): void
    {
        $this->mainContent = '<div id="main-content">';
        $this->mainContent .= $this->generateTiles($triggers);
        $this->mainContent .= '</div>';
    }

    public function ajaxMainContent(array $triggers): void
    {
        $this->isAjaxRequest = true;
        $this->ajaxOutput = $this->generateTiles($triggers);
    }

    private function generateTiles(array $triggers): string
    {
        $output = '<div id="wallboard-grid">';

        if (empty($triggers)) {
            $isLunch = $this->isLunchTime();
            $icon = $isLunch ? '🍴' : '👍';
            $output .= sprintf(
                '<div class="no-issues-panel"><div style="font-size: 15vh;">%s</div><p>No issues! Good Job!</p></div>',
                $icon
            );
            $output .= '</div>';
            return $output;
        }

        $limit = ($this->problemCountShow === 0) ? count($triggers) : $this->problemCountShow;

        for ($i = 0; $i < min($limit, count($triggers)); $i++) {
            $trigger = $triggers[$i];
            if (!is_array($trigger))
                continue;

            $hosts = $trigger['hosts'] ?? [];
            $lastEvent = $trigger['lastEvent'] ?? [];

            $isMaint = array_search('1', array_column($hosts, 'maintenance_status')) !== false;
            $isAck = ($lastEvent['acknowledged'] ?? '0') === '1';
            $color = ($isMaint || $isAck) ? '' : $this->getSeverityColor((int) $trigger['priority']);

            $output .= sprintf(
                '<div class="tile-wide %s shadow">',
                $color
            );
            $output .= '<div class="tile-content">';
            $output .= sprintf(
                '<p class="align-center text-date">%s</p>',
                date('Y-m-d H:i:s', $trigger['lastchange'] ?? time())
            );
            $hostName = $this->escape($hosts[0]['name'] ?? 'N/A');
            $desc = $this->escape($trigger['description'] ?? '');
            $output .= sprintf('<p class="align-center text-accent">%s</p>', $hostName);
            $output .= sprintf('<p class="align-center text-default">%s</p>', $desc);

            if ($isMaint || $isAck) {
                $badges = ($isMaint ? '🔧 ' : '') . ($isAck ? '✅' : '');
                $output .= '<span class="tile-badge bg-emerald">' . $badges . '</span>';
            }

            $output .= '</div></div>';
        }

        $output .= '</div>';
        return $output;
    }



    private function generateHostgroupMenu(array $hostgroups): string
    {
        $selectedIds = $_SESSION['groupid'] ?? ['all'];
        if (!is_array($selectedIds))
            $selectedIds = [$selectedIds];
        $selectedIds = array_map('strval', $selectedIds);

        $selectedCount = 0;
        if (in_array('all', $selectedIds, true)) {
            $label = 'All Hosts';
        } else {
            $selectedCount = count($selectedIds);
            $label = $selectedCount > 0 ? ($selectedCount . ' Selected') : 'All';
        }

        $menu = sprintf('<li><a href="#" class="dropdown-toggle">%s</a><ul class="d-menu">', $this->escape($label));

        $clearUrl = $this->escape($this->generateScriptPath(['groupid' => ['all']]));
        $menu .= sprintf('<li><a href="%s" style="border-bottom:1px solid #eee; font-weight:bold;">❌ Clear Filters</a></li>', $clearUrl);

        foreach ($hostgroups as $group) {
            $gid = (string) ($group['groupid'] ?? '');
            $isActive = in_array($gid, $selectedIds, true);

            $newSelection = array_values(array_diff($selectedIds, ['all']));
            if ($isActive) {
                $newSelection = array_values(array_diff($newSelection, [$gid]));
            } else {
                $newSelection[] = $gid;
            }
            if (empty($newSelection))
                $newSelection = ['all'];

            $url = $this->escape($this->generateScriptPath(['groupid' => $newSelection]));
            $icon = $isActive ? '✅' : '▫️';
            $class = $isActive ? 'class="active-selection"' : '';

            $menu .= sprintf(
                '<li><a href="%s" %s>%s %s</a></li>',
                $url,
                $class,
                $icon,
                $this->escape($group['name'] ?? '')
            );
        }

        $menu .= '</ul></li>';
        return $menu;
    }

    private function generateSeverityMenu(array $severities): string
    {
        $currentSeverity = (string) ($_SESSION['severity'] ?? '0');
        $label = ($currentSeverity === '' || $currentSeverity === '0') ? 'All Severities' : ($severities[$currentSeverity] ?? ('Severity ' . $currentSeverity));

        $menu = sprintf('<li><a href="#" class="dropdown-toggle">%s</a><ul class="d-menu">', $this->escape($label));

        $allUrl = $this->escape($this->generateScriptPath(['severity' => 0]));
        $menu .= sprintf('<li><a href="%s" style="border-bottom:1px solid #eee; font-weight:bold;">❌ All Severities</a></li>', $allUrl);

        foreach ($severities as $level => $name) {
            $isActive = ((string) $level === $currentSeverity);
            $url = $this->escape($this->generateScriptPath(['severity' => $level]));
            $icon = $isActive ? '✅' : '▫️';
            $class = $isActive ? 'class="active-selection"' : '';

            $menu .= sprintf(
                '<li><a href="%s" %s>%s %s</a></li>',
                $url,
                $class,
                $icon,
                $this->escape($name)
            );
        }

        $menu .= '</ul></li>';
        return $menu;
    }

    public function generateMenu(array $hostgroups, array $severities): void
    {
        $this->menu = '<div class="app-bar">';
        $this->menu .= sprintf('<a href="#" class="brand">%s</a>', $this->escape($this->title));

        $this->menu .= '<ul class="app-bar-menu">';
        $this->menu .= $this->generateHostgroupMenu($hostgroups);
        $this->menu .= $this->generateSeverityMenu($severities);

        $hideAcked = $_SESSION['hide_acked'] ?? false;
        $hideMaint = $_SESSION['hide_maint'] ?? false;

        $this->menu .= sprintf(
            '<li><a href="%s">%s Acked</a></li>',
            $this->escape($this->generateScriptPath(['hide_acked' => $hideAcked ? 0 : 1])),
            $hideAcked ? 'Hide' : 'Show'
        );
        $this->menu .= sprintf(
            '<li><a href="%s">%s Maint</a></li>',
            $this->escape($this->generateScriptPath(['hide_maint' => $hideMaint ? 0 : 1])),
            $hideMaint ? 'Hide' : 'Show'
        );

        $this->menu .= '</ul>';

        $this->menu .= '<div class="place-right">';
        $this->menu .= '<span id="clock"></span>';
        $this->menu .= '</div></div>';
    }



    public function displayError(int $code, string $message, string $trace): void
    {
        $this->mainContent = sprintf(
            '<div style="padding:20px;"><div style="background:#fff0f0;border:1px solid #ce352c;padding:20px;">
                <h3>Error %d</h3><p>%s</p></div></div>',
            $code,
            $this->escape($message)
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
        echo sprintf("<body>%s%s</body></html>", $this->menu, $this->mainContent);
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
                <script src='js/scale.js' nonce='%s'></script>
                </head>\n",
            $this->escape($this->title),
            $this->escape($this->csrfToken),
            $this->ajaxRefreshInterval,
            $csp,
            $nonce,
            $nonce,
            $nonce
        );
    }

    private function isLunchTime(): bool
    {
        if (empty($this->lunchReminders))
            return false;

        $now = (int) date('Hi');

        foreach ($this->lunchReminders as $period) {
            $start = (int) ($period['start'] ?? 0);
            $end = (int) ($period['end'] ?? 0);

            if ($start <= $end) {
                if ($now >= $start && $now <= $end)
                    return true;
            } else {
                if ($now >= $start || $now <= $end)
                    return true;
            }
        }

        return false;
    }
}
