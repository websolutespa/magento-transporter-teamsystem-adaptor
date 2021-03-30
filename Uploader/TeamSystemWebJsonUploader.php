<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Uploader;

use DateTime;
use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\Rest\Request;
use Monolog\Logger;
use Websolute\TransporterActivity\Api\ActivityRepositoryInterface;
use Websolute\TransporterBase\Api\UploaderInterface;
use Websolute\TransporterBase\Exception\TransporterException;
use Websolute\TransporterEntity\Model\EntityRepository;
use Websolute\TransporterImporter\Model\DotConvention;
use Websolute\TransporterMagentoOrder\Model\GetOrderToSendCollection;
use Websolute\TransporterTeamSystemAdaptor\Api\CallConfigInterface;
use Websolute\TransporterTeamSystemAdaptor\Api\TeamSystemParamsInterface;
use Websolute\TransporterTeamSystemAdaptor\Model\ClientManager;
use Websolute\TransporterTeamSystemAdaptor\Model\GetAccessToken;
use Websolute\TransporterTeamSystemAdaptor\Model\TeamSystemConfig;
use Zend\Http\Response;

class TeamSystemWebJsonUploader implements UploaderInterface
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var TeamSystemConfig
     */
    private $teamSystemConfig;

    /**
     * @var CallConfigInterface
     */
    private $config;

    /**
     * @var array
     */
    private $params;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var EntityRepository
     */
    private $entityRepository;

    /**
     * @var ActivityRepositoryInterface
     */
    private $activityRepository;

    /**
     * @var GetAccessToken
     */
    private $getAccessToken;

    /**
     * @var DateTime
     */
    private $startsAt;

    /**
     * @var DotConvention
     */
    private $dotConvention;

    /**
     * @var ClientManager
     */
    private $clientManager;

    /**
     * @var array
     */
    private $source;

    /**
     * @param Logger $logger
     * @param TeamSystemConfig $teamSystemConfig
     * @param CallConfigInterface $config
     * @param TeamSystemParamsInterface $params
     * @param SerializerInterface $serializer
     * @param EntityRepository $entityRepository
     * @param ActivityRepositoryInterface $activityRepository
     * @param GetAccessToken $getAccessToken
     * @param DotConvention $dotConvention
     * @param ClientManager $clientManager
     * @param string $source
     */
    public function __construct(
        Logger $logger,
        TeamSystemConfig $teamSystemConfig,
        CallConfigInterface $config,
        TeamSystemParamsInterface $params,
        SerializerInterface $serializer,
        EntityRepository $entityRepository,
        ActivityRepositoryInterface $activityRepository,
        GetAccessToken $getAccessToken,
        DotConvention $dotConvention,
        ClientManager $clientManager,
        string $source
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->teamSystemConfig = $teamSystemConfig;
        $this->params = $params;
        $this->serializer = $serializer;
        $this->entityRepository = $entityRepository;
        $this->activityRepository = $activityRepository;
        $this->getAccessToken = $getAccessToken;
        $this->dotConvention = $dotConvention;
        $this->clientManager = $clientManager;
        $this->source = $source;
    }

    /**
     * @param int $activityId
     * @param string $uploaderType
     * @throws TransporterException
     * @throws NoSuchEntityException|AlreadyExistsException
     */
    public function execute(int $activityId, string $uploaderType): void
    {
        $client = $this->clientManager->getClient();

        $resourceName = $this->getResourceName($activityId, $uploaderType);
        $method = $this->getMethod($activityId, $uploaderType);

        $webserviceUrl = $this->getWebserviceUrl($activityId, $uploaderType);
        $webserviceUrl .= '/' . $resourceName;

        if ($this->teamSystemConfig->isResponseCompressed()) {
            $client->addHeader('Accept-Encoding', 'gzip, compress');
        }

        $timeoutInSeconds = $this->config->getTimeoutInSeconds();
        $client->setTimeout($timeoutInSeconds);

        $allActivityEntities = $this->entityRepository->getAllDataManipulatedByActivityIdGroupedByIdentifier($activityId);

        $ok = 0;
        $ko = 0;

        foreach ($allActivityEntities as $entityIdentifier => $entities) {
            $destinationType = $this->dotConvention->getFirst($this->source);
            $entity = $this->entityRepository->getByActivityIdAndIdentifierAndType($activityId, (string)$entityIdentifier, $destinationType);

            $bodyObject = $this->dotConvention->getValue($entities, $this->source);

            $bodyJson = $this->serializer->serialize($bodyObject);

            try {
                $this->logStart($activityId, $uploaderType);
                $this->clientManager->call($method, $webserviceUrl, $bodyJson);
                $this->logEnd($activityId, $uploaderType);

                $body = $this->clientManager->getBody();

                if ($client->getStatus() !== Response::STATUS_CODE_201) {
                    if ($this->config->continueInCaseOfErrors()) {
                        $this->logger->error(__(
                            'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ KO ~ httpBody:%3',
                            $activityId,
                            $uploaderType,
                            $body
                        ));

                        $ko++;
                        $entity->addExtraArray([
                            GetOrderToSendCollection::TRANSPORTER_EXPORTED => 0,
                            'response' => $body,
                        ]);
                        $this->entityRepository->save($entity);
                        continue;
                    } else {
                        throw new TransporterException(__(
                            'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ ERROR ~ httpBody:%3',
                            $activityId,
                            $uploaderType,
                            $body
                        ));
                    }
                }

                $ok++;
                $entity->addExtraArray([
                    GetOrderToSendCollection::TRANSPORTER_EXPORTED => 1,
                    'response' => $body,
                ]);
                $this->entityRepository->save($entity);
            } catch (Exception $e) {
                throw new TransporterException(__(
                    'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ ERROR ~ error:%3',
                    $activityId,
                    $uploaderType,
                    $e->getMessage()
                ));
            }
        }

        $this->logger->info(__(
            'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ okCount:%3 koCount:%4',
            $activityId,
            $uploaderType,
            $ok,
            $ko
        ));
    }

    /**
     * @param int $activityId
     * @param string $uploaderType
     * @return string
     * @throws TransporterException
     */
    public function getResourceName(int $activityId, string $uploaderType): string
    {
        $resourceName = $this->params->getResourceName();

        if (!$resourceName) {
            throw new TransporterException(__(
                'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $uploaderType,
                'Missing resource name'
            ));
        }

        return $resourceName;
    }

    /**
     * @param int $activityId
     * @param string $uploaderType
     * @return string
     * @throws TransporterException
     */
    public function getMethod(int $activityId, string $uploaderType): string
    {
        $method = $this->params->getMethod();

        if (!$method) {
            throw new TransporterException(__(
                'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $uploaderType,
                'Missing method'
            ));
        }

        if (!in_array($method, [Request::HTTP_METHOD_GET, Request::HTTP_METHOD_POST])) {
            throw new TransporterException(__(
                'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $uploaderType,
                'Invalid method'
            ));
        }

        return $method;
    }

    /**
     * @param int $activityId
     * @param string $uploaderType
     * @return string
     * @throws TransporterException
     */
    public function getWebserviceUrl(int $activityId, string $uploaderType): string
    {
        $webserviceUrl = $this->teamSystemConfig->getWebserviceUrl();

        if (!$webserviceUrl) {
            throw new TransporterException(__(
                'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $uploaderType,
                'Missing webservice_url'
            ));
        }

        return $webserviceUrl;
    }

    /**
     * @param int $activityId
     * @param string $uploaderType
     */
    private function logStart(int $activityId, string $uploaderType): void
    {
        $this->startsAt = new DateTime('now');
        $this->logger->info(__(
            'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ start at:%3',
            $activityId,
            $uploaderType,
            $this->startsAt->format(DateTime::RFC3339)
        ));
    }

    /**
     * @param int $activityId
     * @param string $uploaderType
     */
    private function logEnd(int $activityId, string $uploaderType): void
    {
        $endsAt = new DateTime('now');
        $difference = $endsAt->getTimestamp() - $this->startsAt->getTimestamp();
        $this->logger->info(__(
            'activityId:%1 ~ Uploader ~ uploaderType:%2 ~ end at:%3 ~ it takes:%4 seconds',
            $activityId,
            $uploaderType,
            $endsAt->format(DateTime::RFC3339),
            $difference
        ));
    }
}
