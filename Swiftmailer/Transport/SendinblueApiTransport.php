<?php

namespace MauticPlugin\MauticSendinblueBundle\Swiftmailer\Transport;

use Exception;
use Mautic\EmailBundle\Swiftmailer\Transport\AbstractTokenArrayTransport;
use Mautic\EmailBundle\Swiftmailer\Transport\CallbackTransportInterface;
use Mautic\EmailBundle\Swiftmailer\Transport\TokenTransportInterface;
use MauticPlugin\MauticSendinblueBundle\Swiftmailer\Callback\SendinblueApiCallback;
use SendinBlue\Client\Configuration;
use SendinBlue\Client\Api\SMTPApi;
use SendinBlue\Client\Model\CreateSmtpEmail;
use SendinBlue\Client\Model\SendSmtpEmail;
use SendinBlue\Client\Model\SendSmtpEmailAttachment;
use SendinBlue\Client\Model\SendSmtpEmailReplyTo;
use SendinBlue\Client\Model\SendSmtpEmailSender;
use SendinBlue\Client\Model\SendSmtpEmailTo;
use SendinBlue\Client\Model\SendSmtpEmailCc;
use SendinBlue\Client\Model\SendSmtpEmailBcc;
use SplFileObject;
use Swift_Message;
use Swift_Mime_Message;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Client;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class SendinblueApiTransport.
 */
class SendinblueApiTransport extends AbstractTokenArrayTransport implements \Swift_Transport, TokenTransportInterface, CallbackTransportInterface
{

    /**
     * @var string|null
     */
    protected $apiKey;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var bool
     */
    protected $started = false;

    /**
     * @var SendinblueApiCallback
     */
    protected $sendinblueApiCallback;

    /**
     * SendinblueApiTransport constructor.
     *
     * @param $apiKey
     * @param TranslatorInterface $translator
     * @param SendinblueApiCallback $sendinblueApiCallback
     */
    public function __construct($apiKey, TranslatorInterface $translator, SendinblueApiCallback $sendinblueApiCallback)
    {
        $this->apiKey = $apiKey;
        $this->translator = $translator;
        $this->sendinblueApiCallback = $sendinblueApiCallback;
    }

    /**
     * {@inheritdoc}
     */
    public function getCallbackPath()
    {
        return 'sendinblue_api';
    }

    /**
     * {@inheritdoc}
     */
    public function processCallbackRequest(Request $request)
    {
        $this->sendinblueApiCallback->processCallbackRequest($request);
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxBatchLimit()
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getBatchRecipientCount(Swift_Message $message, $toBeAdded = 1, $type = 'to')
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function start()
    {
        if (empty($this->apiKey)) {
            $this->throwException($this->translator->trans('mautic.email.api_key_required', [], 'validators'));
        }

        $this->started = true;
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null)
    {
        $result = 0;
        $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $this->apiKey);
        $smtpApiInstance = new SMTPApi(new Client(), $config);

        try {
            $smtpEmail = $this->getSendinBlueEmail($message);
            $response = $smtpApiInstance->sendTransacEmail($smtpEmail);

            if ($response instanceof CreateSmtpEmail || $response instanceof SplFileObject) {
                $result = 1;
            }
        } catch (Exception $e) {
            $this->throwException($e->getMessage());
        }

        return $result;
    }

    /**
     * Converts Swift_Mime_Message object into SendSmtpEmail one.
     *
     * @param Swift_Mime_Message $message
     *
     * @return SendSmtpEmail
     *
     * @throws Exception
     */
    protected function getSendinBlueEmail(Swift_Mime_Message $message)
    {
        $data = [];

        $this->message = $message;
        $metadata = $this->getMetadata();
        $mauticTokens = $mergeVars = $mergeVarPlaceholders = [];
        $tokens = [];

        // Sendinblue uses {NAME} for tokens so Mautic's need to be converted.
        if (!empty($metadata)) {
            $metadataSet = reset($metadata);
            $tokens = (!empty($metadataSet['tokens'])) ? $metadataSet['tokens'] : [];
            $mauticTokens = array_keys($tokens);

            $mergeVars = $mergeVarPlaceholders = [];
            foreach ($mauticTokens as $token) {
                $mergeVars[$token] = strtoupper(preg_replace('/[^a-z0-9]+/i', '', $token));
                $mergeVarPlaceholders[$token] = '{'.$mergeVars[$token].'}';
            }
        }

        $message = $this->messageToArray($mauticTokens, $mergeVarPlaceholders, true);

        if (empty($message['subject'])) {
            throw new Exception($this->translator->trans('mautic.email.subject.notblank', [], 'validators'));
        }

        if (empty($message['html'])) {
            throw new Exception($this->translator->trans('mautic.email.html.notblank', [], 'validators'));
        }

        if (isset($message['headers']['X-MC-Tags'])) {
            $data['tags'] = explode(',', $message['headers']['X-MC-Tags']);
        }

        $data['sender'] = new SendSmtpEmailSender([
            'name' => $message['from']['name'],
            'email' => $message['from']['email'],
        ]);

        foreach ($message['recipients']['to'] as $to) {
            $data['to'][] = new SendSmtpEmailTo([
                'name' => $to['name'],
                'email' => $to['email'],
            ]);
        }

        foreach ($message['recipients']['cc'] as $cc) {
            $data['cc'][] = new SendSmtpEmailCc([
                'name' => $cc['name'],
                'email' => $cc['email'],
            ]);
        }

        foreach ($message['recipients']['bcc'] as $bcc) {
            $data['bcc'][] = new SendSmtpEmailBcc([
                'name' => $bcc['name'],
                'email' => $bcc['email'],
            ]);
        }

        if (isset($message['replyTo'])) {
            $data['replyTo'] = new SendSmtpEmailReplyTo([
                'name' => $message['replyTo']['name'],
                'email' => $message['replyTo']['email'],
            ]);
        }

        if (!empty($message['headers'])) {
            $data['headers'] = $message['headers'];
        }

        $attachments = $this->message->getAttachments();
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (stream_is_local($attachment['filePath'])) {
                    $fileContent = file_get_contents($attachment['filePath']);

                    // Breaks current iteration if content of the local file
                    // is wrong.
                    if (!$fileContent) {
                        continue;
                    }

                    $data['attachment'][] = new SendSmtpEmailAttachment([
                        'name' => $attachment['fileName'],
                        'content' => base64_encode($fileContent),
                    ]);
                }
                else {
                    $data['attachment'][] = new SendSmtpEmailAttachment([
                        'name' => $attachment['fileName'],
                        'url' => $attachment['filePath'],
                    ]);
                }
            }
        }

        // Prepares array of tokens to pass them as params.
        foreach ($mergeVars as $mergeVarIndex => $mergeVar) {
            if (isset($tokens[$mergeVarIndex])) {
                $data['params'][$mergeVar] = $tokens[$mergeVarIndex];
            }
        }

        $data['subject'] = $message['subject'];
        $data['htmlContent'] = $message['html'];
        $data['text'] = $message['text'];

        return new SendSmtpEmail($data);
    }

}
