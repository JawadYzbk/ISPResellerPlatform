<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class MessageTemplateProvisioner
{
    /** @var list<string> */
    private const CHANNELS = ['whatsapp', 'sms', 'email'];

    /** @var list<string> */
    private const SUPPORTED_LOCALES = ['en', 'ar', 'fr'];

    /**
     * @var array<string, array{variables: list<string>, subjects: array<string, string>, bodies: array<string, string>}>
     */
    private const DEFAULT_TEMPLATES = [
        'customer.welcome' => [
            'variables' => ['customer_name', 'customer_code'],
            'subjects' => [
                'en' => 'Welcome to your internet service',
                'ar' => 'مرحباً بك في خدمة الإنترنت',
                'fr' => 'Bienvenue dans votre service Internet',
            ],
            'bodies' => [
                'en' => 'Welcome {{ customer_name }}. Your customer code is {{ customer_code }}.',
                'ar' => 'مرحباً {{ customer_name }}. رمز المشترك الخاص بك هو {{ customer_code }}.',
                'fr' => 'Bienvenue {{ customer_name }}. Votre code client est {{ customer_code }}.',
            ],
        ],
        'payment.receipt' => [
            'variables' => ['customer_name', 'receipt_number', 'amount', 'currency'],
            'subjects' => [
                'en' => 'Payment receipt',
                'ar' => 'إيصال دفع',
                'fr' => 'Reçu de paiement',
            ],
            'bodies' => [
                'en' => 'Payment received from {{ customer_name }}. Receipt {{ receipt_number }} for {{ amount }} {{ currency }}.',
                'ar' => 'تم استلام الدفعة من {{ customer_name }}. رقم الإيصال {{ receipt_number }} بقيمة {{ amount }} {{ currency }}.',
                'fr' => 'Paiement reçu de {{ customer_name }}. Reçu {{ receipt_number }} de {{ amount }} {{ currency }}.',
            ],
        ],
        'service.expiry_reminder' => [
            'variables' => ['customer_name', 'service_username', 'expiry_date', 'days_remaining'],
            'subjects' => [
                'en' => 'Service expiry reminder',
                'ar' => 'تذكير بانتهاء الخدمة',
                'fr' => 'Rappel d’expiration du service',
            ],
            'bodies' => [
                'en' => 'Reminder for {{ customer_name }}: service {{ service_username }} expires on {{ expiry_date }} ({{ days_remaining }} days remaining).',
                'ar' => 'تذكير لـ {{ customer_name }}: تنتهي الخدمة {{ service_username }} بتاريخ {{ expiry_date }} (متبقٍ {{ days_remaining }} أيام).',
                'fr' => 'Rappel pour {{ customer_name }} : le service {{ service_username }} expire le {{ expiry_date }} ({{ days_remaining }} jours restants).',
            ],
        ],
        'service.suspended' => [
            'variables' => ['customer_name', 'service_username', 'reason'],
            'subjects' => [
                'en' => 'Service suspended',
                'ar' => 'تم تعليق الخدمة',
                'fr' => 'Service suspendu',
            ],
            'bodies' => [
                'en' => 'Service {{ service_username }} for {{ customer_name }} was suspended because {{ reason }}.',
                'ar' => 'تم تعليق الخدمة {{ service_username }} الخاصة بـ {{ customer_name }} بسبب {{ reason }}.',
                'fr' => 'Le service {{ service_username }} de {{ customer_name }} a été suspendu pour la raison suivante : {{ reason }}.',
            ],
        ],
        'service.reactivated' => [
            'variables' => ['customer_name', 'service_username'],
            'subjects' => [
                'en' => 'Service reactivated',
                'ar' => 'تمت إعادة تفعيل الخدمة',
                'fr' => 'Service réactivé',
            ],
            'bodies' => [
                'en' => 'Good news, {{ customer_name }}: service {{ service_username }} is active again.',
                'ar' => 'خبر سار يا {{ customer_name }}: تمت إعادة تفعيل الخدمة {{ service_username }}.',
                'fr' => 'Bonne nouvelle, {{ customer_name }} : le service {{ service_username }} est à nouveau actif.',
            ],
        ],
        'outage.notice' => [
            'variables' => ['customer_name', 'incident_title', 'incident_description', 'severity'],
            'subjects' => [
                'en' => 'Service notice',
                'ar' => 'إشعار بالخدمة',
                'fr' => 'Avis de service',
            ],
            'bodies' => [
                'en' => '{{ incident_title }}: {{ incident_description }}',
                'ar' => '{{ incident_title }}: {{ incident_description }}',
                'fr' => '{{ incident_title }} : {{ incident_description }}',
            ],
        ],
        'outage.resolved' => [
            'variables' => ['customer_name', 'incident_title', 'incident_description', 'severity'],
            'subjects' => [
                'en' => 'Service restored',
                'ar' => 'تمت استعادة الخدمة',
                'fr' => 'Service rétabli',
            ],
            'bodies' => [
                'en' => 'Resolved: {{ incident_title }}. {{ incident_description }}',
                'ar' => 'تم الحل: {{ incident_title }}. {{ incident_description }}',
                'fr' => 'Résolu : {{ incident_title }}. {{ incident_description }}',
            ],
        ],
        'service.quota_warning' => [
            'variables' => ['customer_name', 'service_username', 'quota_percent', 'used_bytes', 'quota_bytes'],
            'subjects' => [
                'en' => 'Usage alert',
                'ar' => 'تنبيه استخدام',
                'fr' => 'Alerte d’utilisation',
            ],
            'bodies' => [
                'en' => 'Usage alert for {{ customer_name }}: service {{ service_username }} reached {{ quota_percent }}% of its allowance.',
                'ar' => 'تنبيه استخدام لـ {{ customer_name }}: بلغت الخدمة {{ service_username }} نسبة {{ quota_percent }}% من حصتها.',
                'fr' => 'Alerte pour {{ customer_name }} : le service {{ service_username }} a atteint {{ quota_percent }}% de son quota.',
            ],
        ],
    ];

    public function provision(Tenant $tenant): void
    {
        app(Tenancy::class)->run($tenant, function () use ($tenant): void {
            $tenantId = app(Tenancy::class)->requireId();
            $timestamp = now();
            $templates = [];
            $locale = strtolower((string) ($tenant->locale ?: 'en'));
            $locale = in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'en';

            foreach (self::DEFAULT_TEMPLATES as $key => $definition) {
                foreach (self::CHANNELS as $channel) {
                    $templates[] = [
                        'tenant_id' => $tenantId,
                        'key' => $key,
                        'channel' => $channel,
                        'locale' => $locale,
                        'subject' => $channel === 'email' ? $definition['subjects'][$locale] : null,
                        'body' => $definition['bodies'][$locale],
                        'variables' => json_encode($definition['variables'], JSON_THROW_ON_ERROR),
                        'is_active' => true,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            DB::table('message_templates')->insertOrIgnore($templates);
        });
    }
}
