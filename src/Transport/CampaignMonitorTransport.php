<?php
namespace Gurucomkz\SymfonyCampaignMonitor\Transport;

use CS_REST_Transactional_ClassicEmail;
use RuntimeException;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class CampaignMonitorTransport extends AbstractTransport
{
    private $client;

    public function __construct(
        private string $clientId, 
        private string $apiKey, 
        private string $domain = 'api.createsend.com'
    ) {
        $this->client = new CS_REST_Transactional_ClassicEmail(
            $apiKey, 
            $clientId,
            'https',
            CS_REST_LOG_NONE,
            $domain
        );
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        try {
            $email = MessageConverter::toEmail($message->getOriginalMessage());
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('Unable to send message with the "%s" transport: ', __CLASS__).$e->getMessage(), 0, $e);
        }

        $payload = $this->getPayload($email, $message->getEnvelope());

        $result = $this->client->send($payload, null, 'No');

        if(!$result->was_successful()) {
            throw new \Exception('Failed to send email');
        }
    }

    public function __toString(): string
    {
        return \sprintf('campaignmonitor://%s', $this->clientId);
    }

    protected function getPayload(Email $email, Envelope $envelope)
    {
        $payload = [
            'Html' => $email->getHtmlBody(),
            'Text' => $email->getTextBody(),
            'Subject' => $email->getSubject(),
            'From' => $envelope->getSender()->getAddress(),
            'ReplyTo' => $email->getReplyTo()[0]->getAddress(),
        ];

        $replyTo = $email->getReplyTo();
        if (count($replyTo) > 0) {
            $payload['ReplyTo'] = $replyTo[0]->getAddress();
        }

        $payload = array_merge($payload, $this->getRecipientsPayload($email, $envelope));

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();

            $att = [
                'Content' => $attachment->bodyToString(),
                'Type' => $headers->get('Content-Type')->getBody(),
            ];

            if ($name = $headers->getHeaderParameter('Content-Disposition', 'name')) {
                $att['Name'] = $name;
            }

            $payload['Attachments'][] = $att;
        }

        return $payload;
    }

    private function getRecipientsPayload(Email $email, Envelope $envelope): array
    {
        $recipients = [];
        foreach ($envelope->getRecipients() as $recipient) {
            $type = 'To';
            if (\in_array($recipient, $email->getBcc(), true)) {
                $type = 'BCC';
            } elseif (\in_array($recipient, $email->getCc(), true)) {
                $type = 'CC';
            }

            if ($type === 'To') {
                $recipients[$type] = $recipient->getAddress();
            } else {
                if (!$recipients[$type]) {
                    $recipients[$type] = [];
                }
                $recipients[$type][] = $recipient->getAddress();
            }
        }

        return $recipients;
    }
}