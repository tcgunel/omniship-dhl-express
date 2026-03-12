<?php

declare(strict_types=1);

use Omniship\Common\Address;
use Omniship\Common\Package;
use Omniship\DHL\Express\Message\CreateShipmentRequest;
use Omniship\DHL\Express\Message\CreateShipmentResponse;

use function Omniship\DHL\Express\Tests\createMockHttpClient;
use function Omniship\DHL\Express\Tests\createMockRequestFactory;
use function Omniship\DHL\Express\Tests\createMockStreamFactory;

function createShipmentSuccessJson(): string
{
    return json_encode([
        'shipmentTrackingNumber' => '1234567890',
        'dispatchConfirmationNumber' => 'DHL-DC-123456',
        'trackingUrl' => 'https://www.dhl.com/en/express/tracking.html?AWB=1234567890',
        'packages' => [
            [
                'referenceNumber' => 1,
                'trackingNumber' => 'JD014600003812345671',
                'documents' => [
                    [
                        'imageFormat' => 'PDF',
                        'content' => 'JVBERi0xLjQK',
                        'typeCode' => 'label',
                    ],
                ],
            ],
        ],
        'estimatedDeliveryDate' => [
            'estimatedDeliveryDate' => '2026-03-16',
        ],
    ], JSON_THROW_ON_ERROR);
}

function createShipmentErrorJson(): string
{
    return json_encode([
        'instance' => '/mydhlapi/shipments',
        'detail' => 'Please provide valid account number',
        'title' => 'Invalid account number',
        'message' => 'Invalid account number',
        'status' => 422,
    ], JSON_THROW_ON_ERROR);
}

function createShipmentRequest(string $responseJson): CreateShipmentRequest
{
    return new CreateShipmentRequest(
        createMockHttpClient($responseJson, 201),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

it('builds correct shipment data', function () {
    $request = createShipmentRequest(createShipmentSuccessJson());
    $request->initialize([
        'username' => 'testKey',
        'password' => 'testSecret',
        'accountNumber' => '123456789',
        'shipFrom' => new Address(
            name: 'Ahmet Yilmaz',
            company: 'Sender Company',
            street1: 'Ataturk Cad. No:42',
            city: 'Istanbul',
            district: 'Kadikoy',
            postalCode: '34710',
            country: 'TR',
            phone: '+905551234567',
            email: 'sender@example.com',
            taxId: 'TR1234567890',
        ),
        'shipTo' => new Address(
            name: 'John Smith',
            company: 'Receiver Inc',
            street1: '123 Main Street',
            street2: 'Suite 100',
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            phone: '+12125551234',
            email: 'receiver@example.com',
        ),
        'packages' => [
            new Package(weight: 2.5, length: 30, width: 20, height: 15, description: 'Electronics'),
        ],
        'productCode' => 'P',
        'isCustomsDeclarable' => true,
        'declaredValue' => 150.00,
        'declaredValueCurrency' => 'USD',
    ]);

    $data = $request->getData();

    expect($data['productCode'])->toBe('P')
        ->and($data['accounts'][0]['number'])->toBe('123456789')
        ->and($data['customerDetails']['shipperDetails']['postalAddress']['cityName'])->toBe('Istanbul')
        ->and($data['customerDetails']['shipperDetails']['postalAddress']['countryCode'])->toBe('TR')
        ->and($data['customerDetails']['shipperDetails']['postalAddress']['countyName'])->toBe('Kadikoy')
        ->and($data['customerDetails']['shipperDetails']['contactInformation']['fullName'])->toBe('Ahmet Yilmaz')
        ->and($data['customerDetails']['shipperDetails']['contactInformation']['companyName'])->toBe('Sender Company')
        ->and($data['customerDetails']['shipperDetails']['registrationNumbers'][0]['number'])->toBe('TR1234567890')
        ->and($data['customerDetails']['receiverDetails']['postalAddress']['cityName'])->toBe('New York')
        ->and($data['customerDetails']['receiverDetails']['postalAddress']['provinceCode'])->toBe('NY')
        ->and($data['customerDetails']['receiverDetails']['postalAddress']['addressLine2'])->toBe('Suite 100')
        ->and($data['content']['packages'][0]['weight'])->toBe(2.5)
        ->and($data['content']['packages'][0]['dimensions']['length'])->toBe(30)
        ->and($data['content']['packages'][0]['dimensions']['width'])->toBe(20)
        ->and($data['content']['packages'][0]['dimensions']['height'])->toBe(15)
        ->and($data['content']['packages'][0]['description'])->toBe('Electronics')
        ->and($data['content']['isCustomsDeclarable'])->toBeTrue()
        ->and($data['content']['declaredValue'])->toBe(150.00)
        ->and($data['content']['declaredValueCurrency'])->toBe('USD')
        ->and($data['content']['unitOfMeasurement'])->toBe('metric');
});

it('auto-detects customs declarability for international shipments', function () {
    $request = createShipmentRequest(createShipmentSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'accountNumber' => '123',
        'shipFrom' => new Address(name: 'Sender', city: 'Istanbul', country: 'TR', phone: '555'),
        'shipTo' => new Address(name: 'Receiver', city: 'Berlin', country: 'DE', phone: '555'),
    ]);

    $data = $request->getData();

    expect($data['content']['isCustomsDeclarable'])->toBeTrue();
});

it('sets customs declarable false for domestic shipments', function () {
    $request = createShipmentRequest(createShipmentSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'accountNumber' => '123',
        'shipFrom' => new Address(name: 'Sender', city: 'Istanbul', country: 'TR', phone: '555'),
        'shipTo' => new Address(name: 'Receiver', city: 'Ankara', country: 'TR', phone: '555'),
    ]);

    $data = $request->getData();

    expect($data['content']['isCustomsDeclarable'])->toBeFalse();
});

it('handles multiple packages with quantity', function () {
    $request = createShipmentRequest(createShipmentSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'accountNumber' => '123',
        'shipFrom' => new Address(name: 'Sender', city: 'Istanbul', country: 'TR', phone: '555'),
        'shipTo' => new Address(name: 'Receiver', city: 'Ankara', country: 'TR', phone: '555'),
        'packages' => [
            new Package(weight: 1.0, length: 10, width: 10, height: 10, quantity: 2),
        ],
    ]);

    $data = $request->getData();

    expect($data['content']['packages'])->toHaveCount(2)
        ->and($data['content']['packages'][0]['weight'])->toBe(1.0)
        ->and($data['content']['packages'][1]['weight'])->toBe(1.0);
});

it('sends request and returns successful response', function () {
    $request = createShipmentRequest(createShipmentSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'accountNumber' => '123',
        'shipFrom' => new Address(name: 'Sender', city: 'Istanbul', country: 'TR', phone: '555'),
        'shipTo' => new Address(name: 'Receiver', city: 'Ankara', country: 'TR', phone: '555'),
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(CreateShipmentResponse::class)
        ->and($response->isSuccessful())->toBeTrue()
        ->and($response->getTrackingNumber())->toBe('1234567890');
});

it('sends request and returns error response', function () {
    $request = createShipmentRequest(createShipmentErrorJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'accountNumber' => '123',
        'shipFrom' => new Address(name: 'Sender', city: 'Istanbul', country: 'TR', phone: '555'),
        'shipTo' => new Address(name: 'Receiver', city: 'Ankara', country: 'TR', phone: '555'),
    ]);

    $response = $request->send();

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Invalid account number')
        ->and($response->getCode())->toBe('422');
});

it('throws exception when required fields are missing', function () {
    $request = createShipmentRequest(createShipmentSuccessJson());
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);
