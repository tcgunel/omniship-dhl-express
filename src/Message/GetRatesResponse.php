<?php

declare(strict_types=1);

namespace Omniship\DHL\Express\Message;

use Omniship\Common\Message\AbstractResponse;

class GetRatesResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return is_array($this->data)
            && isset($this->data['products'])
            && is_array($this->data['products'])
            && $this->data['products'] !== [];
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

    /**
     * @return array<int, array{productCode: string, productName: string, price: float, currency: string, transitDays: int|null, estimatedDelivery: string|null}>
     */
    public function getRates(): array
    {
        if (!$this->isSuccessful()) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $products */
        $products = $this->data['products'];

        $rates = [];

        foreach ($products as $product) {
            $price = 0.0;
            $currency = 'USD';

            /** @var array<int, array{currencyType?: string, priceCurrency?: string, price?: float}> $totalPrices */
            $totalPrices = $product['totalPrice'] ?? [];

            foreach ($totalPrices as $totalPrice) {
                if (($totalPrice['currencyType'] ?? '') === 'BILLC') {
                    $price = (float) ($totalPrice['price'] ?? 0.0);
                    $currency = (string) ($totalPrice['priceCurrency'] ?? 'USD');
                    break;
                }
            }

            // Fallback to first price if BILLC not found
            if ($price === 0.0 && $totalPrices !== []) {
                $price = (float) ($totalPrices[0]['price'] ?? 0.0);
                $currency = (string) ($totalPrices[0]['priceCurrency'] ?? 'USD');
            }

            /** @var array{totalTransitDays?: int, estimatedDeliveryDateAndTime?: string} $deliveryCapabilities */
            $deliveryCapabilities = $product['deliveryCapabilities'] ?? [];

            $rates[] = [
                'productCode' => (string) ($product['productCode'] ?? ''),
                'productName' => (string) ($product['productName'] ?? ''),
                'price' => $price,
                'currency' => $currency,
                'transitDays' => isset($deliveryCapabilities['totalTransitDays'])
                    ? (int) $deliveryCapabilities['totalTransitDays']
                    : null,
                'estimatedDelivery' => $deliveryCapabilities['estimatedDeliveryDateAndTime'] ?? null,
            ];
        }

        return $rates;
    }
}
