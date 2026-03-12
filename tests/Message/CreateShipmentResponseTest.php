<?php

declare(strict_types=1);

use Omniship\Common\Enum\LabelFormat;
use Omniship\DHL\Express\Message\CreateShipmentRequest;
use Omniship\DHL\Express\Message\CreateShipmentResponse;

use function Omniship\DHL\Express\Tests\createMockHttpClient;
use function Omniship\DHL\Express\Tests\createMockRequestFactory;
use function Omniship\DHL\Express\Tests\createMockStreamFactory;

function createCreateResponseWith(array $data): CreateShipmentResponse
{
    $request = new CreateShipmentRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'key',
        'password' => 'secret',
        'accountNumber' => '123',
    ]);

    return new CreateShipmentResponse($request, $data);
}

it('parses successful shipment creation', function () {
    $response = createCreateResponseWith([
        'shipmentTrackingNumber' => '1234567890',
        'dispatchConfirmationNumber' => 'DHL-DC-123456',
        'trackingUrl' => 'https://www.dhl.com/en/express/tracking.html?AWB=1234567890',
        'packages' => [
            [
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
    ]);

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->getTrackingNumber())->toBe('1234567890')
        ->and($response->getShipmentId())->toBe('1234567890')
        ->and($response->getBarcode())->toBe('1234567890')
        ->and($response->getDispatchConfirmationNumber())->toBe('DHL-DC-123456')
        ->and($response->getTrackingUrl())->toBe('https://www.dhl.com/en/express/tracking.html?AWB=1234567890')
        ->and($response->getEstimatedDeliveryDate())->toBe('2026-03-16')
        ->and($response->getCode())->toBe('201');
});

it('extracts label from response', function () {
    $response = createCreateResponseWith([
        'shipmentTrackingNumber' => '1234567890',
        'packages' => [
            [
                'documents' => [
                    [
                        'imageFormat' => 'PDF',
                        'content' => 'JVBERi0xLjQK',
                        'typeCode' => 'label',
                    ],
                    [
                        'imageFormat' => 'PDF',
                        'content' => 'waybill-content',
                        'typeCode' => 'waybillDoc',
                    ],
                ],
            ],
        ],
    ]);

    $label = $response->getLabel();

    expect($label)->not->toBeNull()
        ->and($label->trackingNumber)->toBe('1234567890')
        ->and($label->content)->toBe('JVBERi0xLjQK')
        ->and($label->format)->toBe(LabelFormat::PDF);
});

it('handles ZPL label format', function () {
    $response = createCreateResponseWith([
        'shipmentTrackingNumber' => '1234567890',
        'packages' => [
            [
                'documents' => [
                    [
                        'imageFormat' => 'ZPL',
                        'content' => '^XA^FO50,50^FDHello^FS^XZ',
                        'typeCode' => 'label',
                    ],
                ],
            ],
        ],
    ]);

    $label = $response->getLabel();

    expect($label)->not->toBeNull()
        ->and($label->format)->toBe(LabelFormat::ZPL);
});

it('returns null label when no documents', function () {
    $response = createCreateResponseWith([
        'shipmentTrackingNumber' => '1234567890',
        'packages' => [
            [
                'trackingNumber' => 'JD014600003812345671',
            ],
        ],
    ]);

    expect($response->getLabel())->toBeNull();
});

it('parses error response', function () {
    $response = createCreateResponseWith([
        'instance' => '/mydhlapi/shipments',
        'detail' => 'Please provide valid account number',
        'message' => 'Invalid account number',
        'status' => 422,
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Invalid account number')
        ->and($response->getCode())->toBe('422')
        ->and($response->getTrackingNumber())->toBeNull()
        ->and($response->getLabel())->toBeNull();
});

it('returns null for charges', function () {
    $response = createCreateResponseWith([
        'shipmentTrackingNumber' => '1234567890',
    ]);

    expect($response->getTotalCharge())->toBeNull()
        ->and($response->getCurrency())->toBeNull();
});

it('provides access to raw data', function () {
    $data = ['shipmentTrackingNumber' => '1234567890'];
    $response = createCreateResponseWith($data);

    expect($response->getData())->toBe($data);
});
