<?php
namespace Gurucomkz\SymfonyCampaignMonitor\Transport;

use CS_REST_Transactional_ClassicEmail;
use RuntimeException;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
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
            $this->apiKey,
            $this->clientId,
            'https',
            CS_REST_LOG_NONE,
            $this->domain
        );
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();
        if (!($original instanceof Message)) {
            throw new RuntimeException('The CampaignMonitorTransport only supports instances of ' . Message::class . ' as the message being sent.');
        }

        try {
            $email = MessageConverter::toEmail($original);
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('Unable to send message with the "%s" transport: ', __CLASS__) . $e->getMessage(), 0, $e);
        }

        $payload = $this->getPayload($email, $message->getEnvelope());

        // createsend hadn't upgraded the connector to the latest php
        // so we need to suppress the error here
        $old_error_reporting = error_reporting();
        error_reporting($old_error_reporting & ~E_DEPRECATED);
        $result = $this->client->send($payload, null, 'No');
        error_reporting($old_error_reporting);

        if (!$result->was_successful()) {
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
