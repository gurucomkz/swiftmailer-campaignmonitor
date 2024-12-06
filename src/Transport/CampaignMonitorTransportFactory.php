<?php
namespace Gurucomkz\SymfonyCampaignMonitor\Transport;

use Gurucomkz\SymfonyCampaignMonitor\Transport\CampaignMonitorTransport;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

class CampaignMonitorTransportFactory implements TransportFactoryInterface
{
    protected $dispatcher;
    protected $logger;

    public function __construct(?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
    }

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
        $transport = new CampaignMonitorTransport($clientId, $apiKey, $domain, $this->dispatcher, $this->logger);
        error_reporting($old_error_reporting);

        return $transport;
    }

    public function supports(Dsn $dsn): bool
    {
        return 'campaignmonitor' === $dsn->getScheme();
    }
}
