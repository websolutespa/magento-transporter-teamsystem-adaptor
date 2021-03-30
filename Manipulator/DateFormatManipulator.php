<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See LICENSE and/or COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterTeamSystemAdaptor\Manipulator;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime;
use Monolog\Logger;
use Websolute\TransporterBase\Api\ManipulatorInterface;
use Websolute\TransporterBase\Exception\TransporterException;
use Websolute\TransporterEntity\Api\Data\EntityInterface;
use Websolute\TransporterImporter\Model\DotConvention;
use Websolute\TransporterTeamSystemAdaptor\Model\DateTimeFormat;

class DateFormatManipulator implements ManipulatorInterface
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var DotConvention
     */
    private $dotConvention;

    /**
     * @var string
     */
    private $source;

    /**
     * @var string
     */
    private $destination;

    /**
     * @param Logger $logger
     * @param Json $serializer
     * @param DotConvention $dotConvention
     * @param string $source
     * @param string $destination
     */
    public function __construct(
        Logger $logger,
        Json $serializer,
        DotConvention $dotConvention,
        string $source,
        string $destination
    ) {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->dotConvention = $dotConvention;
        $this->source = $source;
        $this->destination = $destination;
    }

    /**
     * @param int $activityId
     * @param string $manipulatorType
     * @param string $entityIdentifier
     * @param EntityInterface[] $entities
     * @throws TransporterException
     */
    public function execute(int $activityId, string $manipulatorType, string $entityIdentifier, array $entities): void
    {
        $sourceIdentifier = $this->dotConvention->getFirst($this->source);
        $source = $this->dotConvention->getFromSecondInDotConvention($this->source);
        if (!array_key_exists($sourceIdentifier, $entities)) {
            throw new TransporterException(__('Invalid sourceIdentifier for class %1', self::class));
        }

        $destinationIdentifier = $this->dotConvention->getFirst($this->destination);
        $destination = $this->dotConvention->getFromSecondInDotConvention($this->destination);
        if (!array_key_exists($destinationIdentifier, $entities)) {
            throw new TransporterException(__('Invalid destinationIdentifier for class %1', self::class));
        }

        $entity = $entities[$sourceIdentifier];
        $data = $entity->getDataManipulated();
        $data = $this->serializer->unserialize($data);

        $dateString = $this->dotConvention->getValue($data, $source);

        $date = \DateTime::createFromFormat(DateTimeFormat::TEAMSYSTEM_FORMAT, $dateString);

        $dateFormatted = $date->format(DateTime::DATETIME_PHP_FORMAT);

        $this->dotConvention->setValue($data, $destination, $dateFormatted);

        $data = $this->serializer->serialize($data);
        $entity->setDataManipulated($data);
    }
}
