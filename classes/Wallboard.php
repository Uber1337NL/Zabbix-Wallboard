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
    private bool $lunchReminder;
    private int $lunchReminderStart;
    private int $lunchReminderEnd;
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
        $this->lunchReminder = $display['LUNCH_REMINDER'] ?? false;
        $this->lunchReminderStart = $display['LUNCH_REMINDER_START'] ?? 1200;
        $this->lunchReminderEnd = $display['LUNCH_REMINDER_END'] ?? 1230;
        
        $this->csrfToken = $_SESSION['csrf_token'] ?? $this->generateCsrfToken();
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

        if (isset($reqParams['groupid'])) {
            $params['groupid'] = $reqParams['groupid'];
        } elseif (isset($_SESSION['groupid'])) {
            $params['groupid'] = $_SESSION['groupid'];
        }

        if (isset($params['groupid']) && is_array($params['groupid']) && count($params['groupid']) === 0) {
            $params['groupid'] = ['all'];
        }

        if (isset($reqParams['severity'])) {
            $params['severity'] = $reqParams['severity'];
        } elseif (isset($_SESSION['severity'])) {
            $params['severity'] = $_SESSION['severity'];
        }

        if (isset($reqParams['action'])) {
            $params['action'] = $reqParams['action'];
        }

        if (isset($reqParams['eventid'])) {
            $params['eventid'] = $reqParams['eventid'];
        }

        if (isset($reqParams['hide_acked'])) {
            $params['hide_acked'] = $reqParams['hide_acked'];
        } elseif (isset($_SESSION['hide_acked'])) {
            $params['hide_acked'] = $_SESSION['hide_acked'] ? 1 : 0;
        }

        if (isset($reqParams['hide_maint'])) {
            $params['hide_maint'] = $reqParams['hide_maint'];
        } elseif (isset($_SESSION['hide_maint'])) {
            $params['hide_maint'] = $_SESSION['hide_maint'] === false ? 1 : 0;
        }

        $params['csrf_token'] = $this->csrfToken;

        return $this->scriptPath . '?' . http_build_query($params);
    }

    public function generateMainContent(array $triggers): void
    {
        if ($this->problemCountShow === 0) {
            $this->problemCountShow = count($triggers);
        }

        $this->mainContent = '<div class="container-fluid" id="main-content">';
        $this->mainContent .= $this->generateTiles($triggers);
        $this->mainContent .= $this->generateEventDialog();
        $this->mainContent .= '</div>';
    }

    private function generateTiles(array $triggers): string
    {
        $output = '';
        
        if (empty($triggers)) {
            return $this->generateNoIssuesPanel();
        }

        for ($i = 0; $i < min($this->problemCountShow, count($triggers)); $i++) {
            $trigger = $triggers[$i];
            if (!is_array($trigger)) {
               continue;
            }
            $isMaintenance = $this->isInMaintenance($trigger);
            $isAcknowledged = $this->isAcknowledged($trigger);

            $color = ($isMaintenance || $isAcknowledged) 
                ? '' 
                : $this->getSeverityColor((int)$trigger['priority']);

            $onclick = isset($trigger['lastEvent']['eventid'])
                ? sprintf(
                    'onclick="showDialogDetails(\'#dialog_details\',\'%s\');"',
                    $this->escape($trigger['lastEvent']['eventid'])
                )
                : '';

            $output .= sprintf(
                '<div class="tile-wide %s no-margin-right shadow" data-role="tile" %s>',
                $color,
                $onclick
            );
            $output .= '<div class="tile-content">';
            $output .= sprintf(
                '<p class="align-center text-default">%s</p>',
                $this->escape(date('Y-m-d H:i:s', $trigger['lastchange']))
            );

            $hostname = $this->escape($trigger['hosts'][0]['name']);
            $description = $this->escape($trigger['description']);

            $output .= $this->generateResponsiveText($hostname, 32, 'text-accent');
            $output .= $this->generateResponsiveText($description, 64, 'text-default');
            $output .= $this->generateBadges($isMaintenance, $isAcknowledged);
            $output .= '</div></div>';
        }

        return $output;
    }

    private function isInMaintenance(array $trigger): bool
    {
        return array_search('1', array_column($trigger['hosts'], 'maintenance_status')) !== false;
    }

    private function isAcknowledged(array $trigger): bool
    {
        return isset($trigger['lastEvent']['acknowledged']) 
            && $trigger['lastEvent']['acknowledged'] === '1';
    }

    private function generateResponsiveText(string $text, int $threshold, string $class): string
    {
        $isLong = strlen($text) > $threshold;
        return sprintf(
            '<p class="align-center %s-small %s">%s</p><p class="align-center %s %s">%s</p>',
            $class,
            $isLong ? '' : 'hidden',
            $text,
            $class,
            $isLong ? 'hidden' : '',
            $text
        );
    }

    private function generateBadges(bool $maintenance, bool $acknowledged): string
    {
        $badges = [];
        
        if ($maintenance) {
            $badges[] = '<span class="mif-wrench"></span>';
        }
        
        if ($acknowledged) {
            $badges[] = '<span class="mif-checkmark"></span>';
        }

        if (empty($badges)) {
            return '';
        }

        return '<span class="tile-badge bg-emerald">' . implode(' | ', $badges) . '</span>';
    }

    private function generateNoIssuesPanel(): string
    {
        $icon = ($this->lunchReminder 
            && (int)date('Hi') >= $this->lunchReminderStart 
            && (int)date('Hi') <= $this->lunchReminderEnd)
            ? '<span class="mif-spoon-fork mif-ani-flash fg-emerald" style="font-size: 30em;"></span>'
            : '<span class="mif-thumbs-up fg-emerald" style="font-size: 30em;"></span>';

        return <<<HTML
        <div class="row flex-just-center">&nbsp;</div>
        <div class="row flex-just-center">
            <div class="cell"></div>
            <div class="panel success">
                <div class="heading">
                    <span class="icon mif-thumbs-up"></span>
                    <span class="title">No issues!</span>
                </div>
                <div class="content">
                    <p class="align-center text-default">
                        There are no issues in this hostgroup. Good Job!<br />&nbsp;<br />
                        {$icon}
                    </p>
                </div>
            </div>
        </div>
        HTML;
    }

    private function generateEventDialog(): string
    {
        $formAction = $this->escape($this->generateScriptPath(['action' => 'add_acknowledge']));
        
        return <<<HTML
        <div class="dialog" data-role="dialog" id="dialog_details" data-overlay-click-close="true">
            <div class="dialog-title">Event Details</div>
            <div class="dialog-content" id="dialog_details_content"></div>
        </div>
        HTML;
    }

    public function ajaxEventDetails(array $details): void
    {
        $this->isAjaxRequest = true;
        $this->ajaxOutput = $this->formatEventDetails($details);
    }

    private function formatEventDetails(array $details): string
    {
        if (empty($details)) {
            return '<p>No details available</p>';
        }

        $event = $details[0];
        $output = '<table class="table striped">';
        
        $fields = [
            'Clock' => date('Y-m-d H:i:s', $event['clock']),
            'Message' => $event['name'] ?? 'N/A'
        ];

        foreach ($fields as $label => $value) {
            $output .= sprintf(
                '<tr><td><b>%s</b></td><td>%s</td></tr>',
                $this->escape($label),
                $this->escape($value)
            );
        }

        $output .= '</table>';

        if (!empty($event['acknowledges'])) {
            $output .= '<h4>Acknowledgements</h4><table class="table striped">';
            foreach ($event['acknowledges'] as $ack) {
                $output .= sprintf(
                    '<tr><td>%s</td><td>%s %s</td><td>%s</td></tr>',
                    $this->escape(date('Y-m-d H:i:s', $ack['clock'])),
                    $this->escape($ack['name'] ?? ''),
                    $this->escape($ack['surname'] ?? ''),
                    $this->escape($ack['message'] ?? '')
                );
            }
            $output .= '</table>';
        }

        if (isset($_SESSION['username'])) {
            $output .= $this->generateAcknowledgeForm($event['eventid']);
        }

        return $output;
    }

    private function generateAcknowledgeForm(string $eventId): string
    {
        $action = $this->escape($this->generateScriptPath(['action' => 'add_acknowledge']));
        
        return sprintf(
            '<form method="POST" action="%s">
                <input type="hidden" name="eventid" value="%s">
                <input type="hidden" name="csrf_token" value="%s">
                <div class="form-group">
                    <textarea name="ack_msg" class="input" placeholder="Acknowledgement message" required></textarea>
                </div>
                <button type="submit" class="button primary">Acknowledge</button>
            </form>',
            $action,
            $this->escape($eventId),
            $this->escape($this->csrfToken)
        );
    }

    public function generateMenu(array $hostgroups, array $severities): void
    {
        $this->menu = '<div class="app-bar" data-role="appbar">';
        $this->menu .= sprintf('<a href="#" class="brand">%s</a>', $this->escape($this->title));
        $this->menu .= '<ul class="app-bar-menu">';
        $this->menu .= $this->generateHostgroupMenu($hostgroups);
        $this->menu .= $this->generateSeverityMenu($severities);
        $this->menu .= $this->generateFilterMenu();
        $this->menu .= $this->generateAuthMenu();
        $this->menu .= '</ul></div>';
    }

    private function generateHostgroupMenu(array $hostgroups): string
    {
        $current = $_SESSION['group_name'] ?? 'All';
        $menu = sprintf('<li><a href="#" class="dropdown-toggle">%s</a><ul class="d-menu" data-role="dropdown">', $this->escape($current));
        
        foreach ($hostgroups as $group) {
            $url = $this->escape($this->generateScriptPath(['groupid' => $group['groupid']]));
            $menu .= sprintf('<li><a href="%s">%s</a></li>', $url, $this->escape($group['name']));
        }
        
        $menu .= '</ul></li>';
        return $menu;
    }

    private function generateSeverityMenu(array $severities): string
    {
        $current = $_SESSION['severity_name'] ?? 'All Severities';
        $menu = sprintf('<li><a href="#" class="dropdown-toggle">%s</a><ul class="d-menu" data-role="dropdown">', $this->escape($current));
        
        foreach ($severities as $level => $name) {
            $url = $this->escape($this->generateScriptPath(['severity' => $level]));
            $menu .= sprintf('<li><a href="%s">%s</a></li>', $url, $this->escape($name));
        }
        
        $menu .= '</ul></li>';
        return $menu;
    }

    private function generateFilterMenu(): string
    {
        $hideAcked = $_SESSION['hide_acked'] ?? false;
        $hideMaint = $_SESSION['hide_maint'] ?? false;

        return sprintf(
            '<li><a href="%s">%s Acked</a></li><li><a href="%s">%s Maint</a></li>',
            $this->escape($this->generateScriptPath(['hide_acked' => $hideAcked ? 0 : 1])),
            $hideAcked ? 'Show' : 'Hide',
            $this->escape($this->generateScriptPath(['hide_maint' => $hideMaint ? 0 : 1])),
            $hideMaint ? 'Show' : 'Hide'
        );
    }

    private function generateAuthMenu(): string
    {
        if (isset($_SESSION['username'])) {
            $url = $this->escape($this->generateScriptPath(['action' => 'logout']));
            return sprintf('<li><a href="%s">Logout (%s)</a></li>', $url, $this->escape($_SESSION['username']));
        }

        return sprintf(
            '<li><a href="#" data-role="dialog" data-dialog="#login_dialog">Login</a></li>%s',
            $this->generateLoginDialog()
        );
    }

    private function generateLoginDialog(): string
    {
        $action = $this->escape($this->generateScriptPath(['action' => 'login']));
        
        return sprintf(
            '<div class="dialog" data-role="dialog" id="login_dialog">
                <div class="dialog-title">Login</div>
                <div class="dialog-content">
                    <form method="POST" action="%s">
                        <input type="hidden" name="csrf_token" value="%s">
                        <input type="text" name="username" placeholder="Username" required>
                        <input type="password" name="password" placeholder="Password" required>
                        <button type="submit" class="button primary">Login</button>
                    </form>
                </div>
            </div>',
            $action,
            $this->escape($this->csrfToken)
        );
    }

    public function displayError(int $code, string $message, string $trace): void
    {
        $this->mainContent = sprintf(
            '<div class="container"><div class="panel alert">
                <div class="heading"><span class="icon mif-warning"></span>
                <span class="title">Error %d</span></div>
                <div class="content"><p>%s</p></div></div></div>',
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
        echo $this->generateBody();
    }

    private function generateHeader(): string
    {
        $nonce = base64_encode(random_bytes(16));
        $_SESSION['csp_nonce'] = $nonce;
        
        $csp = sprintf(
            "default-src 'self'; script-src 'self' 'nonce-%s'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none';",
            $nonce
        );
        
        return sprintf(
            "<!DOCTYPE html>\n<html lang='en'>\n<head>\n<title>%s</title>\n
            <meta charset='utf-8'>\n
            <meta http-equiv='X-UA-Compatible' content='IE=edge'>\n
            <meta name='viewport' content='width=device-width, initial-scale=1'>\n
            <meta name='csrf-token' content='%s'>\n
            <meta http-equiv='Content-Security-Policy' content='%s'>\n
            <link href='css/metro.min.css' rel='stylesheet'>\n
            <link href='css/metro-icons.min.css' rel='stylesheet'>\n
            <link href='css/metro-responsive.min.css' rel='stylesheet'>\n
            <link href='css/metro-schema.min.css' rel='stylesheet'>\n˜
            <link href='css/style.css' rel='stylesheet'>\n
            <script src='js/jquery-3.7.0.min.js'></script>\n
            <script src='js/metro.min.js'></script>\n
            <script src='js/security.js'></script>\n
            <script src='js/wallboard.js'></script>\n
            <script src='js/scale.js'></script>\n
            </head>",
            $this->escape($this->title),
            $this->escape($this->csrfToken),
            $csp
        );
    }

    private function generateBody(): string
    {
        return sprintf(
            "<body class='bg-white'>%s%s</body></html>",
            $this->menu,
            $this->mainContent
        );
    }
}
