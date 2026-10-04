<?php
declare(strict_types=1);

namespace app\common\service;

/**
 * Single source of truth for every business notification that can send email.
 * Event codes intentionally match SF_notification.type so station messages and
 * email preferences cannot drift apart.
 */
class NotificationEventService
{
    private const STANDARD_VARIABLES = [
        'site_name', 'recipient_name', 'notification_title',
        'notification_content', 'action_url', 'event_name', 'sent_at',
    ];

    public static function all(?string $audience = null): array
    {
        $events = [
            'plugin_audit' => self::event('user', 'notification_email.event.plugin_audit', 'notification_email.event_desc.plugin_audit', ['plugin_name', 'review_status', 'audit_note']),
            'comment_audit' => self::event('user', 'notification_email.event.comment_audit', 'notification_email.event_desc.comment_audit', ['plugin_name', 'review_status']),
            'feedback_handled' => self::event('user', 'notification_email.event.feedback_handled', 'notification_email.event_desc.feedback_handled', ['feedback_title', 'review_status', 'feedback_reply']),
            'comment_reply' => self::event('user', 'notification_email.event.comment_reply', 'notification_email.event_desc.comment_reply', ['plugin_name']),
            'plugin_comment' => self::event('user', 'notification_email.event.plugin_comment', 'notification_email.event_desc.plugin_comment', ['plugin_name', 'rating', 'comment_content']),
            'plugin_purchase' => self::event('user', 'notification_email.event.plugin_purchase', 'notification_email.event_desc.plugin_purchase', ['buyer_name', 'plugin_name', 'income']),
            'plugin_reward' => self::event('user', 'notification_email.event.plugin_reward', 'notification_email.event_desc.plugin_reward', ['plugin_name', 'reward']),
            'withdraw_approved' => self::event('user', 'notification_email.event.withdraw_approved', 'notification_email.event_desc.withdraw_approved', ['amount', 'remark']),
            'withdraw_rejected' => self::event('user', 'notification_email.event.withdraw_rejected', 'notification_email.event_desc.withdraw_rejected', ['amount', 'remark']),
            'rebate' => self::event('user', 'notification_email.event.rebate', 'notification_email.event_desc.rebate', ['discount_code', 'amount', 'order_no', 'settled_at', 'phase']),
            'plugin_new' => self::event('admin', 'notification_email.event.plugin_new', 'notification_email.event_desc.plugin_new', ['username', 'plugin_name']),
            'plugin_comment_pending' => self::event('admin', 'notification_email.event.plugin_comment_pending', 'notification_email.event_desc.plugin_comment_pending', ['username', 'plugin_name', 'rating', 'comment_content']),
            'feedback_new' => self::event('admin', 'notification_email.event.feedback_new', 'notification_email.event_desc.feedback_new', ['username', 'feedback_title']),
            'withdraw_new' => self::event('admin', 'notification_email.event.withdraw_new', 'notification_email.event_desc.withdraw_new', ['username', 'amount']),
        ];

        if ($audience === null) {
            return $events;
        }
        return array_filter($events, static function (array $event) use ($audience): bool {
            return $event['audience'] === $audience;
        });
    }

    public static function get(string $eventCode): ?array
    {
        $events = self::all();
        return $events[$eventCode] ?? null;
    }

    public static function codes(string $audience): array
    {
        return array_keys(self::all($audience));
    }

    public static function allowedVariables(string $eventCode): array
    {
        $event = self::get($eventCode);
        return $event ? $event['variables'] : [];
    }

    public static function defaultTemplate(string $eventCode): array
    {
        $event = self::get($eventCode);
        if (!$event) {
            throw new \InvalidArgumentException('Unknown notification event');
        }
        $label = t($event['label_key']);
        return [
            'event_code' => $eventCode,
            'audience' => $event['audience'],
            'event_name' => $label,
            'subject' => '【{{site_name}}】{{notification_title}}',
            'html_body' => '<p style="margin:0 0 12px;color:#475569;line-height:1.8;">' .
                t('notification_email.default_greeting') . '</p>' .
                '<h2 style="margin:0 0 14px;color:#0f172a;font-size:22px;line-height:1.4;">{{notification_title}}</h2>' .
                '<div style="padding:16px 18px;background-color:#f8fafc;border-left:4px solid #1e9fff;color:#334155;line-height:1.8;">{{notification_content}}</div>' .
                '<p style="margin:22px 0 4px;"><a href="{{action_url}}" style="display:inline-block;padding:11px 22px;background-color:#1e9fff;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:600;">' .
                t('notification_email.view_details') . '</a></p>' .
                '<p style="margin:18px 0 0;color:#94a3b8;font-size:12px;">' . t('notification_email.sent_at') . '：{{sent_at}}</p>',
            'enabled' => 1,
            'is_custom' => 0,
            'variables' => $event['variables'],
        ];
    }

    private static function event(string $audience, string $labelKey, string $descriptionKey, array $variables): array
    {
        return [
            'audience' => $audience,
            'label_key' => $labelKey,
            'description_key' => $descriptionKey,
            'variables' => array_values(array_unique(array_merge(self::STANDARD_VARIABLES, $variables))),
        ];
    }
}
