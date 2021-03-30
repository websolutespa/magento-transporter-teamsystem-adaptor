<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Downloader;

use DateTime;
use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\Rest\Request;
use Monolog\Logger;
use Websolute\TransporterActivity\Api\ActivityRepositoryInterface;
use Websolute\TransporterActivity\Api\Data\ActivityInterface;
use Websolute\TransporterBase\Api\DownloaderInterface;
use Websolute\TransporterBase\Exception\TransporterException;
use Websolute\TransporterEntity\Api\Data\EntityInterface;
use Websolute\TransporterEntity\Model\EntityModel;
use Websolute\TransporterEntity\Model\EntityModelFactory;
use Websolute\TransporterEntity\Model\EntityRepository;
use Websolute\TransporterTeamSystemAdaptor\Api\CallConfigInterface;
use Websolute\TransporterTeamSystemAdaptor\Api\TeamSystemParamsInterface;
use Websolute\TransporterTeamSystemAdaptor\Model\ClientManager;
use Websolute\TransporterTeamSystemAdaptor\Model\DateTimeFormat;
use Websolute\TransporterTeamSystemAdaptor\Model\GetAccessToken;
use Websolute\TransporterTeamSystemAdaptor\Model\GetLastChangeDateTimeByActivityType;
use Websolute\TransporterTeamSystemAdaptor\Model\TeamSystemConfig;
use Zend\Http\Response;

class TeamSystemWebJsonDownloader implements DownloaderInterface
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
    protected $config;

    /**
     * @var array
     */
    private $identifiers;

    /**
     * @var array
     */
    protected $params;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var EntityModelFactory
     */
    private $entityModelFactory;

    /**
     * @var EntityRepository
     */
    private $entityRepository;

    /**
     * @var ActivityRepositoryInterface
     */
    protected $activityRepository;

    /**
     * @var GetAccessToken
     */
    private $getAccessToken;

    /**
     * @var GetLastChangeDateTimeByActivityType
     */
    private $getLastChangeDateTimeByActivityType;

    /**
     * @var DateTime
     */
    private $lastChange;

    /**
     * @var bool
     */
    private $ignoreLastChange;

    /**
     * @var DateTime
     */
    private $startsAt;

    /**
     * @var ClientManager
     */
    private $clientManager;

    /**
     * @param Logger $logger
     * @param TeamSystemConfig $teamSystemConfig
     * @param CallConfigInterface $config
     * @param TeamSystemParamsInterface $params
     * @param SerializerInterface $serializer
     * @param EntityModelFactory $entityModelFactory
     * @param EntityRepository $entityRepository
     * @param ActivityRepositoryInterface $activityRepository
     * @param GetLastChangeDateTimeByActivityType $getLastChangeDateTimeByActivityType
     * @param GetAccessToken $getAccessToken
     * @param ClientManager $clientManager
     * @param bool $ignoreLastChange
     * @param string $identifier
     * @param array $identifiers
     */
    public function __construct(
        Logger $logger,
        TeamSystemConfig $teamSystemConfig,
        CallConfigInterface $config,
        TeamSystemParamsInterface $params,
        SerializerInterface $serializer,
        EntityModelFactory $entityModelFactory,
        EntityRepository $entityRepository,
        ActivityRepositoryInterface $activityRepository,
        GetLastChangeDateTimeByActivityType $getLastChangeDateTimeByActivityType,
        GetAccessToken $getAccessToken,
        ClientManager $clientManager,
        bool $ignoreLastChange = false,
        string $identifier = '',
        array $identifiers = []
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->teamSystemConfig = $teamSystemConfig;
        $this->params = $params;
        $this->serializer = $serializer;
        $this->entityModelFactory = $entityModelFactory;
        $this->entityRepository = $entityRepository;
        $this->activityRepository = $activityRepository;
        $this->getLastChangeDateTimeByActivityType = $getLastChangeDateTimeByActivityType;
        $this->getAccessToken = $getAccessToken;
        $this->clientManager = $clientManager;
        $this->ignoreLastChange = $ignoreLastChange;
        $this->identifiers = $identifiers;
        if ($identifier !== '') {
            array_unshift($this->identifiers, $identifier);
        }
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     * @throws TransporterException
     * @throws NoSuchEntityException|AlreadyExistsException
     */
    public function execute(int $activityId, string $downloaderType): void
    {
        $client = $this->clientManager->getClient();

        $activity = $this->activityRepository->getById($activityId);
        $activityType = $activity->getType();

        $webserviceUrl = $this->getWebserviceUrl($activityId, $downloaderType);
        $resourceName = $this->getResourceName($activityId, $downloaderType);
        $method = $this->getMethod($activityId, $downloaderType);
        $urlParams = $this->getUrlParams($activityId, $downloaderType, $activityType, $resourceName);

        $webserviceUrl .= '/' . $resourceName . $urlParams;

        if ($this->teamSystemConfig->isResponseCompressed()) {
            $client->addHeader('Accept-Encoding', 'gzip, compress');
        }

        $timeoutInSeconds = $this->config->getTimeoutInSeconds();
        $client->setTimeout($timeoutInSeconds);

        try {
            $this->logStart($activityId, $downloaderType, $method, $webserviceUrl);
            $this->clientManager->call($method, $webserviceUrl);
            $this->logEnd($activityId, $downloaderType);

            $body = $this->clientManager->getBody();

            if ($client->getStatus() !== Response::STATUS_CODE_200) {
                throw new TransporterException(__(
                    'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ httpBody:%3',
                    $activityId,
                    $downloaderType,
                    $body
                ));
            }
            $rows = $this->serializer->unserialize($body);
        } catch (Exception $e) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                $e->getMessage()
            ));
        }

        $ok = 0;
        $ko = 0;

        foreach ($rows as $row) {
            try {
                $dataOriginal = $this->serializer->serialize($row);

                if (array_key_exists('lastChange', $row)) {
                    $lastChange = $row['lastChange'];
                } else {
                    throw new TransporterException(__(
                        'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error: Missing lastChange value ~ dataOriginal: %3',
                        $activityId,
                        $downloaderType,
                        $dataOriginal
                    ));
                }

                $this->checkLastChange($lastChange);

                $identifier = '';
                foreach ($this->identifiers as $identifierPathString) {
                    $partialIdentifier = $row;
                    $identifierPath = explode('.', $identifierPathString);
                    foreach ($identifierPath as $identifierPathField) {
                        $partialIdentifier = $partialIdentifier[$identifierPathField];
                    }
                    if (is_array($partialIdentifier)) {
                        if ($this->config->continueInCaseOfErrors()) {
                            $this->logger->error(__(
                                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ KO ~ error: Invalid identifier path ~ dataOriginal: %3',
                                $activityId,
                                $downloaderType,
                                $dataOriginal
                            ));
                            $ko++;
                        } else {
                            throw new TransporterException(__(
                                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error: Invalid identifier path ~ dataOriginal: %3',
                                $activityId,
                                $downloaderType,
                                $dataOriginal
                            ));
                        }
                    }
                    $identifier .= $partialIdentifier;
                }

                $entity = $this->getOrCreateEntity($activityId, $identifier, $downloaderType);

                $entity->setDataOriginal($dataOriginal);

                $this->entityRepository->save($entity);
                $ok++;
            } catch (Exception $e) {
                if ($this->config->continueInCaseOfErrors()) {
                    $this->logger->error(__(
                        'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ KO ~ error:%3',
                        $activityId,
                        $downloaderType,
                        $e->getMessage()
                    ));
                    $ko++;
                } else {
                    throw new TransporterException(__(
                        'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ KO ~ error:%3',
                        $activityId,
                        $downloaderType,
                        $e->getMessage()
                    ));
                }
            }
        }

        if (!$this->ignoreLastChange && $ko === 0 && $ok > 0) {
            $this->updateLastChange($activity);
        }

        $this->logger->info(__(
            'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ okCount:%3 koCount:%4',
            $activityId,
            $downloaderType,
            $ok,
            $ko
        ));
    }

    /**
     * @param int $activityId
     * @param string $identifier
     * @param string $downloaderType
     * @return EntityInterface|EntityModel
     * @throws Exception
     */
    private function getOrCreateEntity(int $activityId, string $identifier, string $downloaderType)
    {
        $entity = $this->entityRepository->getByActivityIdAndIdentifierAndType($activityId, $identifier, $downloaderType);
        if ($entity->getId()) {
            return $entity;
        }

        $entity = $this->entityModelFactory->create();
        $entity->setActivityId($activityId);
        $entity->setType($downloaderType);
        $entity->setIdentifier($identifier);
        return $entity;
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     * @return string
     * @throws TransporterException
     */
    public function getWebserviceUrl(int $activityId, string $downloaderType): string
    {
        $webserviceUrl = $this->teamSystemConfig->getWebserviceUrl();

        if (!$webserviceUrl) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                'Missing webservice_url'
            ));
        }

        return $webserviceUrl;
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     * @return string
     * @throws TransporterException
     */
    public function getResourceName(int $activityId, string $downloaderType): string
    {
        $resourceName = $this->params->getResourceName();

        if (!$resourceName) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                'Missing resource name'
            ));
        }

        return $resourceName;
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     * @return string
     * @throws TransporterException
     */
    public function getMethod(int $activityId, string $downloaderType): string
    {
        $method = $this->params->getMethod();

        if (!$method) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                'Missing method'
            ));
        }

        if (!in_array($method, [Request::HTTP_METHOD_GET, Request::HTTP_METHOD_POST])) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                'Invalid method'
            ));
        }

        return $method;
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     * @param string $activityType
     * @param string $resourceName
     * @return string
     * @throws TransporterException
     */
    public function getUrlParams(int $activityId, string $downloaderType, string $activityType, string $resourceName): string
    {
        $lastChangeDateTime = $this->ignoreLastChange ? null : $this->getLastChangeDateTimeByActivityType->execute($activityType);

        $urlParams = $this->params->getUrlParams(
            $lastChangeDateTime,
            $this->config->getSortField($resourceName),
            $this->config->getSortOrder($resourceName)
        );

        if (!$urlParams) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                'Missing method'
            ));
        }

        return $urlParams;
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     * @param string $method
     * @param string $webserviceUrl
     */
    private function logStart(int $activityId, string $downloaderType, string $method, string $webserviceUrl): void
    {
        $this->startsAt = new DateTime('now');
        $this->logger->info(__(
            'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ start calling via %3 at url: %4 at time:%5',
            $activityId,
            $downloaderType,
            $method,
            $webserviceUrl,
            $this->startsAt->format(DateTimeFormat::TEAMSYSTEM_FORMAT)
        ));
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     */
    private function logEnd(int $activityId, string $downloaderType): void
    {
        $endsAt = new DateTime('now');
        $difference = $endsAt->getTimestamp() - $this->startsAt->getTimestamp();
        $this->logger->info(__(
            'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ end at:%3 ~ it takes:%4 seconds',
            $activityId,
            $downloaderType,
            $endsAt->format(DateTimeFormat::TEAMSYSTEM_FORMAT),
            $difference
        ));
    }

    /**
     * @param string $lastChangeString
     */
    protected function checkLastChange(string $lastChangeString)
    {
        $lastChange = DateTime::createFromFormat(DateTimeFormat::TEAMSYSTEM_FORMAT, $lastChangeString);
        if ($this->lastChange < $lastChange) {
            $this->lastChange = $lastChange;
        }
    }

    /**
     * @param ActivityInterface $activity
     */
    protected function updateLastChange(ActivityInterface $activity)
    {
        $key = GetLastChangeDateTimeByActivityType::LAST_CHANGE_TOKEN . '_' . $activity->getType();
        $lastChange = $this->lastChange->format(DateTimeFormat::TEAMSYSTEM_FORMAT);
        $activity->addExtraArray([$key => $lastChange]);
        $this->activityRepository->save($activity);
    }
}
