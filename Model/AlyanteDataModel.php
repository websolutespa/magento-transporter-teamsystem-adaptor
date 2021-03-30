<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Model;

use DateTime;
use Exception;
use Magento\Framework\Model\AbstractExtensibleModel;

class AlyanteDataModel extends AbstractExtensibleModel
{
    const KEY = 'key';
    const VALUE = 'value';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    const CACHE_TAG = 'transporter_alyante_data';
    protected $_cacheTag = 'transporter_alyante_data';
    protected $_eventPrefix = 'transporter_alyante_data';

    /**
     * @return string[]
     */
    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getKey()];
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return (string)$this->getData(self::KEY);
    }

    /**
     * @param string $key
     * @return void
     */
    public function setKey(string $key)
    {
        $this->setData(self::KEY, $key);
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return (string)$this->getData(self::VALUE);
    }

    /**
     * @param string $value
     * @return void
     */
    public function setValue(string $value)
    {
        $this->setData(self::VALUE, $value);
    }

    /**
     * @return DateTime
     * @throws Exception
     */
    public function getCreatedAt(): DateTime
    {
        return new DateTime($this->getData(self::CREATED_AT));
    }

    /**
     * @return DateTime
     * @throws Exception
     */
    public function getUpdatedAt(): DateTime
    {
        return new DateTime($this->getData(self::UPDATED_AT));
    }

    protected function _construct()
    {
        $this->_init(ResourceModel\AlyanteDataResourceModel::class);
    }
}
