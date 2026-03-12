<?php

declare(strict_types=1);

use Omniship\DHL\Express\Message\GetTrackingStatusRequest;
use Omniship\DHL\Express\Message\GetTrackingStatusResponse;

use function Omniship\DHL\Express\Tests\createMockHttpClient;
use function Omniship\DHL\Express\Tests\createMockRequestFactory;
use function Omniship\DHL\Express\Tests\createMockStreamFactory;

function createTrackingSuccessJson(): string
{
    return json_encode([
        'shipments' => [
            [
                'shipmentTrackingNumber' => '1234567890',
                'status' => 'transit',
                'events' => [
                    [
                        'date' => '2026-03-14T08:15:00',
                        'typeCode' => 'PU',
                        'description' => 'Shipment picked up',
                        'serviceArea' => [
                            ['code' => 'IST', 'description' => 'ISTANBUL-TURKEY'],
                        ],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}

function createTrackingRequest(string $responseJson): GetTrackingStatusRequest
{
    return new GetTrackingStatusRequest(
        createMockHttpClient($responseJson),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

it('builds correct tracking data', function () {
    $request = createTrackingRequest(createTrackingSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'trackingNumber' => '1234567890',
    ]);

    $data = $request->getData();

    expect($data['trackingNumber'])->toBe('1234567890');
});

it('sends request and returns tracking response', function () {
    $request = createTrackingRequest(createTrackingSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'trackingNumber' => '1234567890',
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(GetTrackingStatusResponse::class)
        ->and($response->isSuccessful())->toBeTrue();
});

it('throws exception when tracking number is missing', function () {
    $request = createTrackingRequest(createTrackingSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);
