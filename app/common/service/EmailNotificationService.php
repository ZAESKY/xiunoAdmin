<?php
declare(strict_types=1);

namespace app\common\service;

use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

class EmailNotificationService
{
    public static function mailTransportStatus(): array
    {
        $config = self::mailAddonConfig();
        $mode = intval($config['type'] ?? 0);
        $recipient = strtolower(trim((string)($config['recv'] ?? '')));
        $configured = false;

        if ($mode === 1) {
            $smtp = (array)($config['smtp'] ?? []);
            $port = intval($smtp['port'] ?? 0);
            $configured = trim((string)($smtp['server'] ?? '')) !== ''
                && $port > 0 && $port <= 65535
                && filter_var((string)($smtp['name'] ?? ''), FILTER_VALIDATE_EMAIL)
                && (string)($smtp['authcode'] ?? '') !== '';
        } elseif ($mode === 2) {
            $aliyun = (array)($config['aliyun'] ?? []);
            $configured = trim((string)($aliyun['accessKey'] ?? '')) !== ''
                && (string)($aliyun['accessSecret'] ?? '') !== ''
                && filter_var((string)($aliyun['name'] ?? ''), FILTER_VALIDATE_EMAIL);
        } elseif ($mode === 3) {
            $sendcloud = (array)($config['sendcloud'] ?? []);
            $configured = trim((string)($sendcloud['apiUser'] ?? '')) !== ''
                && (string)($sendcloud['apiKey'] ?? '') !== ''
                && filter_var((string)($sendcloud['name'] ?? ''), FILTER_VALIDATE_EMAIL);
        }

        $modeKeys = [
            0 => 'notification_email.transport.mode.disabled',
            1 => 'notification_email.transport.mode.smtp',
            2 => 'notification_email.transport.mode.aliyun',
            3 => 'notification_email.transport.mode.sendcloud',
        ];

        return [
            'mode' => $mode,
            'mode_name' => t($modeKeys[$mode] ?? $modeKeys[0]),
            'configured' => $configured,
            'recipient_valid' => (bool)filter_var($recipient, FILTER_VALIDATE_EMAIL),
            'recipient_masked' => filter_var($recipient, FILTER_VALIDATE_EMAIL)
                ? self::maskEmail($recipient)
                : '',
        ];
    }

    public static function sendTransportTest(int $adminId): array
    {
        $status = self::mailTransportStatus();
        if (empty($status['configured'])) {
            throw new \InvalidArgumentException(t('notification_email.transport.not_configured'));
        }
        if (empty($status['recipient_valid'])) {
            throw new \InvalidArgumentException(t('notification_email.transport.recipient_missing'));
        }

        $config = self::mailAddonConfig();
        $email = strtolower(trim((string)($config['recv'] ?? '')));
        $siteName = self::plain((string)(conf('title') ?: 'NoteWeb'), 150);
        $variables = ['site_name' => $siteName];
        $subject = t('notification_email.transport.test_subject', ['site_name' => $siteName]);
        $html = self::wrapHtml(
            '<h2 style="margin:0 0 16px;color:#0f172a;">' .
            htmlspecialchars(t('notification_email.transport.test_title'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
            '</h2><p style="margin:0 0 10px;line-height:1.8;">' .
            htmlspecialchars(t('notification_email.transport.test_body', ['mode' => (string)$status['mode_name']]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
            '</p><p style="margin:0;color:#64748b;">' .
            htmlspecialchars(datetime(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
            $variables
        );
        $result = self::sendMail($email, '', $subject, $html);
        self::logDelivery('transport_test', 'admin', $adminId, $email, $result);
        return $result;
    }

    public static function catalog(string $audience, int $ownerId = 0): array
    {
        $preferences = self::preferences($audience, $ownerId);
        // User notifications are opt-in. Admin notifications retain their
        // original opt-out behaviour so operational alerts are not lost.
        $defaultEnabled = $audience === 'admin';
        $result = [];
        foreach (NotificationEventService::all($audience) as $code => $event) {
            $result[] = [
                'event_code' => $code,
                'name' => t($event['label_key']),
                'description' => t($event['description_key']),
                'enabled' => $preferences[$code] ?? $defaultEnabled,
            ];
        }
        return $result;
    }

    public static function isGlobalEnabled(): bool
    {
        return (string)conf('email_notification_enabled') === '1';
    }

    public static function preferences(string $ownerType, int $ownerId): array
    {
        $map = [];
        try {
            $rows = Db::name('notification_email_preference')
                ->where('owner_type', $ownerType)
                ->where('owner_id', $ownerId)
                ->select()->toArray();
            foreach ($rows as $row) {
                $map[(string)$row['event_code']] = intval($row['enabled']) === 1;
            }
        } catch (\Throwable $e) {
            // Before the migration is applied the settings page should remain usable.
        }
        return $map;
    }

    public static function savePreferences(string $ownerType, int $ownerId, array $enabledCodes): void
    {
        if (!in_array($ownerType, ['user', 'admin'], true) || $ownerId < 1) {
            throw new \InvalidArgumentException(t('notification_email.invalid_owner'));
        }
        $allowed = NotificationEventService::codes($ownerType);
        $enabledCodes = array_values(array_intersect($allowed, array_map('strval', $enabledCodes)));
        if ($ownerType === 'user') {
            if (!self::isGlobalEnabled()) {
                throw new \InvalidArgumentException(t('notification_email.feature_disabled'));
            }
            if ($enabledCodes && !self::userHasValidEmail($ownerId)) {
                throw new \InvalidArgumentException(t('notification_email.bind_email_required'));
            }
        }
        $now = datetime();
        Db::transaction(function () use ($ownerType, $ownerId, $allowed, $enabledCodes, $now): void {
            foreach ($allowed as $eventCode) {
                $enabled = in_array($eventCode, $enabledCodes, true) ? 1 : 0;
                $query = Db::name('notification_email_preference')->where([
                    'owner_type' => $ownerType,
                    'owner_id' => $ownerId,
                    'event_code' => $eventCode,
                ]);
                if ($query->find()) {
                    $query->update(['enabled' => $enabled, 'updated_at' => $now]);
                } else {
                    Db::name('notification_email_preference')->insert([
                        'owner_type' => $ownerType,
                        'owner_id' => $ownerId,
                        'event_code' => $eventCode,
                        'enabled' => $enabled,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public static function templates(): array
    {
        $custom = [];
        try {
            foreach (Db::name('notification_email_template')->select()->toArray() as $row) {
                $custom[(string)$row['event_code']] = $row;
            }
        } catch (\Throwable $e) {
        }
        $rows = [];
        foreach (NotificationEventService::all() as $eventCode => $event) {
            $template = self::template($eventCode, $custom[$eventCode] ?? null);
            $rows[] = $template;
        }
        return $rows;
    }

    public static function template(string $eventCode, ?array $row = null): array
    {
        $default = NotificationEventService::defaultTemplate($eventCode);
        if ($row === null) {
            try {
                $row = Db::name('notification_email_template')->where('event_code', $eventCode)->find();
            } catch (\Throwable $e) {
                $row = null;
            }
        }
        if (!$row) {
            return $default;
        }
        return array_merge($default, [
            'subject' => (string)$row['subject'],
            'html_body' => (string)$row['html_body'],
            'enabled' => intval($row['enabled']) === 1 ? 1 : 0,
            'is_custom' => 1,
        ]);
    }

    public static function saveTemplate(string $eventCode, string $subject, string $htmlBody, bool $enabled, int $adminId): void
    {
        $event = NotificationEventService::get($eventCode);
        if (!$event) {
            throw new \InvalidArgumentException(t('notification_email.invalid_event'));
        }
        $subject = trim(strip_tags(html_entity_decode(stripslashes($subject), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $htmlBody = clean_rich_text($htmlBody);
        if ($subject === '' || mb_strlen($subject) > 255) {
            throw new \InvalidArgumentException(t('notification_email.invalid_subject'));
        }
        if ($htmlBody === '' || strlen($htmlBody) > 200000) {
            throw new \InvalidArgumentException(t('notification_email.invalid_body'));
        }
        self::validateVariables($eventCode, $subject . "\n" . $htmlBody);
        $now = datetime();
        $data = [
            'audience' => $event['audience'],
            'subject' => $subject,
            'html_body' => $htmlBody,
            'enabled' => $enabled ? 1 : 0,
            'updated_by' => $adminId,
            'updated_at' => $now,
        ];
        $query = Db::name('notification_email_template')->where('event_code', $eventCode);
        if ($query->find()) {
            $query->update($data);
        } else {
            $data['event_code'] = $eventCode;
            $data['created_at'] = $now;
            Db::name('notification_email_template')->insert($data);
        }
    }

    public static function resetTemplate(string $eventCode): void
    {
        if (!NotificationEventService::get($eventCode)) {
            throw new \InvalidArgumentException(t('notification_email.invalid_event'));
        }
        Db::name('notification_email_template')->where('event_code', $eventCode)->delete();
    }

    public static function saveGlobalEnabled(bool $enabled): void
    {
        $query = Db::name('config')->where('name', 'email_notification_enabled');
        if ($query->find()) {
            $query->update(['value' => $enabled ? '1' : '0']);
        } else {
            Db::name('config')->insert([
                'name' => 'email_notification_enabled', 'group' => 'email_notification',
                'title' => '业务邮件通知', 'tip' => '总开关关闭时仅保留站内消息，不发送业务邮件',
                'type' => 'bool', 'value' => $enabled ? '1' : '0', 'content' => '',
                'rule' => '', 'extend' => '', 'tip_type' => '',
            ]);
        }
        Cache::delete('email_notification_enabled');
        Cache::tag('SF_Set')->clear();
    }

    /** Called after a station notification was persisted. Never throws. */
    public static function dispatch(array $notification): void
    {
        try {
            if ((string)conf('email_notification_enabled') !== '1') {
                return;
            }
            $eventCode = (string)($notification['type'] ?? '');
            $event = NotificationEventService::get($eventCode);
            if (!$event) {
                return;
            }
            $template = self::template($eventCode);
            if (intval($template['enabled']) !== 1) {
                return;
            }
            $ownerType = (string)$event['audience'];
            $ownerId = intval($notification['user_id'] ?? 0);
            $recipients = self::recipients($ownerType, $ownerId);
            foreach ($recipients as $recipient) {
                if (!self::preferenceEnabled($ownerType, intval($recipient['id']), $eventCode)) {
                    continue;
                }
                self::deliver($eventCode, $event, $template, $notification, $recipient);
            }
        } catch (\Throwable $e) {
            Log::warning('Email notification dispatch skipped: ' . $e->getMessage());
        }
    }

    public static function preview(string $eventCode, ?string $subject = null, ?string $htmlBody = null): array
    {
        $template = self::template($eventCode);
        if ($subject !== null) {
            $template['subject'] = trim(stripslashes($subject));
        }
        if ($htmlBody !== null) {
            $template['html_body'] = clean_rich_text($htmlBody);
        }
        self::validateVariables($eventCode, $template['subject'] . "\n" . $template['html_body']);
        $event = NotificationEventService::get($eventCode);
        $variables = self::sampleVariables($eventCode);
        return [
            'subject' => self::renderText($template['subject'], $variables),
            'html' => self::wrapHtml(self::renderHtml($template['html_body'], $variables), $variables),
            'variables' => NotificationEventService::allowedVariables($eventCode),
            'event_name' => t($event['label_key']),
        ];
    }

    public static function sendTest(string $eventCode, string $email, string $recipientName = ''): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(t('notification_email.invalid_email'));
        }
        $preview = self::preview($eventCode);
        return self::sendMail($email, $recipientName, $preview['subject'], $preview['html']);
    }

    private static function recipients(string $ownerType, int $ownerId): array
    {
        if ($ownerType === 'user') {
            if ($ownerId < 1) {
                return [];
            }
            $row = Db::name('user')->where('id', $ownerId)->field('id,username,email')->find();
            return $row && filter_var((string)$row['email'], FILTER_VALIDATE_EMAIL) ? [$row] : [];
        }
        $rows = Db::name('admin')->where('status', 1)->field('id,username,email')->select()->toArray();
        $result = [];
        $seen = [];
        foreach ($rows as $row) {
            $email = strtolower(trim((string)($row['email'] ?? '')));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && !isset($seen[$email])) {
                $row['email'] = $email;
                $result[] = $row;
                $seen[$email] = true;
            }
        }
        return $result;
    }

    private static function preferenceEnabled(string $ownerType, int $ownerId, string $eventCode): bool
    {
        $row = Db::name('notification_email_preference')->where([
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'event_code' => $eventCode,
        ])->find();
        if (!$row) {
            return $ownerType === 'admin';
        }
        return intval($row['enabled']) === 1;
    }

    private static function userHasValidEmail(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        $email = strtolower(trim((string)Db::name('user')->where('id', $userId)->value('email')));
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    private static function deliver(string $eventCode, array $event, array $template, array $notification, array $recipient): void
    {
        $variables = self::variables($eventCode, $event, $notification, $recipient);
        $subject = self::renderText((string)$template['subject'], $variables);
        $body = self::wrapHtml(self::renderHtml((string)$template['html_body'], $variables), $variables);
        try {
            $result = self::sendMail((string)$recipient['email'], (string)$recipient['username'], $subject, $body);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'error_code' => 'MAIL_PROVIDER_EXCEPTION'];
            Log::warning('Email notification provider exception: ' . $e->getMessage());
        }
        self::logDelivery($eventCode, (string)$event['audience'], intval($recipient['id']), (string)$recipient['email'], $result);
    }

    private static function variables(string $eventCode, array $event, array $notification, array $recipient): array
    {
        $title = self::plain((string)($notification['title'] ?? ''), 255);
        $content = self::plain((string)($notification['content'] ?? ''), 4000);
        $variables = [
            'site_name' => self::plain((string)(conf('title') ?: (defined('SITE_NAME') ? SITE_NAME : 'SF授权平台')), 150),
            'recipient_name' => self::plain((string)($recipient['username'] ?? ''), 150),
            'notification_title' => $title,
            'notification_content' => $content,
            'action_url' => self::actionUrl((string)($notification['link'] ?? ''), (string)$event['audience']),
            'event_name' => t($event['label_key']),
            'sent_at' => datetime(),
        ];
        foreach ((array)($notification['variables'] ?? []) as $key => $value) {
            if (in_array((string)$key, NotificationEventService::allowedVariables($eventCode), true) && is_scalar($value)) {
                $variables[(string)$key] = self::plain((string)$value, 4000);
            }
        }
        foreach (NotificationEventService::allowedVariables($eventCode) as $name) {
            $variables[$name] = $variables[$name] ?? '';
        }
        return $variables;
    }

    private static function sampleVariables(string $eventCode): array
    {
        $event = NotificationEventService::get($eventCode);
        $values = [
            'site_name' => (string)(conf('title') ?: 'NoteWeb'),
            'recipient_name' => t('notification_email.preview_recipient'),
            'notification_title' => t($event['label_key']),
            'notification_content' => t($event['description_key']),
            'action_url' => (defined('SITE_URL') ? SITE_URL : 'https://example.com') . '/',
            'event_name' => t($event['label_key']),
            'sent_at' => datetime(),
            'plugin_name' => t('notification_email.sample.plugin_name'),
            'review_status' => t('notification_email.sample.review_status'),
            'audit_note' => t('notification_email.sample.audit_note'),
            'feedback_title' => t('notification_email.sample.feedback_title'),
            'feedback_reply' => t('notification_email.sample.feedback_reply'),
            'rating' => '5', 'comment_content' => t('notification_email.sample.comment'),
            'buyer_name' => t('notification_email.sample.buyer_name'),
            'income' => '¥ 88.00', 'reward' => '100 ' . t('notification_email.sample.points'),
            'amount' => '88.00', 'remark' => t('notification_email.sample.remark'),
            'discount_code' => 'DEMO2026', 'order_no' => 'DEMO202610040001',
            'settled_at' => datetime(), 'phase' => t('notification_email.sample.phase'),
            'username' => t('notification_email.sample.username'),
        ];
        foreach (NotificationEventService::allowedVariables($eventCode) as $name) {
            $values[$name] = $values[$name] ?? t('notification_email.sample.generic');
        }
        return $values;
    }

    private static function validateVariables(string $eventCode, string $template): void
    {
        preg_match_all('/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/i', $template, $matches);
        $unknown = array_diff(array_unique($matches[1] ?? []), NotificationEventService::allowedVariables($eventCode));
        if ($unknown) {
            throw new \InvalidArgumentException(t('notification_email.unknown_variables', ['variables' => implode(', ', $unknown)]));
        }
    }

    private static function renderText(string $template, array $variables): string
    {
        $rendered = self::replace($template, $variables, false);
        return mb_substr(trim(strip_tags(html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 0, 255);
    }

    private static function renderHtml(string $template, array $variables): string
    {
        return self::replace($template, $variables, true);
    }

    private static function replace(string $template, array $variables, bool $escape): string
    {
        return (string)preg_replace_callback('/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/i', static function (array $match) use ($variables, $escape): string {
            $value = (string)($variables[$match[1]] ?? '');
            return $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
        }, $template);
    }

    private static function wrapHtml(string $content, array $variables): string
    {
        $siteName = htmlspecialchars((string)($variables['site_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>' .
            '<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Arial,sans-serif;color:#334155;">' .
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:32px 12px;"><tr><td align="center">' .
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background-color:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 8px 30px rgba(15,23,42,.08);">' .
            '<tr><td style="padding:22px 28px;background-color:#1e9fff;color:#ffffff;font-size:18px;font-weight:700;">' . $siteName . '</td></tr>' .
            '<tr><td style="padding:30px 28px;">' . $content . '</td></tr>' .
            '<tr><td style="padding:18px 28px;background-color:#f8fafc;color:#94a3b8;font-size:12px;line-height:1.6;">' .
            htmlspecialchars(t('notification_email.footer'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td></tr></table></td></tr></table></body></html>';
    }

    private static function sendMail(string $email, string $recipientName, string $subject, string $html): array
    {
        $raw = hook('mailNotifyUser', [
            'to' => $email,
            'from_name' => (string)(conf('title') ?: 'NoteWeb'),
            'title' => $subject,
            'content' => $html,
        ]);
        $response = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($response) || intval($response['code'] ?? -1) !== 0) {
            return ['success' => false, 'error_code' => 'MAIL_PROVIDER_REJECTED'];
        }
        return ['success' => true, 'error_code' => ''];
    }

    private static function mailAddonConfig(): array
    {
        try {
            $raw = function_exists('get_addons_fullconfig')
                ? (array)get_addons_fullconfig('mail')
                : [];
            $config = [];
            foreach ($raw as $key => $item) {
                if (is_array($item) && isset($item['name'])) {
                    $name = trim((string)$item['name']);
                    if ($name !== '') {
                        $config[$name] = $item['value'] ?? null;
                    }
                } elseif (is_string($key)) {
                    $config[$key] = $item;
                }
            }
            return $config;
        } catch (\Throwable $e) {
            Log::warning('Mail add-on configuration could not be read: ' . $e->getMessage());
            return [];
        }
    }

    private static function logDelivery(string $eventCode, string $recipientType, int $recipientId, string $email, array $result): void
    {
        try {
            Db::name('notification_email_log')->insert([
                'event_code' => $eventCode,
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'email_hash' => hash('sha256', strtolower(trim($email))),
                'email_masked' => self::maskEmail($email),
                'status' => !empty($result['success']) ? 'sent' : 'failed',
                'error_code' => (string)($result['error_code'] ?? ''),
                'created_at' => datetime(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Email notification audit write failed: ' . $e->getMessage());
        }
    }

    private static function actionUrl(string $link, string $audience): string
    {
        $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        if ($link === '') {
            return $base . '/';
        }
        if (preg_match('#^https?://#i', $link)) {
            $parts = parse_url($link);
            $baseParts = parse_url($base);
            if (is_array($parts) && is_array($baseParts) && strtolower((string)($parts['host'] ?? '')) === strtolower((string)($baseParts['host'] ?? ''))) {
                return $link;
            }
            return $base . '/';
        }
        $front = $audience === 'admin' ? '/admin.php' : '/user.php';
        return $base . $front . '/' . ltrim($link, '/');
    }

    private static function plain(string $value, int $limit): string
    {
        $value = trim((string)preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
        return mb_substr($value, 0, $limit);
    }

    private static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
        return $visible . '***@' . $domain;
    }
}
