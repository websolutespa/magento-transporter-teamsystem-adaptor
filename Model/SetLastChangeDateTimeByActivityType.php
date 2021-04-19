<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Model;

use DateInterval;
use DateTime;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Stdlib\DateTime as MagentoDateTime;
use Websolute\TransporterTeamSystemAdaptor\Model\ResourceModel\AlyanteDataResourceModel;

class SetLastChangeDateTimeByActivityType
{
    /**
     * @var AlyanteDataModelFactory
     */
    private $alyanteDataModelFactory;

    /**
     * @var AlyanteDataResourceModel
     */
    private $alyanteDataResourceModel;

    /**
     * @param AlyanteDataModelFactory $alyanteDataModelFactory
     * @param AlyanteDataResourceModel $alyanteDataResourceModel
     */
    public function __construct (
        AlyanteDataModelFactory $alyanteDataModelFactory,
        AlyanteDataResourceModel $alyanteDataResourceModel
    ) {
        $this->alyanteDataModelFactory = $alyanteDataModelFactory;
        $this->alyanteDataResourceModel = $alyanteDataResourceModel;
    }

    /**
     * @param string $activityType
     * @param DateTime $lastChange
     * @throws AlreadyExistsException
     */
    public function execute (string $activityType, DateTime $lastChange)
    {
        $lastChange->add(new DateInterval('PT1S'));

        $alyanteDataLastChange = $this->alyanteDataModelFactory->create();
        $key = GetLastChangeDateTimeByActivityType::LAST_CHANGE_TOKEN . '_' . $activityType;
        $this->alyanteDataResourceModel->load($alyanteDataLastChange, $key, AlyanteDataModel::KEY);

        $alyanteDataLastChange->setKey($key);
        $alyanteDataLastChange->setValue($lastChange->format(MagentoDateTime::DATETIME_PHP_FORMAT));
        $this->alyanteDataResourceModel->save($alyanteDataLastChange);
    }
}
