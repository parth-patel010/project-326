<?php

declare(strict_types=1);

/**
 * Fire-and-forget emit to realtime Socket.IO HTTP bridge.
 */
final class Realtime
{
    public static function emit(string $event, array $payload, ?string $room = null): void
    {
        $url = Env::get('REALTIME_EMIT_URL', '') ?? '';
        $secret = Env::get('REALTIME_SECRET', 'foodmitra_realtime') ?? 'foodmitra_realtime';
        if ($url === '') {
            return;
        }
        $body = json_encode([
            'event' => $event,
            'room' => $room,
            'payload' => $payload,
            'secret' => $secret,
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 400,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
        ]);
        @curl_exec($ch);
        @curl_close($ch);
    }
}
