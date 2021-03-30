<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Model\AccessToken;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\Response;
use Websolute\TransporterBase\Exception\TransporterException;
use Websolute\TransporterTeamSystemAdaptor\Model\CurlInsecure;
use Websolute\TransporterTeamSystemAdaptor\Model\GetClient;
use Websolute\TransporterTeamSystemAdaptor\Model\TeamSystemConfig;

class Refresh
{
    /** @var int */
    const TIMEOUT = 30;

    /** @var string */
    const ENDPOINT_URI = '/oauth/access_token';

    /**
     * @var TeamSystemConfig
     */
    private $teamSystemConfig;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var GetClient
     */
    private $getClient;

    /**
     * @param TeamSystemConfig $teamSystemConfig
     * @param SerializerInterface $serializer
     * @param GetClient $getClient
     */
    public function __construct(
        TeamSystemConfig $teamSystemConfig,
        SerializerInterface $serializer,
        GetClient $getClient
    ) {
        $this->teamSystemConfig = $teamSystemConfig;
        $this->serializer = $serializer;
        $this->getClient = $getClient;
    }

    /**
     * @param string $refreshToken
     * @return array
     * @throws TransporterException
     */
    public function execute(string $refreshToken): array
    {
        $client = $this->getClient->execute();
        $webserviceUrl = $this->teamSystemConfig->getWebserviceUrl();
        $webserviceUrl .= self::ENDPOINT_URI;

        $client->addHeader('Accept', 'application/json');
        $client->setTimeout(self::TIMEOUT);
        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken
        ];
        $client->post($webserviceUrl, $params);

        $body = $client->getBody();

        if ($client->getStatus() !== Response::STATUS_CODE_100) {
            throw new TransporterException(__(
                'Error while login in class:%1 ~ response code:%2 ~ body:%3',
                self::class,
                $client->getStatus(),
                $body
            ));
        }

        return $this->serializer->unserialize($body);
    }
}
