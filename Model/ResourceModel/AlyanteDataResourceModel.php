<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AlyanteDataResourceModel extends AbstractDb
{
    const TRANSPORTER_ALYANTE_DATA_TABLE_NAME = 'transporter_alyante_data';

    protected function _construct()
    {
        $this->_init(self::TRANSPORTER_ALYANTE_DATA_TABLE_NAME, 'id');
    }
}
