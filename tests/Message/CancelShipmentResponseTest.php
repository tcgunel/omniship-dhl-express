<?php

declare(strict_types=1);

use Omniship\DHL\Express\Message\CancelShipmentRequest;
use Omniship\DHL\Express\Message\CancelShipmentResponse;

use function Omniship\DHL\Express\Tests\createMockHttpClient;
use function Omniship\DHL\Express\Tests\createMockRequestFactory;
use function Omniship\DHL\Express\Tests\createMockStreamFactory;

function createCancelResponseWith(array $data): CancelShipmentResponse
{
    $request = new CancelShipmentRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'trackingNumber' => '1234567890',
        'dispatchConfirmationNumber' => 'DC-123',
    ]);

    return new CancelShipmentResponse($request, $data);
}

it('parses successful cancellation', function () {
    $response = createCancelResponseWith([
        'cancelledPickup' => true,
        'message' => 'Pickup cancelled successfully',
    ]);

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->isCancelled())->toBeTrue()
        ->and($response->getMessage())->toBe('Pickup cancelled successfully')
        ->and($response->getCode())->toBe('200');
});

it('treats empty response as success', function () {
    $response = createCancelResponseWith([]);

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->isCancelled())->toBeTrue();
});

it('parses failed cancellation', function () {
    $response = createCancelResponseWith([
        'message' => 'Cannot cancel pickup',
        'status' => 400,
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->isCancelled())->toBeFalse()
        ->and($response->getMessage())->toBe('Cannot cancel pickup')
        ->and($response->getCode())->toBe('400');
});

it('provides access to raw data', function () {
    $data = ['cancelledPickup' => true];
    $response = createCancelResponseWith($data);

    expect($response->getData())->toBe($data);
});
