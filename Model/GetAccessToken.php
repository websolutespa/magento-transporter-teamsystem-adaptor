<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Model;

use DateInterval;
use DateTime;
use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Monolog\Logger;
use Websolute\TransporterBase\Exception\TransporterException;
use Websolute\TransporterTeamSystemAdaptor\Model\AccessToken\Login;
use Websolute\TransporterTeamSystemAdaptor\Model\AccessToken\Refresh;
use Websolute\TransporterTeamSystemAdaptor\Model\AccessToken\Validate;
use Websolute\TransporterTeamSystemAdaptor\Model\ResourceModel\AlyanteDataResourceModel;

class GetAccessToken
{
    const EXPIRATION_TOKEN = 'expiration_token';
    const REFRESH_TOKEN = 'refresh_token';
    const ACCESS_TOKEN = 'access_token';
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var AlyanteDataResourceModel
     */
    private $alyanteDataResourceModel;

    /**
     * @var AlyanteDataModelFactory
     */
    private $alyanteDataModelFactory;

    /**
     * @var TeamSystemConfig
     */
    private $teamSystemConfig;

    /**
     * @var AccessToken\Login
     */
    private $login;

    /**
     * @var AccessToken\Validate
     */
    private $validate;

    /**
     * @var Refresh
     */
    private $refresh;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param Logger $logger
     * @param AlyanteDataResourceModel $alyanteDataResourceModel
     * @param AlyanteDataModelFactory $alyanteDataModelFactory
     * @param TeamSystemConfig $teamSystemConfig
     * @param ClientInterface $client
     * @param AccessToken\Login $login
     * @param Validate $validate
     * @param Refresh $refresh
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Logger $logger,
        AlyanteDataResourceModel $alyanteDataResourceModel,
        AlyanteDataModelFactory $alyanteDataModelFactory,
        TeamSystemConfig $teamSystemConfig,
        ClientInterface $client,
        Login $login,
        Validate $validate,
        Refresh $refresh,
        SerializerInterface $serializer
    ) {
        $this->logger = $logger;
        $this->alyanteDataResourceModel = $alyanteDataResourceModel;
        $this->alyanteDataModelFactory = $alyanteDataModelFactory;
        $this->teamSystemConfig = $teamSystemConfig;
        $this->client = $client;
        $this->login = $login;
        $this->validate = $validate;
        $this->refresh = $refresh;
        $this->serializer = $serializer;
    }

    /**
     * @param bool $force
     * @return string
     * @throws AlreadyExistsException
     * @throws TransporterException
     */
    public function execute(bool $force = false): string
    {
        $alyanteDataExpirationToken = $this->alyanteDataModelFactory->create();
        $this->alyanteDataResourceModel->load($alyanteDataExpirationToken, self::EXPIRATION_TOKEN, AlyanteDataModel::KEY);
        $expirationValue = $alyanteDataExpirationToken->getValue();

        if ($force || !$expirationValue) {
            $this->createNewToken();
        } else {
            if ($this->isExpired($expirationValue)) {
                $this->refreshToken();
            }
        }

        return $this->getCurrentToken();
    }

    /**
     * @throws AlreadyExistsException
     * @throws TransporterException
     */
    private function createNewToken()
    {
        $data = $this->login->execute();
        $this->setDataFromResponse($data);
    }

    /**
     * @param array $data
     * @throws TransporterException|AlreadyExistsException
     */
    private function setDataFromResponse(array $data)
    {
        if (!array_key_exists(self::ACCESS_TOKEN, $data)) {
            throw new TransporterException(__(
                'Missing access_token param from webservice response ~ response:%1',
                $this->serializer->serialize($data)
            ));
        }
        $accessToken = $data[self::ACCESS_TOKEN];
        $alyanteDataAccessToken = $this->alyanteDataModelFactory->create();
        $this->alyanteDataResourceModel->load($alyanteDataAccessToken, self::ACCESS_TOKEN, AlyanteDataModel::KEY);
        $alyanteDataAccessToken->setKey(self::ACCESS_TOKEN);
        $alyanteDataAccessToken->setValue($accessToken);

        if (!array_key_exists(self::REFRESH_TOKEN, $data)) {
            throw new TransporterException(__(
                'Missing refresh_token param from webservice response ~ response:%1',
                $this->serializer->serialize($data)
            ));
        }
        $refreshToken = $data[self::REFRESH_TOKEN];
        $alyanteDataRefreshToken = $this->alyanteDataModelFactory->create();
        $this->alyanteDataResourceModel->load($alyanteDataRefreshToken, self::REFRESH_TOKEN, AlyanteDataModel::KEY);
        $alyanteDataRefreshToken->setKey(self::REFRESH_TOKEN);
        $alyanteDataRefreshToken->setValue($refreshToken);

        if (!array_key_exists('expires_in', $data)) {
            throw new TransporterException(__(
                'Missing expires_in param from webservice response ~ response:%1',
                $this->serializer->serialize($data)
            ));
        }
        $expiresIn = (int)$data['expires_in'];
        $expire = new DateTime();
        try {
            $expire->add(new DateInterval('PT' . $expiresIn . 'S'));
        } catch (Exception $e) {
            $expire->add(new DateInterval('PT300S'));
        }
        $alyanteDataExpirationToken = $this->alyanteDataModelFactory->create();
        $this->alyanteDataResourceModel->load($alyanteDataExpirationToken, self::EXPIRATION_TOKEN, AlyanteDataModel::KEY);
        $alyanteDataExpirationToken->setKey(self::EXPIRATION_TOKEN);
        $alyanteDataExpirationToken->setValue((string)$expire->getTimestamp());

        $this->alyanteDataResourceModel->save($alyanteDataAccessToken);
        $this->alyanteDataResourceModel->save($alyanteDataRefreshToken);
        $this->alyanteDataResourceModel->save($alyanteDataExpirationToken);
    }

    /**
     * @throws AlreadyExistsException
     * @throws TransporterException
     */
    private function refreshToken()
    {
        $alyanteDataRefreshToken = $this->alyanteDataModelFactory->create();
        $this->alyanteDataResourceModel->load($alyanteDataRefreshToken, self::REFRESH_TOKEN, AlyanteDataModel::KEY);
        $refreshToken = $alyanteDataRefreshToken->getValue();

        if ($refreshToken) {
            $data = $this->refresh->execute($refreshToken);
            $this->setDataFromResponse($data);
        } else {
            $this->createNewToken();
        }
    }

    /**
     * @return string
     */
    private function getCurrentToken(): string
    {
        $alyanteDataAccessToken = $this->alyanteDataModelFactory->create();
        $this->alyanteDataResourceModel->load($alyanteDataAccessToken, self::ACCESS_TOKEN, AlyanteDataModel::KEY);
        return (string)$alyanteDataAccessToken->getValue();
    }

    /**
     * @param $expirationValue
     * @return bool
     */
    private function isExpired($expirationValue): bool
    {
        $expiration = new DateTime();
        $expiration->setTimestamp((int)$expirationValue);
        $now = new DateTime();
        $now->sub(new DateInterval('PT120S'));
        return $now > $expiration;
    }
}
