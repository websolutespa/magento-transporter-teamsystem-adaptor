<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Downloader;

use Magento\Framework\Exception\NoSuchEntityException;
use Websolute\TransporterActivity\Api\Data\ActivityInterface;
use Websolute\TransporterBase\Exception\TransporterException;

class TeamSystemWebJsonSpecificDownloader extends TeamSystemWebJsonDownloader
{
    /**
     * @param int $activityId
     * @param string $downloaderType
     * @param string $activityType
     * @param string $resourceName
     * @return string
     * @throws TransporterException|NoSuchEntityException
     */
    public function getUrlParams(int $activityId, string $downloaderType, string $activityType, string $resourceName): string
    {
        $identifier = $this->getIdentifier($activityId);

        $urlParams = $this->params->getUrlParams(
            null,
            $this->config->getSortField($resourceName),
            $this->config->getSortOrder($resourceName)
        );

        $urlParams .= '&codArt=' . $identifier;

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
     * @param string $lastChangeString
     */
    protected function checkLastChange(string $lastChangeString)
    {
    }

    /**
     * @param ActivityInterface $activity
     */
    protected function updateLastChange(ActivityInterface $activity)
    {
    }

    /**
     * @param int $activityId
     * @return string
     * @throws NoSuchEntityException
     */
    private function getIdentifier(int $activityId): string
    {
        $activity = $this->activityRepository->getById($activityId);
        return $activity->getExtra()->getData('data');
    }
}
