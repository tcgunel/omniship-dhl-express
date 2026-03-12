<?php

declare(strict_types=1);

namespace Omniship\DHL\Express\Message;

use Omniship\Common\Enum\ShipmentStatus;
use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\TrackingResponse;
use Omniship\Common\TrackingEvent;
use Omniship\Common\TrackingInfo;

class GetTrackingStatusResponse extends AbstractResponse implements TrackingResponse
{
    private const EVENT_STATUS_MAP = [
        'PU' => ShipmentStatus::PICKED_UP,
        'PL' => ShipmentStatus::IN_TRANSIT,
        'DF' => ShipmentStatus::IN_TRANSIT,
        'AF' => ShipmentStatus::IN_TRANSIT,
        'CC' => ShipmentStatus::IN_TRANSIT,
        'CD' => ShipmentStatus::IN_TRANSIT,
        'CR' => ShipmentStatus::IN_TRANSIT,
        'CI' => ShipmentStatus::IN_TRANSIT,
        'WC' => ShipmentStatus::OUT_FOR_DELIVERY,
        'OK' => ShipmentStatus::DELIVERED,
        'DL' => ShipmentStatus::DELIVERED,
        'DD' => ShipmentStatus::DELIVERED,
        'NH' => ShipmentStatus::FAILURE,
        'BA' => ShipmentStatus::FAILURE,
        'MS' => ShipmentStatus::FAILURE,
        'OH' => ShipmentStatus::FAILURE,
        'RR' => ShipmentStatus::RETURNED,
        'RT' => ShipmentStatus::RETURNED,
        'TP' => ShipmentStatus::PRE_TRANSIT,
    ];

    private const SHIPMENT_STATUS_MAP = [
        'pre-transit' => ShipmentStatus::PRE_TRANSIT,
        'transit' => ShipmentStatus::IN_TRANSIT,
        'delivered' => ShipmentStatus::DELIVERED,
        'failure' => ShipmentStatus::FAILURE,
    ];

    public function isSuccessful(): bool
    {
        return is_array($this->data)
            && isset($this->data['shipments'])
            && is_array($this->data['shipments'])
            && $this->data['shipments'] !== [];
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

        return $this->isSuccessful() ? '200' : null;
    }

    public function getTrackingInfo(): TrackingInfo
    {
        if (!$this->isSuccessful()) {
            return new TrackingInfo(
                trackingNumber: '',
                status: ShipmentStatus::UNKNOWN,
                carrier: 'DHL Express',
            );
        }

        /** @var array<string, mixed> $shipment */
        $shipment = $this->data['shipments'][0];

        $trackingNumber = (string) ($shipment['shipmentTrackingNumber'] ?? '');
        $status = $this->mapShipmentStatus((string) ($shipment['status'] ?? 'unknown'));
        $events = $this->parseEvents($shipment);

        // If we have events, use the latest event's status
        if ($events !== []) {
            $status = $events[0]->status;
        }

        $signedBy = null;
        foreach ($events as $event) {
            if ($event->status === ShipmentStatus::DELIVERED) {
                // Check raw events for signedBy
                $signedBy = $this->findSignedBy($shipment);
                break;
            }
        }

        $estimatedDelivery = null;
        if (isset($shipment['estimatedDeliveryDate'])) {
            $estimatedDelivery = $this->parseDate((string) $shipment['estimatedDeliveryDate']);
        }

        return new TrackingInfo(
            trackingNumber: $trackingNumber,
            status: $status,
            events: $events,
            carrier: 'DHL Express',
            serviceName: $shipment['description'] ?? null,
            estimatedDelivery: $estimatedDelivery,
            signedBy: $signedBy,
        );
    }

    /**
     * @param array<string, mixed> $shipment
     * @return TrackingEvent[]
     */
    private function parseEvents(array $shipment): array
    {
        /** @var array<int, array<string, mixed>> $rawEvents */
        $rawEvents = $shipment['events'] ?? [];

        $events = [];

        foreach ($rawEvents as $rawEvent) {
            $typeCode = (string) ($rawEvent['typeCode'] ?? '');
            $description = (string) ($rawEvent['description'] ?? '');
            $dateStr = (string) ($rawEvent['date'] ?? '');

            $occurredAt = $this->parseDate($dateStr);
            if ($occurredAt === null) {
                continue;
            }

            $status = self::EVENT_STATUS_MAP[$typeCode] ?? ShipmentStatus::UNKNOWN;

            $location = null;
            $city = null;
            $country = null;

            /** @var array<int, array{code?: string, description?: string}> $serviceAreas */
            $serviceAreas = $rawEvent['serviceArea'] ?? [];
            if ($serviceAreas !== []) {
                $areaDescription = $serviceAreas[0]['description'] ?? '';
                $location = $areaDescription;

                // Parse "CITY-COUNTRY" format
                $parts = explode('-', $areaDescription, 2);
                if (count($parts) === 2) {
                    $city = trim($parts[0]);
                    $country = trim($parts[1]);
                }
            }

            $events[] = new TrackingEvent(
                status: $status,
                description: $description,
                occurredAt: $occurredAt,
                location: $location,
                city: $city,
                country: $country,
            );
        }

        return $events;
    }

    private function mapShipmentStatus(string $status): ShipmentStatus
    {
        return self::SHIPMENT_STATUS_MAP[strtolower($status)] ?? ShipmentStatus::UNKNOWN;
    }

    private function parseDate(string $dateStr): ?\DateTimeImmutable
    {
        if ($dateStr === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $dateStr);
        if ($date !== false) {
            return $date;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
        if ($date !== false) {
            return $date;
        }

        // Try ISO 8601 with timezone
        try {
            return new \DateTimeImmutable($dateStr);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $shipment
     */
    private function findSignedBy(array $shipment): ?string
    {
        /** @var array<int, array<string, mixed>> $events */
        $events = $shipment['events'] ?? [];

        foreach ($events as $event) {
            $typeCode = $event['typeCode'] ?? '';
            if (in_array($typeCode, ['OK', 'DL', 'DD'], true)) {
                $signedBy = $event['signedBy'] ?? null;
                if ($signedBy !== null && $signedBy !== '') {
                    return (string) $signedBy;
                }
            }
        }

        return null;
    }
}
