<?php

declare(strict_types=1);

use Omniship\DHL\Express\Message\CancelShipmentRequest;
use Omniship\DHL\Express\Message\CancelShipmentResponse;

use function Omniship\DHL\Express\Tests\createMockHttpClient;
use function Omniship\DHL\Express\Tests\createMockRequestFactory;
use function Omniship\DHL\Express\Tests\createMockStreamFactory;

function createCancelPickupSuccessJson(): string
{
    return json_encode([
        'cancelledPickup' => true,
        'message' => 'Pickup cancelled successfully',
    ], JSON_THROW_ON_ERROR);
}

function createCancelPickupFailureJson(): string
{
    return json_encode([
        'message' => 'Cannot cancel pickup',
        'status' => 400,
    ], JSON_THROW_ON_ERROR);
}

function createCancelPickupRequest(string $responseJson): CancelShipmentRequest
{
    return new CancelShipmentRequest(
        createMockHttpClient($responseJson),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

it('builds correct cancel data', function () {
    $request = createCancelPickupRequest(createCancelPickupSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'trackingNumber' => '1234567890',
        'dispatchConfirmationNumber' => 'DHL-DC-123456',
        'requestorName' => 'Ahmet Yilmaz',
        'cancelReason' => '006',
    ]);

    $data = $request->getData();

    expect($data['trackingNumber'])->toBe('1234567890')
        ->and($data['dispatchConfirmationNumber'])->toBe('DHL-DC-123456')
        ->and($data['requestorName'])->toBe('Ahmet Yilmaz')
        ->and($data['reason'])->toBe('006');
});

it('uses default cancel reason', function () {
    $request = createCancelPickupRequest(createCancelPickupSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'trackingNumber' => '1234567890',
        'dispatchConfirmationNumber' => 'DHL-DC-123456',
    ]);

    $data = $request->getData();

    expect($data['reason'])->toBe('008');
});

it('sends request and returns successful cancel', function () {
    $request = createCancelPickupRequest(createCancelPickupSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'trackingNumber' => '1234567890',
        'dispatchConfirmationNumber' => 'DHL-DC-123456',
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(CancelShipmentResponse::class)
        ->and($response->isSuccessful())->toBeTrue()
        ->and($response->isCancelled())->toBeTrue();
});

it('sends request and returns failed cancel', function () {
    $request = createCancelPickupRequest(createCancelPickupFailureJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'trackingNumber' => '1234567890',
        'dispatchConfirmationNumber' => 'DHL-DC-123456',
    ]);

    $response = $request->send();

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->isCancelled())->toBeFalse()
        ->and($response->getMessage())->toBe('Cannot cancel pickup');
});

it('throws exception when required fields are missing', function () {
    $request = createCancelPickupRequest(createCancelPickupSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'trackingNumber' => '1234567890',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);
