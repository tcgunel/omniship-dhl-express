<?php

declare(strict_types=1);

use Omniship\DHL\Express\Message\GetRatesRequest;
use Omniship\DHL\Express\Message\GetRatesResponse;

use function Omniship\DHL\Express\Tests\createMockHttpClient;
use function Omniship\DHL\Express\Tests\createMockRequestFactory;
use function Omniship\DHL\Express\Tests\createMockStreamFactory;

function createRatesResponseWith(array $data): GetRatesResponse
{
    $request = new GetRatesRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'accountNumber' => '123',
        'shipFrom' => new \Omniship\Common\Address(city: 'Istanbul', country: 'TR'),
        'shipTo' => new \Omniship\Common\Address(city: 'New York', country: 'US'),
    ]);

    return new GetRatesResponse($request, $data);
}

it('parses successful rates response', function () {
    $response = createRatesResponseWith([
        'products' => [
            [
                'productName' => 'EXPRESS WORLDWIDE',
                'productCode' => 'P',
                'totalPrice' => [
                    [
                        'currencyType' => 'BILLC',
                        'priceCurrency' => 'USD',
                        'price' => 85.50,
                    ],
                    [
                        'currencyType' => 'PULCL',
                        'priceCurrency' => 'TRY',
                        'price' => 2820.00,
                    ],
                ],
                'deliveryCapabilities' => [
                    'totalTransitDays' => 3,
                    'estimatedDeliveryDateAndTime' => '2026-03-16T23:59:00',
                ],
            ],
            [
                'productName' => 'ECONOMY SELECT',
                'productCode' => 'H',
                'totalPrice' => [
                    [
                        'currencyType' => 'BILLC',
                        'priceCurrency' => 'USD',
                        'price' => 55.00,
                    ],
                ],
                'deliveryCapabilities' => [
                    'totalTransitDays' => 7,
                ],
            ],
        ],
    ]);

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->getCode())->toBe('200');

    $rates = $response->getRates();

    expect($rates)->toHaveCount(2);

    // Express Worldwide
    expect($rates[0]['productCode'])->toBe('P')
        ->and($rates[0]['productName'])->toBe('EXPRESS WORLDWIDE')
        ->and($rates[0]['price'])->toBe(85.50)
        ->and($rates[0]['currency'])->toBe('USD')
        ->and($rates[0]['transitDays'])->toBe(3)
        ->and($rates[0]['estimatedDelivery'])->toBe('2026-03-16T23:59:00');

    // Economy Select
    expect($rates[1]['productCode'])->toBe('H')
        ->and($rates[1]['productName'])->toBe('ECONOMY SELECT')
        ->and($rates[1]['price'])->toBe(55.00)
        ->and($rates[1]['transitDays'])->toBe(7);
});

it('uses BILLC currency for pricing', function () {
    $response = createRatesResponseWith([
        'products' => [
            [
                'productName' => 'EXPRESS',
                'productCode' => 'P',
                'totalPrice' => [
                    [
                        'currencyType' => 'PULCL',
                        'priceCurrency' => 'TRY',
                        'price' => 2820.00,
                    ],
                    [
                        'currencyType' => 'BILLC',
                        'priceCurrency' => 'EUR',
                        'price' => 75.00,
                    ],
                ],
                'deliveryCapabilities' => [],
            ],
        ],
    ]);

    $rates = $response->getRates();

    expect($rates[0]['price'])->toBe(75.00)
        ->and($rates[0]['currency'])->toBe('EUR');
});

it('falls back to first price when BILLC not found', function () {
    $response = createRatesResponseWith([
        'products' => [
            [
                'productName' => 'EXPRESS',
                'productCode' => 'P',
                'totalPrice' => [
                    [
                        'currencyType' => 'BASEC',
                        'priceCurrency' => 'USD',
                        'price' => 90.00,
                    ],
                ],
                'deliveryCapabilities' => [],
            ],
        ],
    ]);

    $rates = $response->getRates();

    expect($rates[0]['price'])->toBe(90.00)
        ->and($rates[0]['currency'])->toBe('USD');
});

it('handles empty products', function () {
    $response = createRatesResponseWith([
        'products' => [],
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getRates())->toBe([]);
});

it('handles error response', function () {
    $response = createRatesResponseWith([
        'message' => 'Invalid parameters',
        'status' => 400,
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Invalid parameters')
        ->and($response->getCode())->toBe('400')
        ->and($response->getRates())->toBe([]);
});

it('handles missing delivery capabilities', function () {
    $response = createRatesResponseWith([
        'products' => [
            [
                'productName' => 'EXPRESS',
                'productCode' => 'P',
                'totalPrice' => [
                    ['currencyType' => 'BILLC', 'priceCurrency' => 'USD', 'price' => 50.00],
                ],
            ],
        ],
    ]);

    $rates = $response->getRates();

    expect($rates[0]['transitDays'])->toBeNull()
        ->and($rates[0]['estimatedDelivery'])->toBeNull();
});

it('provides access to raw data', function () {
    $data = ['products' => [['productCode' => 'P', 'productName' => 'EXPRESS', 'totalPrice' => [], 'deliveryCapabilities' => []]]];
    $response = createRatesResponseWith($data);

    expect($response->getData())->toBe($data);
});
