<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Model;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Webapi\Rest\Request;
use Websolute\TransporterBase\Exception\TransporterException;
use Zend\Http\Response;

class ClientManager
{
    /**
     * @var GetClient
     */
    private $getClient;

    /**
     * @var GetAccessToken
     */
    private $getAccessToken;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @param GetClient $getClient
     * @param GetAccessToken $getAccessToken
     */
    public function __construct(
        GetClient $getClient,
        GetAccessToken $getAccessToken
    ) {
        $this->getClient = $getClient;
        $this->getAccessToken = $getAccessToken;
    }

    /**
     * @param string $method
     * @param string $url
     * @param null $body
     * @throws AlreadyExistsException
     * @throws TransporterException
     */
    public function call(string $method, string $url, $body = null)
    {
        switch ($method) {
            case Request::HTTP_METHOD_GET:
                $this->getClient()->get($url);
                break;
            case Request::HTTP_METHOD_POST:
                $this->getClient()->post($url, $body);
                break;
        }
    }

    /**
     * @return ClientInterface
     * @throws AlreadyExistsException
     * @throws TransporterException
     */
    public function getClient(): ClientInterface
    {
        if (!isset($this->client)) {
            $this->client = $this->getClient->execute();
            $accessToken = $this->getAccessToken->execute();

            $this->client->addHeader('Authorization', 'Bearer ' . $accessToken);
            $this->client->addHeader('Accept', 'application/json');
            $this->client->addHeader('Content-Type', 'application/json');
        }
        return $this->client;
    }

    /**
     * @return string
     * @throws AlreadyExistsException
     * @throws TransporterException
     */
    public function getBody(): string
    {
        $body = $this->getClient()->getBody();

        if ($this->getClient()->getStatus() === Response::STATUS_CODE_401) {
            $this->getClient()->removeHeader('Authorization');
            $accessToken = $this->getAccessToken->execute(true);
            $this->client->addHeader('Authorization', 'Bearer ' . $accessToken);
            $body = $this->getClient()->getBody();
        }

        $headers = $this->getHeaders();

        if (array_key_exists('Content-Encoding', $headers) &&
            (strpos($headers['Content-Encoding'], 'gzip') !== false)
        ) {
            $body = gzdecode($body);
        }

        return $body;
    }

    /**
     * @return array
     * @throws AlreadyExistsException
     * @throws TransporterException
     */
    public function getHeaders(): array
    {
        return $this->getClient()->getHeaders();
    }
}
