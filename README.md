# Omniship DHL Express

DHL Express (MyDHL API) carrier driver for the Omniship multi-carrier shipping library.

## Installation

```bash
composer require tcgunel/omniship-dhl-express
```

## Usage

```php
use Omniship\Omniship;
use Omniship\Common\Address;
use Omniship\Common\Package;

$dhl = Omniship::create('DHL_Express');
$dhl->initialize([
    'username' => 'your-api-key',       // MyDHL API Key
    'password' => 'your-api-secret',    // MyDHL API Secret
    'accountNumber' => '123456789',     // DHL account number (9-10 digits)
    'testMode' => true,
]);
```

### Create Shipment

```php
$response = $dhl->createShipment([
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
    'productCode' => 'P',                  // Express Worldwide (default)
    'plannedShippingDate' => '2026-03-13T10:00:00GMT+03:00',
    // 'pickupRequested' => false,          // default
    // 'isCustomsDeclarable' => true,       // auto-detected from countries
    // 'declaredValue' => 150.00,
    // 'declaredValueCurrency' => 'USD',
    // 'labelFormat' => 'pdf',             // pdf, zpl, epl (default: pdf)
])->send();

if ($response->isSuccessful()) {
    echo $response->getTrackingNumber();                // "1234567890"
    echo $response->getDispatchConfirmationNumber();    // needed for cancellation
    echo $response->getTrackingUrl();                   // DHL tracking page URL
    echo $response->getEstimatedDeliveryDate();         // "2026-03-16"

    $label = $response->getLabel();
    if ($label !== null) {
        file_put_contents('label.pdf', base64_decode($label->content));
    }
}
```

### Track Shipment

```php
$response = $dhl->getTrackingStatus([
    'trackingNumber' => '1234567890',
])->send();

if ($response->isSuccessful()) {
    $info = $response->getTrackingInfo();
    echo $info->status->value;           // "delivered", "in_transit", etc.
    echo $info->trackingNumber;          // AWB number
    echo $info->carrier;                 // "DHL Express"
    echo $info->serviceName;             // "Express Worldwide"
    echo $info->signedBy;                // "J.SMITH" (if delivered)

    foreach ($info->events as $event) {
        echo $event->description;        // "Arrived at DHL Sort Facility"
        echo $event->city;               // "NEW YORK"
        echo $event->country;            // "USA"
        echo $event->occurredAt->format('Y-m-d H:i');
    }
}
```

### Cancel Shipment

Cancels the pickup associated with a shipment. The `dispatchConfirmationNumber` from the create response is required.

```php
$response = $dhl->cancelShipment([
    'trackingNumber' => '1234567890',
    'dispatchConfirmationNumber' => 'DHL-DC-123456',
    // 'requestorName' => 'Ahmet Yilmaz',
    // 'cancelReason' => '006',  // default: '008' (Other)
])->send();

if ($response->isCancelled()) {
    echo 'Pickup cancelled';
}
```

### Get Rates

```php
$response = $dhl->getRates([
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
    // 'productCode' => 'P',    // omit to get all available products
])->send();

if ($response->isSuccessful()) {
    foreach ($response->getRates() as $rate) {
        echo $rate['productCode'];       // "P"
        echo $rate['productName'];       // "EXPRESS WORLDWIDE"
        echo $rate['price'];             // 85.50 (billing currency)
        echo $rate['currency'];          // "USD"
        echo $rate['transitDays'];       // 3
        echo $rate['estimatedDelivery']; // "2026-03-16T23:59:00"
    }
}
```

## Product Codes

| Code | Product | Type |
|------|---------|------|
| `P` | Express Worldwide | Non-document |
| `D` | Express Worldwide | Document |
| `U` | Express Worldwide (EU) | Non-document |
| `K` | Express 9:00 | Non-document |
| `T` | Express 12:00 | Non-document |
| `N` | Domestic Express | Non-document |
| `H` | Economy Select | Non-document |
| `G` | Express Worldwide (B2C) | Non-document |

## Tracking Status Mapping

| DHL Event | Description | ShipmentStatus |
|-----------|-------------|----------------|
| `TP` | Shipment data received | `PRE_TRANSIT` |
| `PU` | Picked up | `PICKED_UP` |
| `PL/DF/AF/CC/CR/CI` | In transit events | `IN_TRANSIT` |
| `WC` | With delivery courier | `OUT_FOR_DELIVERY` |
| `OK/DL/DD` | Delivered | `DELIVERED` |
| `NH/BA/MS/OH` | Delivery failure | `FAILURE` |
| `RR/RT` | Returned to shipper | `RETURNED` |

## Cancel Reason Codes

| Code | Description |
|------|-------------|
| `001` | Package not ready |
| `002` | Rates too high |
| `003` | Transit time too long |
| `006` | Shipment cancelled by customer |
| `008` | Other (default) |

## API Details

- **Transport**: REST/JSON via PSR-18 HTTP client
- **Auth**: HTTP Basic Authentication (API Key + API Secret)
- **Base URL**: `https://express.api.dhl.com/mydhlapi/`
- **Test URL**: `https://express.api.dhl.com/mydhlapi/test/`
- **Create**: `POST /shipments` — returns tracking number + base64 labels
- **Track**: `GET /tracking?shipmentTrackingNumber={number}` — returns events with type codes
- **Cancel**: `DELETE /shipments/{trackingNumber}/pickup` — cancels pickup (not label void)
- **Rates**: `GET /rates` — returns available products with pricing

> **Note:** DHL Express does not provide a label-voiding endpoint. The cancel operation cancels the associated pickup request.

## Testing

```bash
docker compose run --rm php bash -c "cd omniship-dhl-express && vendor/bin/pest"
```

## License

MIT
