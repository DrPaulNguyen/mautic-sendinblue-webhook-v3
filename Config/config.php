<?php

return [
    'name'        => 'Sendinblue Webhook',
    'description' => 'Process from Webhook Sendinblue',
    'version'     => '1.0',
    'author'      => 'Paul Nguyen',
    'services'    => [
        'other' => [
            'mautic.transport.sendinblue_api.callback' => [
                'class' => \MauticPlugin\MauticSendinblueBundle\Swiftmailer\Callback\SendinblueApiCallback::class,
                'arguments' => [
                    'mautic.email.model.transport_callback',
                    'monolog.logger.mautic',
                ],
            ],
        ],
    ],
];
