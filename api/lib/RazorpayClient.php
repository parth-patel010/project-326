<?php

declare(strict_types=1);

final class RazorpayClient
{
    private string $keyId;
    private string $keySecret;
    private string $base = 'https://api.razorpay.com/v1';

    public function __construct(string $keyId, string $keySecret)
    {
        $this->keyId = $keyId;
        $this->keySecret = $keySecret;
    }

    public function createOrder(int $amountPaise, string $currency, string $receipt, array $notes = []): array
    {
        return $this->request('POST', '/orders', [
            'amount' => $amountPaise,
            'currency' => $currency,
            'receipt' => $receipt,
            'payment_capture' => 1,
            'notes' => $notes,
        ]);
    }

    public function fetchPayment(string $paymentId): array
    {
        return $this->request('GET', '/payments/' . rawurlencode($paymentId));
    }

    public static function verifyPaymentSignature(
        string $orderId,
        string $paymentId,
        string $signature,
        string $secret
    ): bool {
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
        return hash_equals($expected, $signature);
    }

    public static function verifyWebhookSignature(
        string $body,
        string $signature,
        string $secret
    ): bool {
        $expected = hash_hmac('sha256', $body, $secret);
        return hash_equals($expected, $signature);
    }

    private function request(string $method, string $path, ?array $json = null): array
    {
        $ch = curl_init($this->base . $path);
        $headers = ['Content-Type: application/json'];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->keyId . ':' . $this->keySecret,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        }
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Razorpay request failed: ' . $err);
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid Razorpay response');
        }
        if ($code >= 400) {
            $msg = $data['error']['description'] ?? ('Razorpay HTTP ' . $code);
            throw new RuntimeException($msg, $code);
        }
        return $data;
    }
}
