<?php

declare(strict_types=1);

namespace Omniship\DHL\Express\Message;

use Omniship\Common\Enum\LabelFormat;
use Omniship\Common\Label;
use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\ShipmentResponse;

class CreateShipmentResponse extends AbstractResponse implements ShipmentResponse
{
    public function isSuccessful(): bool
    {
        return is_array($this->data)
            && isset($this->data['shipmentTrackingNumber'])
            && !isset($this->data['status']);
    }

    public function getMessage(): ?string
    {
        if (!is_array($this->data)) {
            return null;
        }

        return $this->data['message'] ?? $this->data['detail'] ?? null;
    }

    public function getCode(): ?string
    {
        if (!is_array($this->data)) {
            return null;
        }

        if (isset($this->data['status'])) {
            return (string) $this->data['status'];
        }

        return $this->isSuccessful() ? '201' : null;
    }

    public function getShipmentId(): ?string
    {
        if (!is_array($this->data) || !isset($this->data['shipmentTrackingNumber'])) {
            return null;
        }

        return (string) $this->data['shipmentTrackingNumber'];
    }

    public function getTrackingNumber(): ?string
    {
        return $this->getShipmentId();
    }

    public function getBarcode(): ?string
    {
        return $this->getTrackingNumber();
    }

    public function getLabel(): ?Label
    {
        if (!is_array($this->data) || !isset($this->data['packages'])) {
            return null;
        }

        /** @var array<int, array{documents?: array<int, array{typeCode?: string, content?: string, imageFormat?: string}>}> $packages */
        $packages = $this->data['packages'];

        foreach ($packages as $package) {
            if (!isset($package['documents'])) {
                continue;
            }

            foreach ($package['documents'] as $document) {
                if (($document['typeCode'] ?? '') === 'label' && isset($document['content'])) {
                    $format = $this->mapLabelFormat($document['imageFormat'] ?? 'PDF');

                    return new Label(
                        trackingNumber: $this->getTrackingNumber() ?? '',
                        content: $document['content'],
                        format: $format,
                    );
                }
            }
        }

        return null;
    }

    public function getTotalCharge(): ?float
    {
        return null;
    }

    public function getCurrency(): ?string
    {
        return null;
    }

    public function getDispatchConfirmationNumber(): ?string
    {
        if (!is_array($this->data) || !isset($this->data['dispatchConfirmationNumber'])) {
            return null;
        }

        return (string) $this->data['dispatchConfirmationNumber'];
    }

    public function getTrackingUrl(): ?string
    {
        if (!is_array($this->data) || !isset($this->data['trackingUrl'])) {
            return null;
        }

        return (string) $this->data['trackingUrl'];
    }

    public function getEstimatedDeliveryDate(): ?string
    {
        if (!is_array($this->data)
            || !isset($this->data['estimatedDeliveryDate']['estimatedDeliveryDate'])) {
            return null;
        }

        return (string) $this->data['estimatedDeliveryDate']['estimatedDeliveryDate'];
    }

    private function mapLabelFormat(string $format): LabelFormat
    {
        return match (strtoupper($format)) {
            'PDF' => LabelFormat::PDF,
            'ZPL' => LabelFormat::ZPL,
            'EPL' => LabelFormat::EPL,
            'PNG' => LabelFormat::PNG,
            default => LabelFormat::PDF,
        };
    }
}
