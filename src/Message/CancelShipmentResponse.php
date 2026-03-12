<?php

declare(strict_types=1);

namespace Omniship\DHL\Express\Message;

use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\CancelResponse;

class CancelShipmentResponse extends AbstractResponse implements CancelResponse
{
    public function isSuccessful(): bool
    {
        if (!is_array($this->data)) {
            return false;
        }

        // DHL returns cancelledPickup: true on success
        if (isset($this->data['cancelledPickup']) && $this->data['cancelledPickup'] === true) {
            return true;
        }

        // Empty response with no error status is also success
        if ($this->data === [] || !isset($this->data['status'])) {
            return true;
        }

        return false;
    }

    public function isCancelled(): bool
    {
        return $this->isSuccessful();
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
}
