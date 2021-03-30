<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Api;

use Websolute\TransporterBase\Api\TransporterConfigInterface;

interface CallConfigInterface extends TransporterConfigInterface
{
    /**
     * @return int
     */
    public function getBunchSize(): int;

    /**
     * @return int
     */
    public function getTimeoutInSeconds(): int;

    /**
     * @param string $endPoint
     * @return string
     */
    public function getSortField(string $endPoint): string;

    /**
     * @param string $endPoint
     * @return string
     */
    public function getSortOrder(string $endPoint): string;
}
