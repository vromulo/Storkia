<?php

$configuredHost = env('MAIL_HOST', '127.0.0.1');
$configuredMailer = env('MAIL_MAILER', 'log');

if (
    in_array($configuredMailer, ['log', 'array', ''], true)
    && ! empty($configuredHost)
    && ! in_array(strtolower(trim((string) $configuredHost)), ['127.0.0.1', 'localhost', 'mailpit'], true)
) {
    $configuredMailer = 'smtp';
}

return [

    'default' => $configuredMailer,

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => $configuredHost,
            'port' => (int) env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => (int) env('MAIL_TIMEOUT', 15),
            'local_domain' => env(
                'MAIL_EHLO_DOMAIN',
                parse_url((string) env('APP_URL', 'http://storkia.shop'), PHP_URL_HOST) ?: 'storkia.shop'
            ),
            'verify_peer' => (bool) env('MAIL_VERIFY_PEER', false),
            'stream' => [
                'ssl' => [
                    'allow_self_signed' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ],
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'team@storkia.shop'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Storkia')),
    ],

];