<?php

declare(strict_types=1);

namespace Omniship\DHL\Express\Message;

use Omniship\Common\Message\ResponseInterface;

class GetTrackingStatusRequest extends AbstractDhlExpressRequest
{
    public function getTrackingNumber(): ?string
    {
        return $this->getParameter('trackingNumber');
    }

    public function setTrackingNumber(string $trackingNumber): static
    {
        return $this->setParameter('trackingNumber', $trackingNumber);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password', 'trackingNumber');

        return [
            'trackingNumber' => $this->getTrackingNumber() ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $url = $this->getBaseUrl() . '/tracking?shipmentTrackingNumber='
            . urlencode($data['trackingNumber']);

        /** @var array<string, mixed> $result */
        $result = $this->sendJsonRequest('GET', $url);

        return $this->response = new GetTrackingStatusResponse($this, $result);
    }
}
