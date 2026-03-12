<?php

declare(strict_types=1);

use Omniship\Common\Enum\ShipmentStatus;
use Omniship\DHL\Express\Message\GetTrackingStatusRequest;
use Omniship\DHL\Express\Message\GetTrackingStatusResponse;

use function Omniship\DHL\Express\Tests\createMockHttpClient;
use function Omniship\DHL\Express\Tests\createMockRequestFactory;
use function Omniship\DHL\Express\Tests\createMockStreamFactory;

function createTrackingResponseWith(array $data): GetTrackingStatusResponse
{
    $request = new GetTrackingStatusRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'trackingNumber' => '1234567890',
    ]);

    return new GetTrackingStatusResponse($request, $data);
}

it('parses in-transit tracking response', function () {
    $response = createTrackingResponseWith([
        'shipments' => [
            [
                'shipmentTrackingNumber' => '1234567890',
                'status' => 'transit',
                'description' => 'Express Worldwide',
                'events' => [
                    [
                        'date' => '2026-03-15T06:00:00',
                        'typeCode' => 'AF',
                        'description' => 'Arrived at DHL Sort Facility NEW YORK - USA',
                        'serviceArea' => [
                            ['code' => 'JFK', 'description' => 'NEW YORK-USA'],
                        ],
                        'signedBy' => '',
                    ],
                    [
                        'date' => '2026-03-14T14:30:00',
                        'typeCode' => 'DF',
                        'description' => 'Departed Facility in ISTANBUL - TURKEY',
                        'serviceArea' => [
                            ['code' => 'IST', 'description' => 'ISTANBUL-TURKEY'],
                        ],
                        'signedBy' => '',
                    ],
                    [
                        'date' => '2026-03-14T08:15:00',
                        'typeCode' => 'PU',
                        'description' => 'Shipment picked up',
                        'serviceArea' => [
                            ['code' => 'IST', 'description' => 'ISTANBUL-TURKEY'],
                        ],
                        'signedBy' => '',
                    ],
                ],
                'estimatedDeliveryDate' => '2026-03-16',
            ],
        ],
    ]);

    expect($response->isSuccessful())->toBeTrue();

    $info = $response->getTrackingInfo();

    expect($info->trackingNumber)->toBe('1234567890')
        ->and($info->status)->toBe(ShipmentStatus::IN_TRANSIT)
        ->and($info->carrier)->toBe('DHL Express')
        ->and($info->serviceName)->toBe('Express Worldwide')
        ->and($info->events)->toHaveCount(3);

    // Latest event first
    expect($info->events[0]->status)->toBe(ShipmentStatus::IN_TRANSIT)
        ->and($info->events[0]->description)->toBe('Arrived at DHL Sort Facility NEW YORK - USA')
        ->and($info->events[0]->location)->toBe('NEW YORK-USA')
        ->and($info->events[0]->city)->toBe('NEW YORK')
        ->and($info->events[0]->country)->toBe('USA');

    // Pickup event
    expect($info->events[2]->status)->toBe(ShipmentStatus::PICKED_UP)
        ->and($info->events[2]->city)->toBe('ISTANBUL');
});

it('parses delivered tracking response', function () {
    $response = createTrackingResponseWith([
        'shipments' => [
            [
                'shipmentTrackingNumber' => '1234567890',
                'status' => 'delivered',
                'events' => [
                    [
                        'date' => '2026-03-16T10:30:00',
                        'typeCode' => 'OK',
                        'description' => 'Delivered - Signed for by: J.SMITH',
                        'serviceArea' => [
                            ['code' => 'JFK', 'description' => 'NEW YORK-USA'],
                        ],
                        'signedBy' => 'J.SMITH',
                    ],
                    [
                        'date' => '2026-03-16T08:00:00',
                        'typeCode' => 'WC',
                        'description' => 'With delivery courier',
                        'serviceArea' => [
                            ['code' => 'JFK', 'description' => 'NEW YORK-USA'],
                        ],
                        'signedBy' => '',
                    ],
                ],
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::DELIVERED)
        ->and($info->signedBy)->toBe('J.SMITH')
        ->and($info->events)->toHaveCount(2)
        ->and($info->events[0]->status)->toBe(ShipmentStatus::DELIVERED)
        ->and($info->events[1]->status)->toBe(ShipmentStatus::OUT_FOR_DELIVERY);
});

it('parses failed delivery response', function () {
    $response = createTrackingResponseWith([
        'shipments' => [
            [
                'shipmentTrackingNumber' => '1234567890',
                'status' => 'failure',
                'events' => [
                    [
                        'date' => '2026-03-16T11:00:00',
                        'typeCode' => 'NH',
                        'description' => 'Delivery attempted - customer not home',
                        'serviceArea' => [
                            ['code' => 'JFK', 'description' => 'NEW YORK-USA'],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::FAILURE)
        ->and($info->events[0]->status)->toBe(ShipmentStatus::FAILURE);
});

it('parses returned shipment', function () {
    $response = createTrackingResponseWith([
        'shipments' => [
            [
                'shipmentTrackingNumber' => '1234567890',
                'status' => 'transit',
                'events' => [
                    [
                        'date' => '2026-03-18T10:00:00',
                        'typeCode' => 'RT',
                        'description' => 'Returned to shipper',
                        'serviceArea' => [
                            ['code' => 'IST', 'description' => 'ISTANBUL-TURKEY'],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::RETURNED);
});

it('parses pre-transit status', function () {
    $response = createTrackingResponseWith([
        'shipments' => [
            [
                'shipmentTrackingNumber' => '1234567890',
                'status' => 'pre-transit',
                'events' => [
                    [
                        'date' => '2026-03-13T10:00:00',
                        'typeCode' => 'TP',
                        'description' => 'Shipment data received',
                        'serviceArea' => [],
                    ],
                ],
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::PRE_TRANSIT);
});

it('handles empty shipments array', function () {
    $response = createTrackingResponseWith([
        'shipments' => [],
    ]);

    expect($response->isSuccessful())->toBeFalse();

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::UNKNOWN)
        ->and($info->trackingNumber)->toBe('');
});

it('handles error response', function () {
    $response = createTrackingResponseWith([
        'message' => 'No shipment found',
        'status' => 404,
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('No shipment found')
        ->and($response->getCode())->toBe('404');
});

it('parses estimated delivery date', function () {
    $response = createTrackingResponseWith([
        'shipments' => [
            [
                'shipmentTrackingNumber' => '1234567890',
                'status' => 'transit',
                'events' => [],
                'estimatedDeliveryDate' => '2026-03-16',
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->estimatedDelivery)->not->toBeNull()
        ->and($info->estimatedDelivery->format('Y-m-d'))->toBe('2026-03-16');
});

it('provides access to raw data', function () {
    $data = ['shipments' => [['shipmentTrackingNumber' => '123', 'status' => 'transit', 'events' => []]]];
    $response = createTrackingResponseWith($data);

    expect($response->getData())->toBe($data);
});
