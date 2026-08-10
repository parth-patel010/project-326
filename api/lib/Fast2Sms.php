<?php

declare(strict_types=1);

final class Fast2Sms
{
    private string $apiKey;
    private string $route;
    private string $senderId;
    private string $templateId;

    public function __construct(array $config)
    {
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->route = (string) ($config['route'] ?? 'otp');
        $this->senderId = (string) ($config['sender_id'] ?? 'FSTSMS');
        $this->templateId = (string) ($config['template_id'] ?? '');
        if ($this->apiKey === '' || $this->apiKey === 'YOUR_FAST2SMS_API_KEY') {
            throw new RuntimeException('Fast2SMS API key not configured');
        }
    }

    /**
     * Send OTP SMS. Uses Fast2SMS OTP / DLT route.
     * @return array{ok:bool,request_id?:string,raw?:array,error?:string}
     */
    public function sendOtp(string $phone10, string $otp): array
    {
        $phone10 = preg_replace('/\D+/', '', $phone10) ?? '';
        if (strlen($phone10) === 12 && str_starts_with($phone10, '91')) {
            $phone10 = substr($phone10, 2);
        }
        if (strlen($phone10) !== 10) {
            return ['ok' => false, 'error' => 'Invalid Indian mobile number'];
        }

        $payload = [
            'route' => $this->route,
            'variables_values' => $otp,
            'numbers' => $phone10,
            'flash' => 0,
        ];
        if ($this->templateId !== '') {
            $payload['message'] = $this->templateId;
        }
        if ($this->senderId !== '') {
            $payload['sender_id'] = $this->senderId;
        }

        $ch = curl_init('https://www.fast2sms.com/dev/bulkV2');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'authorization: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            return ['ok' => false, 'error' => 'Fast2SMS curl: ' . $err];
        }

        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Fast2SMS invalid response', 'raw' => ['http' => $code, 'body' => $body]];
        }

        $ok = !empty($data['return']) || (($data['status_code'] ?? 0) == 200);
        if (!$ok) {
            return [
                'ok' => false,
                'error' => (string) ($data['message'] ?? 'Fast2SMS send failed'),
                'raw' => $data,
            ];
        }

        return [
            'ok' => true,
            'request_id' => isset($data['request_id']) ? (string) $data['request_id'] : null,
            'raw' => $data,
        ];
    }
}
