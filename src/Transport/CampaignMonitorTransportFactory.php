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

        // createsend hadn't upgraded the connector to the latest php
        // so we need to suppress the error here
        $old_error_reporting = error_reporting();
        error_reporting($old_error_reporting & ~E_DEPRECATED);
        $transport = new CampaignMonitorTransport($clientId, $apiKey, $domain);
        error_reporting($old_error_reporting);

        return $transport;
    }

    public function supports(Dsn $dsn): bool
    {
        return 'campaignmonitor' === $dsn->getScheme();
    }
}
