<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Api;

use DateTime;

interface TeamSystemParamsInterface
{
    /**
     * @return string
     */
    public function getMethod(): string;

    /**
     * @return string
     */
    public function getResourceName(): string;

    /**
     * @param DateTime|null $lastChangeDateTime
     * @param string $sortField
     * @param string $sortOrder
     * @return string
     */
    public function getUrlParams(
        DateTime $lastChangeDateTime = null,
        string $sortField = '',
        string $sortOrder = 'asc'
    ): string;
}
