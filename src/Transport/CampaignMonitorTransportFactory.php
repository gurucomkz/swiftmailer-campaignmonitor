<?php
namespace Gurucomkz\SymfonyCampaignMonitor\Transport;

use Gurucomkz\SymfonyCampaignMonitor\Transport\CampaignMonitorTransport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

class CampaignMonitorTransportFactory implements TransportFactoryInterface
{
    public function create(Dsn $dsn): TransportInterface
    {
        $clientId = $dsn->getUser();
        $apiKey = $dsn->getPassword();
        $domain = $dsn->getHost();
        if ($domain === 'default' || empty($domain)) {
            $domain = 'api.createsend.com';
        }
        return new CampaignMonitorTransport($clientId, $apiKey, $domain);
    }

    public function supports(Dsn $dsn): bool
    {
        return 'campaignmonitor' === $dsn->getScheme();
    }
}