<?php

declare(strict_types=1);

namespace Omniship\DHL\Express\Message;

use Omniship\Common\Message\AbstractHttpRequest;

abstract class AbstractDhlExpressRequest extends AbstractHttpRequest
{
    private const BASE_URL_PRODUCTION = 'https://express.api.dhl.com/mydhlapi';
    private const BASE_URL_TEST = 'https://express.api.dhl.com/mydhlapi/test';

    public function getUsername(): ?string
    {
        return $this->getParameter('username');
    }

    public function setUsername(string $username): static
    {
        return $this->setParameter('username', $username);
    }

    public function getPassword(): ?string
    {
        return $this->getParameter('password');
    }

    public function setPassword(string $password): static
    {
        return $this->setParameter('password', $password);
    }

    public function getAccountNumber(): ?string
    {
        return $this->getParameter('accountNumber');
    }

    public function setAccountNumber(string $accountNumber): static
    {
        return $this->setParameter('accountNumber', $accountNumber);
    }

    protected function getBaseUrl(): string
    {
        return $this->getTestMode()
            ? self::BASE_URL_TEST
            : self::BASE_URL_PRODUCTION;
    }

    protected function getBasicAuthHeader(): string
    {
        return 'Basic ' . base64_encode($this->getUsername() . ':' . $this->getPassword());
    }

    protected function generateMessageReference(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @return array<string, string>
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Authorization' => $this->getBasicAuthHeader(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Message-Reference' => $this->generateMessageReference(),
            'Message-Reference-Date' => gmdate('c'),
        ];
    }

    /**
     * @param array<string, mixed> $jsonData
     */
    protected function sendJsonRequest(string $method, string $url, array $jsonData = []): mixed
    {
        $headers = $this->getDefaultHeaders();
        $body = $jsonData !== [] ? json_encode($jsonData, JSON_THROW_ON_ERROR) : null;

        if ($method === 'GET' || $method === 'DELETE') {
            $body = null;
        }

        $response = $this->sendHttpRequest(
            method: $method,
            url: $url,
            headers: $headers,
            body: $body,
        );

        $responseBody = (string) $response->getBody();

        if ($responseBody === '') {
            return [];
        }

        /** @var array<string, mixed> */
        return json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    }
}
