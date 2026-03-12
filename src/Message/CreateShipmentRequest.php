<?php

declare(strict_types=1);

namespace Omniship\DHL\Express\Message;

use Omniship\Common\Address;
use Omniship\Common\Message\ResponseInterface;
use Omniship\Common\Package;

class CreateShipmentRequest extends AbstractDhlExpressRequest
{
    public function getProductCode(): string
    {
        return (string) ($this->getParameter('productCode') ?? 'P');
    }

    public function setProductCode(string $productCode): static
    {
        return $this->setParameter('productCode', $productCode);
    }

    public function getPlannedShippingDate(): ?string
    {
        return $this->getParameter('plannedShippingDate');
    }

    public function setPlannedShippingDate(string $date): static
    {
        return $this->setParameter('plannedShippingDate', $date);
    }

    public function getPickupRequested(): bool
    {
        return (bool) ($this->getParameter('pickupRequested') ?? false);
    }

    public function setPickupRequested(bool $requested): static
    {
        return $this->setParameter('pickupRequested', $requested);
    }

    public function getIsCustomsDeclarable(): bool
    {
        return (bool) ($this->getParameter('isCustomsDeclarable') ?? false);
    }

    public function setIsCustomsDeclarable(bool $declarable): static
    {
        return $this->setParameter('isCustomsDeclarable', $declarable);
    }

    public function getDeclaredValue(): ?float
    {
        return $this->getParameter('declaredValue');
    }

    public function setDeclaredValue(float $value): static
    {
        return $this->setParameter('declaredValue', $value);
    }

    public function getDeclaredValueCurrency(): ?string
    {
        return $this->getParameter('declaredValueCurrency');
    }

    public function setDeclaredValueCurrency(string $currency): static
    {
        return $this->setParameter('declaredValueCurrency', $currency);
    }

    public function getLabelFormat(): string
    {
        return (string) ($this->getParameter('labelFormat') ?? 'pdf');
    }

    public function setLabelFormat(string $format): static
    {
        return $this->setParameter('labelFormat', $format);
    }

    public function getShipmentDescription(): ?string
    {
        return $this->getParameter('shipmentDescription');
    }

    public function setShipmentDescription(string $description): static
    {
        return $this->setParameter('shipmentDescription', $description);
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

        $data = [
            'plannedShippingDateAndTime' => $this->getPlannedShippingDate() ?? gmdate('Y-m-d\TH:i:s \G\M\T+00:00'),
            'pickup' => [
                'isRequested' => $this->getPickupRequested(),
            ],
            'productCode' => $this->getProductCode(),
            'accounts' => [
                [
                    'typeCode' => 'shipper',
                    'number' => $this->getAccountNumber() ?? '',
                ],
            ],
            'outputImageProperties' => [
                'printerDPI' => 300,
                'encodingFormat' => $this->getLabelFormat(),
                'imageOptions' => [
                    [
                        'typeCode' => 'label',
                        'templateName' => 'ECOM26_84_001',
                    ],
                ],
            ],
            'customerDetails' => [
                'shipperDetails' => $this->buildAddressDetails($shipFrom),
                'receiverDetails' => $this->buildAddressDetails($shipTo),
            ],
            'content' => $this->buildContent($packages, $shipFrom, $shipTo),
        ];

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $url = $this->getBaseUrl() . '/shipments';

        /** @var array<string, mixed> $result */
        $result = $this->sendJsonRequest('POST', $url, $data);

        return $this->response = new CreateShipmentResponse($this, $result);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAddressDetails(Address $address): array
    {
        $postalAddress = [
            'addressLine1' => $address->street1 ?? '',
            'cityName' => $address->city ?? '',
            'countryCode' => $address->country ?? 'TR',
        ];

        if ($address->street2 !== null && $address->street2 !== '') {
            $postalAddress['addressLine2'] = $address->street2;
        }

        if ($address->postalCode !== null && $address->postalCode !== '') {
            $postalAddress['postalCode'] = $address->postalCode;
        }

        if ($address->state !== null && $address->state !== '') {
            $postalAddress['provinceCode'] = $address->state;
        }

        if ($address->district !== null && $address->district !== '') {
            $postalAddress['countyName'] = $address->district;
        }

        $contactInfo = [
            'phone' => $address->phone ?? '',
            'fullName' => $address->name ?? '',
        ];

        if ($address->company !== null && $address->company !== '') {
            $contactInfo['companyName'] = $address->company;
        }

        if ($address->email !== null && $address->email !== '') {
            $contactInfo['email'] = $address->email;
        }

        $details = [
            'postalAddress' => $postalAddress,
            'contactInformation' => $contactInfo,
        ];

        if ($address->taxId !== null && $address->taxId !== '') {
            $details['registrationNumbers'] = [
                [
                    'typeCode' => 'VAT',
                    'number' => $address->taxId,
                    'issuerCountryCode' => $address->country ?? 'TR',
                ],
            ];
        }

        return $details;
    }

    /**
     * @param Package[] $packages
     * @return array<string, mixed>
     */
    private function buildContent(array $packages, Address $shipFrom, Address $shipTo): array
    {
        $contentPackages = [];
        $totalWeight = 0.0;

        foreach ($packages as $index => $package) {
            for ($i = 0; $i < $package->quantity; $i++) {
                $pkg = [
                    'weight' => $package->weight,
                    'dimensions' => [
                        'length' => (int) ($package->length ?? 1),
                        'width' => (int) ($package->width ?? 1),
                        'height' => (int) ($package->height ?? 1),
                    ],
                ];

                if ($package->description !== null && $package->description !== '') {
                    $pkg['description'] = $package->description;
                    $pkg['labelDescription'] = mb_substr($package->description, 0, 35);
                }

                $contentPackages[] = $pkg;
                $totalWeight += $package->weight;
            }
        }

        if ($contentPackages === []) {
            $contentPackages[] = [
                'weight' => 0.5,
                'dimensions' => [
                    'length' => 1,
                    'width' => 1,
                    'height' => 1,
                ],
            ];
        }

        $isCustomsDeclarable = $this->getIsCustomsDeclarable();

        // Auto-detect customs declarability based on country codes
        if (!$isCustomsDeclarable) {
            $fromCountry = $shipFrom->country ?? 'TR';
            $toCountry = $shipTo->country ?? 'TR';
            $isCustomsDeclarable = $fromCountry !== $toCountry;
        }

        $content = [
            'packages' => $contentPackages,
            'isCustomsDeclarable' => $isCustomsDeclarable,
            'unitOfMeasurement' => 'metric',
            'description' => $this->getShipmentDescription() ?? $this->buildDescription($packages),
        ];

        if ($isCustomsDeclarable && $this->getDeclaredValue() !== null) {
            $content['declaredValue'] = $this->getDeclaredValue();
            $content['declaredValueCurrency'] = $this->getDeclaredValueCurrency() ?? 'USD';
        }

        return $content;
    }

    /**
     * @param Package[] $packages
     */
    private function buildDescription(array $packages): string
    {
        foreach ($packages as $package) {
            if ($package->description !== null && $package->description !== '') {
                return $package->description;
            }
        }

        return 'Shipment';
    }
}
