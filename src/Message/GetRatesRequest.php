<?php

declare(strict_types=1);

namespace Omniship\DHL\Express\Message;

use Omniship\Common\Address;
use Omniship\Common\Message\ResponseInterface;
use Omniship\Common\Package;

class GetRatesRequest extends AbstractDhlExpressRequest
{
    public function getPlannedShippingDate(): ?string
    {
        return $this->getParameter('plannedShippingDate');
    }

    public function setPlannedShippingDate(string $date): static
    {
        return $this->setParameter('plannedShippingDate', $date);
    }

    public function getIsCustomsDeclarable(): bool
    {
        return (bool) ($this->getParameter('isCustomsDeclarable') ?? false);
    }

    public function setIsCustomsDeclarable(bool $declarable): static
    {
        return $this->setParameter('isCustomsDeclarable', $declarable);
    }

    public function getProductCode(): ?string
    {
        return $this->getParameter('productCode');
    }

    public function setProductCode(string $productCode): static
    {
        return $this->setParameter('productCode', $productCode);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password', 'accountNumber', 'shipFrom', 'shipTo');

        $shipFrom = $this->getShipFrom();
        assert($shipFrom instanceof Address);

        $shipTo = $this->getShipTo();
        assert($shipTo instanceof Address);

        $packages = $this->getPackages() ?? [];
        $firstPackage = $packages[0] ?? null;

        $data = [
            'accountNumber' => $this->getAccountNumber() ?? '',
            'originCountryCode' => $shipFrom->country ?? 'TR',
            'originCityName' => $shipFrom->city ?? '',
            'destinationCountryCode' => $shipTo->country ?? 'TR',
            'destinationCityName' => $shipTo->city ?? '',
            'weight' => $firstPackage !== null ? $firstPackage->weight : 0.5,
            'length' => (int) ($firstPackage?->length ?? 1),
            'width' => (int) ($firstPackage?->width ?? 1),
            'height' => (int) ($firstPackage?->height ?? 1),
            'plannedShippingDate' => $this->getPlannedShippingDate() ?? date('Y-m-d'),
            'isCustomsDeclarable' => $this->getIsCustomsDeclarable(),
            'unitOfMeasurement' => 'metric',
        ];

        if ($shipFrom->postalCode !== null && $shipFrom->postalCode !== '') {
            $data['originPostalCode'] = $shipFrom->postalCode;
        }

        if ($shipTo->postalCode !== null && $shipTo->postalCode !== '') {
            $data['destinationPostalCode'] = $shipTo->postalCode;
        }

        if ($this->getProductCode() !== null) {
            $data['productCode'] = $this->getProductCode();
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $queryParams = [];

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $queryParams[$key] = $value ? 'true' : 'false';
            } else {
                $queryParams[$key] = (string) $value;
            }
        }

        $url = $this->getBaseUrl() . '/rates?' . http_build_query($queryParams);

        /** @var array<string, mixed> $result */
        $result = $this->sendJsonRequest('GET', $url);

        return $this->response = new GetRatesResponse($this, $result);
    }
}
