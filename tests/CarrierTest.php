<?php

declare(strict_types=1);

use Omniship\DHL\Express\Carrier;
use Omniship\DHL\Express\Message\CancelShipmentRequest;
use Omniship\DHL\Express\Message\CreateShipmentRequest;
use Omniship\DHL\Express\Message\GetRatesRequest;
use Omniship\DHL\Express\Message\GetTrackingStatusRequest;

use function Omniship\DHL\Express\Tests\createMockHttpClient;
use function Omniship\DHL\Express\Tests\createMockRequestFactory;
use function Omniship\DHL\Express\Tests\createMockStreamFactory;

function createCarrier(): Carrier
{
    $carrier = new Carrier(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );

    $carrier->initialize([
        'username' => 'testApiKey',
        'password' => 'testApiSecret',
        'accountNumber' => '123456789',
        'testMode' => true,
    ]);

    return $carrier;
}

it('returns correct name and short name', function () {
    $carrier = createCarrier();

    expect($carrier->getName())->toBe('DHL Express')
        ->and($carrier->getShortName())->toBe('DHL_Express');
});

it('initializes with default parameters', function () {
    $carrier = createCarrier();

    expect($carrier->getUsername())->toBe('testApiKey')
        ->and($carrier->getPassword())->toBe('testApiSecret')
        ->and($carrier->getAccountNumber())->toBe('123456789')
        ->and($carrier->getTestMode())->toBeTrue();
});

it('returns test base URL when test mode is on', function () {
    $carrier = createCarrier();

    expect($carrier->getBaseUrl())->toBe('https://express.api.dhl.com/mydhlapi/test');
});

it('returns production base URL when test mode is off', function () {
    $carrier = createCarrier();
    $carrier->setTestMode(false);

    expect($carrier->getBaseUrl())->toBe('https://express.api.dhl.com/mydhlapi');
});

it('creates shipment request', function () {
    $carrier = createCarrier();
    $request = $carrier->createShipment();

    expect($request)->toBeInstanceOf(CreateShipmentRequest::class);
});

it('creates tracking request', function () {
    $carrier = createCarrier();
    $request = $carrier->getTrackingStatus(['trackingNumber' => '1234567890']);

    expect($request)->toBeInstanceOf(GetTrackingStatusRequest::class);
});

it('creates cancel request', function () {
    $carrier = createCarrier();
    $request = $carrier->cancelShipment([
        'trackingNumber' => '1234567890',
        'dispatchConfirmationNumber' => 'DC-123',
    ]);

    expect($request)->toBeInstanceOf(CancelShipmentRequest::class);
});

it('creates rates request', function () {
    $carrier = createCarrier();
    $request = $carrier->getRates();

    expect($request)->toBeInstanceOf(GetRatesRequest::class);
});
