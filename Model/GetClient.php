<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Model;

use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\SerializerInterface;

class GetClient
{
    /**
     * @var TeamSystemConfig
     */
    private $teamSystemConfig;

    /**
     * @var CurlInsecure
     */
    private $clientInsecure;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @param TeamSystemConfig $teamSystemConfig
     * @param SerializerInterface $serializer
     * @param CurlInsecure $clientInsecure
     * @param ClientInterface $client
     */
    public function __construct(
        TeamSystemConfig $teamSystemConfig,
        CurlInsecure $clientInsecure,
        ClientInterface $client
    ) {
        $this->teamSystemConfig = $teamSystemConfig;
        $this->clientInsecure = $clientInsecure;
        $this->client = $client;
    }

    /**
     * @return ClientInterface
     */
    public function execute(): ClientInterface
    {
        if ($this->teamSystemConfig->isWebserviceUnsecureCertificate()) {
            return $this->clientInsecure;
        } else {
            return $this->client;
        }
    }
}
