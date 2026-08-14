<?php

declare(strict_types=1);

/**
 * Expo push to delivery partners (ExponentPushToken[...]).
 * Fire-and-forget — never throws into order flows.
 */
final class PartnerPush
{
    public static function notifyNewOffer(int $partnerId, array $order, int $ttlSeconds = 60): void
    {
        try {
            $pdo = db();
            $stmt = $pdo->prepare(
                'SELECT push_token FROM partner_push_tokens
                 WHERE partner_id = :p AND is_active = 1
                 ORDER BY updated_at DESC LIMIT 8'
            );
            $stmt->execute([':p' => $partnerId]);
            $tokens = [];
            foreach ($stmt->fetchAll() as $row) {
                $t = trim((string) ($row['push_token'] ?? ''));
                if ($t !== '' && !in_array($t, $tokens, true)) {
                    $tokens[] = $t;
                }
            }
            if (!$tokens) {
                return;
            }

            $orderId = (string) ($order['public_id'] ?? $order['id'] ?? '');
            $hotel = (string) ($order['restaurant_name'] ?? 'Restaurant');
            $earn = isset($order['partner_earn_paise'])
                ? ((int) $order['partner_earn_paise']) / 100
                : ((int) ($order['delivery_fee_paise'] ?? 0)) / 100;
            $title = 'New Delivery Order';
            $body = $hotel . ($orderId !== '' ? ' · #' . $orderId : '');

            $messages = [];
            foreach ($tokens as $token) {
                $messages[] = [
                    'to' => $token,
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'priority' => 'high',
                    'channelId' => 'delivery-job-alerts',
                    'ttl' => max(30, $ttlSeconds),
                    'data' => [
                        'type' => 'delivery_job',
                        'order_id' => $orderId,
                        'order_number' => $orderId,
                        'hotel_name' => $hotel,
                        'delivery_fee' => $earn,
                        'ttl' => $ttlSeconds,
                    ],
                ];
            }

            $accessToken = Env::get('EXPO_ACCESS_TOKEN', '') ?? '';
            $headers = ['Content-Type: application/json', 'Accept: application/json'];
            if ($accessToken !== '') {
                $headers[] = 'Authorization: Bearer ' . $accessToken;
            }

            $ch = curl_init('https://exp.host/--/api/v2/push/send');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT_MS => 2500,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => json_encode($messages),
            ]);
            @curl_exec($ch);
            @curl_close($ch);
        } catch (Throwable $e) {
            // never block accept / dispatch
        }
    }
}
