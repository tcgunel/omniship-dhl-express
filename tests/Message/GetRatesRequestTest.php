<?php

declare(strict_types=1);

use Omniship\Common\Address;
use Omniship\Common\Package;
use Omniship\DHL\Express\Message\GetRatesRequest;
use Omniship\DHL\Express\Message\GetRatesResponse;

use function Omniship\DHL\Express\Tests\createMockHttpClient;
use function Omniship\DHL\Express\Tests\createMockRequestFactory;
use function Omniship\DHL\Express\Tests\createMockStreamFactory;

function createRatesSuccessJson(): string
{
    return json_encode([
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
    ], JSON_THROW_ON_ERROR);
}

function createRatesRequest(string $responseJson): GetRatesRequest
{
    return new GetRatesRequest(
        createMockHttpClient($responseJson),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

it('builds correct rate query data', function () {
    $request = createRatesRequest(createRatesSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'accountNumber' => '123456789',
        'shipFrom' => new Address(
            city: 'Istanbul',
            postalCode: '34710',
            country: 'TR',
        ),
        'shipTo' => new Address(
            city: 'New York',
            postalCode: '10001',
            country: 'US',
        ),
        'packages' => [
            new Package(weight: 2.5, length: 30, width: 20, height: 15),
        ],
        'plannedShippingDate' => '2026-03-13',
        'isCustomsDeclarable' => true,
    ]);

    $data = $request->getData();

    expect($data['accountNumber'])->toBe('123456789')
        ->and($data['originCountryCode'])->toBe('TR')
        ->and($data['originCityName'])->toBe('Istanbul')
        ->and($data['originPostalCode'])->toBe('34710')
        ->and($data['destinationCountryCode'])->toBe('US')
        ->and($data['destinationCityName'])->toBe('New York')
        ->and($data['destinationPostalCode'])->toBe('10001')
        ->and($data['weight'])->toBe(2.5)
        ->and($data['length'])->toBe(30)
        ->and($data['width'])->toBe(20)
        ->and($data['height'])->toBe(15)
        ->and($data['plannedShippingDate'])->toBe('2026-03-13')
        ->and($data['isCustomsDeclarable'])->toBeTrue()
        ->and($data['unitOfMeasurement'])->toBe('metric');
});

it('sends request and returns rates response', function () {
    $request = createRatesRequest(createRatesSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'accountNumber' => '123456789',
        'shipFrom' => new Address(city: 'Istanbul', country: 'TR'),
        'shipTo' => new Address(city: 'New York', country: 'US'),
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(GetRatesResponse::class)
        ->and($response->isSuccessful())->toBeTrue();
});

it('includes product code filter when specified', function () {
    $request = createRatesRequest(createRatesSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'accountNumber' => '123456789',
        'shipFrom' => new Address(city: 'Istanbul', country: 'TR'),
        'shipTo' => new Address(city: 'New York', country: 'US'),
        'productCode' => 'P',
    ]);

    $data = $request->getData();

    expect($data['productCode'])->toBe('P');
});

it('throws exception when required fields are missing', function () {
    $request = createRatesRequest(createRatesSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);
