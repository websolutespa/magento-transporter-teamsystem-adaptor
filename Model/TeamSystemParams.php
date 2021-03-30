<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Model;

use DateTime;
use InvalidArgumentException;
use Magento\Framework\Url\QueryParamsResolverInterface;
use Magento\Framework\Webapi\Rest\Request;
use Websolute\TransporterTeamSystemAdaptor\Api\CallConfigInterface;
use Websolute\TransporterTeamSystemAdaptor\Api\TeamSystemParamsInterface;

class TeamSystemParams implements TeamSystemParamsInterface
{
    /**
     * @var QueryParamsResolverInterface
     */
    private $queryParamsResolver;

    /**
     * @var CallConfigInterface
     */
    private $config;

    /**
     * @var string
     */
    private $resourceName;

    /**
     * @var string
     */
    private $method;

    /**
     * @param QueryParamsResolverInterface $queryParamsResolver
     * @param CallConfigInterface $config
     * @param string $resourceName
     * @param string $method
     */
    public function __construct(
        QueryParamsResolverInterface $queryParamsResolver,
        CallConfigInterface $config,
        string $resourceName,
        string $method = Request::HTTP_METHOD_GET
    ) {
        $this->queryParamsResolver = $queryParamsResolver;
        $this->config = $config;
        $this->resourceName = $resourceName;
        $this->method = $method;
        $this->validateMethod();
    }

    /**
     * @throw InvalidArgumentException
     */
    private function validateMethod()
    {
        if (null === $this->method) {
            return;
        }

        if (!is_string($this->method)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported HTTP method; must be a string, received %s',
                (is_object($this->method) ? get_class($this->method) : gettype($this->method))
            ));
        }

        if (!preg_match('/^[!#$%&\'*+.^_`\|~0-9a-z-]+$/i', $this->method)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported HTTP method "%s" provided',
                $this->method
            ));
        }
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @inheritDoc
     */
    public function getResourceName(): string
    {
        return $this->resourceName;
    }

    /**
     * @inheritDoc
     */
    public function getUrlParams(
        DateTime $lastChangeDateTime = null,
        string $sortField = '',
        string $sortOrder = ''
    ): string {
        $urlParamsString = '?';

        $bunchSize = $this->config->getBunchSize() ?: false;
        if ($bunchSize) {
            $urlParamsString .= 'max=' . $bunchSize . '&';
        }

        if ($sortField) {
            $urlParamsString .= 'sort=' . $sortField . '&';
        }

        if ($sortOrder) {
            $urlParamsString .= 'order=' . $sortOrder . '&';
        }

        if ($lastChangeDateTime) {
            $lastChangeString = $lastChangeDateTime->format(DateTimeFormat::TEAMSYSTEM_FORMAT_FOR_FILTER);
            $urlParamsString .= 'lastchange=' . $lastChangeString . '&';
        }

        $urlParamsString = rtrim($urlParamsString, '&');
        return $urlParamsString;
    }
}
