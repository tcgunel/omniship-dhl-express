<?php

declare(strict_types=1);

namespace Omniship\DHL\Express\Message;

use Omniship\Common\Message\ResponseInterface;

class CancelShipmentRequest extends AbstractDhlExpressRequest
{
    public function getTrackingNumber(): ?string
    {
        return $this->getParameter('trackingNumber');
    }

    public function setTrackingNumber(string $trackingNumber): static
    {
        return $this->setParameter('trackingNumber', $trackingNumber);
    }

    public function getDispatchConfirmationNumber(): ?string
    {
        return $this->getParameter('dispatchConfirmationNumber');
    }

    public function setDispatchConfirmationNumber(string $number): static
    {
        return $this->setParameter('dispatchConfirmationNumber', $number);
    }

    public function getRequestorName(): ?string
    {
        return $this->getParameter('requestorName');
    }

    public function setRequestorName(string $name): static
    {
        return $this->setParameter('requestorName', $name);
    }

    public function getCancelReason(): string
    {
        return (string) ($this->getParameter('cancelReason') ?? '008');
    }

    public function setCancelReason(string $reason): static
    {
        return $this->setParameter('cancelReason', $reason);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password', 'trackingNumber', 'dispatchConfirmationNumber');

        return [
            'trackingNumber' => $this->getTrackingNumber() ?? '',
            'dispatchConfirmationNumber' => $this->getDispatchConfirmationNumber() ?? '',
            'requestorName' => $this->getRequestorName() ?? '',
            'reason' => $this->getCancelReason(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $url = $this->getBaseUrl() . '/shipments/'
            . urlencode($data['trackingNumber']) . '/pickup';

        $body = [
            'dispatchConfirmationNumber' => $data['dispatchConfirmationNumber'],
            'pickupDate' => date('Y-m-d'),
            'reason' => $data['reason'],
        ];

        if ($data['requestorName'] !== '') {
            $body['requestorName'] = $data['requestorName'];
        }

        $headers = $this->getDefaultHeaders();

        $response = $this->sendHttpRequest(
            method: 'DELETE',
            url: $url,
            headers: $headers,
            body: json_encode($body, JSON_THROW_ON_ERROR),
        );

        $responseBody = (string) $response->getBody();

        /** @var array<string, mixed> $result */
        $result = $responseBody !== '' ? json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR) : [];

        return $this->response = new CancelShipmentResponse($this, $result);
    }
}
