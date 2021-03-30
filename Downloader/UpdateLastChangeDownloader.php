<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Downloader;

use DateTime;
use Exception;
use Monolog\Logger;
use Websolute\TransporterActivity\Model\ActivityRepository;
use Websolute\TransporterBase\Api\DownloaderInterface;
use Websolute\TransporterBase\Api\TransporterConfigInterface;
use Websolute\TransporterBase\Exception\TransporterException;
use Websolute\TransporterTeamSystemAdaptor\Model\DateTimeFormat;
use Websolute\TransporterTeamSystemAdaptor\Model\GetLastChangeDateTimeByActivityType;
use Websolute\TransporterTeamSystemAdaptor\Model\SetLastChangeDateTimeByActivityType;

class UpdateLastChangeDownloader implements DownloaderInterface
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var TransporterConfigInterface
     */
    private $config;

    /**
     * @var ActivityRepository
     */
    private $activityRepository;

    /**
     * @var SetLastChangeDateTimeByActivityType
     */
    private $setLastChangeDateTimeByActivityType;

    /**
     * @param Logger $logger
     * @param TransporterConfigInterface $config
     * @param ActivityRepository $activityRepository
     * @param SetLastChangeDateTimeByActivityType $setLastChangeDateTimeByActivityType
     */
    public function __construct(
        Logger $logger,
        TransporterConfigInterface $config,
        ActivityRepository $activityRepository,
        SetLastChangeDateTimeByActivityType $setLastChangeDateTimeByActivityType
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->activityRepository = $activityRepository;
        $this->setLastChangeDateTimeByActivityType = $setLastChangeDateTimeByActivityType;
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     * @throws TransporterException
     */
    public function execute(int $activityId, string $downloaderType): void
    {
        $this->logger->info(__(
            'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ START',
            $activityId,
            $downloaderType
        ));

        try {
            $activity = $this->activityRepository->getById($activityId);
            $activityType = $activity->getType();
            $key = GetLastChangeDateTimeByActivityType::LAST_CHANGE_TOKEN . '_' . $activityType;
            $lastChangeString = $activity->getExtra()->getData($key);

            if ($lastChangeString) {
                $lastChange = DateTime::createFromFormat(DateTimeFormat::TEAMSYSTEM_FORMAT, $lastChangeString);
                $this->setLastChangeDateTimeByActivityType->execute($activityType, $lastChange);
            }
        } catch (Exception $e) {
            $this->logger->error(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                $e->getMessage()
            ));

            if (!$this->config->continueInCaseOfErrors()) {
                throw new TransporterException(__(
                    'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ END ~ Because of continueInCaseOfErrors = false',
                    $activityId,
                    $downloaderType
                ));
            }
        }

        $this->logger->info(__(
            'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ END',
            $activityId,
            $downloaderType
        ));
    }
}
