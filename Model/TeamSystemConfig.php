<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Model;

use DateTime;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime as MagentoDateTime;
use Magento\Store\Model\ScopeInterface;

class TeamSystemConfig
{
    const TRANSPORTER_TEAMSYSTEM_GENERAL_WEBSERVICE_URL = 'transporter_teamsystem/general/webservice_url';
    const TRANSPORTER_TEAMSYSTEM_GENERAL_WEBSERVICE_USERNAME = 'transporter_teamsystem/general/webservice_username';
    const TRANSPORTER_TEAMSYSTEM_GENERAL_WEBSERVICE_PASSWORD = 'transporter_teamsystem/general/webservice_password';
    const TRANSPORTER_TEAMSYSTEM_GENERAL_WEBSERVICE_RESPONSE_COMPRESSED = 'transporter_teamsystem/general/webservice_response_compressed';
    const TRANSPORTER_TEAMSYSTEM_GENERAL_WEBSERVICE_UNSECURE_CERTIFICATE = 'transporter_teamsystem/general/webservice_unsercure_certificate';
    const TRANSPORTER_TEAMSYSTEM_GENERAL_FALLBACK_LAST_CHANGE_DATETIME = 'transporter_teamsystem/general/fallback_last_change_datetime';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return string
     */
    public function getWebserviceUrl(): string
    {
        return rtrim((string)$this->scopeConfig->getValue(
            self::TRANSPORTER_TEAMSYSTEM_GENERAL_WEBSERVICE_URL,
            ScopeInterface::SCOPE_WEBSITE
        ), '/');
    }

    /**
     * @return DateTime
     */
    public function getFallbackLastChangeDateTime(): DateTime
    {
        $lastChangeString = (string)$this->scopeConfig->getValue(
            self::TRANSPORTER_TEAMSYSTEM_GENERAL_FALLBACK_LAST_CHANGE_DATETIME,
            ScopeInterface::SCOPE_WEBSITE
        );

        $lastChangeDateTime = DateTime::createFromFormat(MagentoDateTime::DATETIME_PHP_FORMAT, $lastChangeString);
        if (!$lastChangeDateTime) {
            $lastChangeDateTime = new DateTime('-1 month');
        }

        return $lastChangeDateTime;
    }

    /**
     * @return string
     */
    public function getWebserviceUsername(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::TRANSPORTER_TEAMSYSTEM_GENERAL_WEBSERVICE_USERNAME,
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * @return string
     */
    public function getWebservicePassword(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::TRANSPORTER_TEAMSYSTEM_GENERAL_WEBSERVICE_PASSWORD,
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * @return bool
     */
    public function isResponseCompressed(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::TRANSPORTER_TEAMSYSTEM_GENERAL_WEBSERVICE_RESPONSE_COMPRESSED,
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * @return bool
     */
    public function isWebserviceUnsecureCertificate(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::TRANSPORTER_TEAMSYSTEM_GENERAL_WEBSERVICE_UNSECURE_CERTIFICATE,
            ScopeInterface::SCOPE_WEBSITE
        );
    }
}
