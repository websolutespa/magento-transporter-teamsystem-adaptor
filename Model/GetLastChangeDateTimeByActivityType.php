<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Model;

use DateTime;
use Magento\Framework\Stdlib\DateTime as MagentoDateTime;
use Websolute\TransporterTeamSystemAdaptor\Model\ResourceModel\AlyanteDataResourceModel;

class GetLastChangeDateTimeByActivityType
{
    const LAST_CHANGE_TOKEN = 'last_change';

    /**
     * @var TeamSystemConfig
     */
    private $teamsystemConfig;

    /**
     * @var AlyanteDataModelFactory
     */
    private $alyanteDataModelFactory;

    /**
     * @var AlyanteDataResourceModel
     */
    private $alyanteDataResourceModel;

    /**
     * @param TeamSystemConfig $teamsystemConfig
     * @param AlyanteDataModelFactory $alyanteDataModelFactory
     * @param AlyanteDataResourceModel $alyanteDataResourceModel
     */
    public function __construct(
        TeamSystemConfig $teamsystemConfig,
        AlyanteDataModelFactory $alyanteDataModelFactory,
        AlyanteDataResourceModel $alyanteDataResourceModel
    ) {
        $this->teamsystemConfig = $teamsystemConfig;
        $this->alyanteDataModelFactory = $alyanteDataModelFactory;
        $this->alyanteDataResourceModel = $alyanteDataResourceModel;
    }

    /**
     * @param string $activityType
     * @return DateTime
     */
    public function execute(string $activityType): DateTime
    {
        $alyanteDataLastChange = $this->alyanteDataModelFactory->create();
        $key = self::LAST_CHANGE_TOKEN . '_' . $activityType;
        $this->alyanteDataResourceModel->load($alyanteDataLastChange, $key, AlyanteDataModel::KEY);

        $lastChangeString = $alyanteDataLastChange->getValue();
        if ($lastChangeString) {
            $lastChangeDateTime = DateTime::createFromFormat(MagentoDateTime::DATETIME_PHP_FORMAT, $lastChangeString);
        } else {
            $lastChangeDateTime = $this->teamsystemConfig->getFallbackLastChangeDateTime();
        }

        return $lastChangeDateTime;
    }
}
