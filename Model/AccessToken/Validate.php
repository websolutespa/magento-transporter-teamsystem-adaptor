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

class Validate
{
    /** @var int */
    const TIMEOUT = 30;

    /** @var string */
    const ENDPOINT_URI = '/api/validate';

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
     * @param string $accessToken
     * @return array
     * @throws TransporterException
     */
    public function execute(string $accessToken): array
    {
        $client = $this->getClient->execute();

        $webserviceUrl = $this->teamSystemConfig->getWebserviceUrl();
        $webserviceUrl .= self::ENDPOINT_URI;

        $client->addHeader('Accept', 'application/json');
        $client->addHeader('Content-Type', 'application/json');
        $client->setTimeout(self::TIMEOUT);
        $client->addHeader('Authorization', 'Bearer '. $accessToken);
        $client->get($webserviceUrl);

        $body = $client->getBody();

        if ($client->getStatus() === Response::STATUS_CODE_401) {
            return [];
        } elseif ($client->getStatus() !== Response::HTTP_OK) {
            throw new TransporterException(__(
                'Error while login in class:%1 ~ body:%2',
                self::class,
                $body
            ));
        }

        return $this->serializer->unserialize($body);
    }
}
