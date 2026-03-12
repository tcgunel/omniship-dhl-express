<?php

declare(strict_types=1);

namespace Omniship\DHL\Express;

use Omniship\Common\AbstractHttpCarrier;
use Omniship\Common\Auth\BasicAuthTrait;
use Omniship\Common\Message\RequestInterface;
use Omniship\DHL\Express\Message\CancelShipmentRequest;
use Omniship\DHL\Express\Message\CreateShipmentRequest;
use Omniship\DHL\Express\Message\GetRatesRequest;
use Omniship\DHL\Express\Message\GetTrackingStatusRequest;

class Carrier extends AbstractHttpCarrier
{
    use BasicAuthTrait;

    private const BASE_URL_PRODUCTION = 'https://express.api.dhl.com/mydhlapi';
    private const BASE_URL_TEST = 'https://express.api.dhl.com/mydhlapi/test';

    public function getName(): string
    {
        return 'DHL Express';
    }

    public function getShortName(): string
    {
        return 'DHL_Express';
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultParameters(): array
    {
        return [
            'username' => '',
            'password' => '',
            'accountNumber' => '',
            'testMode' => false,
        ];
    }

    public function getAccountNumber(): string
    {
        return (string) $this->getParameter('accountNumber');
    }

    public function setAccountNumber(string $accountNumber): static
    {
        return $this->setParameter('accountNumber', $accountNumber);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createShipment(array $options = []): RequestInterface
    {
        return $this->createRequest(CreateShipmentRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getTrackingStatus(array $options = []): RequestInterface
    {
        return $this->createRequest(GetTrackingStatusRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function cancelShipment(array $options = []): RequestInterface
    {
        return $this->createRequest(CancelShipmentRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getRates(array $options = []): RequestInterface
    {
        return $this->createRequest(GetRatesRequest::class, $options);
    }

    public function getBaseUrl(): string
    {
        return $this->getTestMode() ? self::BASE_URL_TEST : self::BASE_URL_PRODUCTION;
    }
}
