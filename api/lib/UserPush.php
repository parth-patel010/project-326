<?php

declare(strict_types=1);

/**
 * Expo push to customer app users (ExponentPushToken[...]).
 */
final class UserPush
{
    private const EXPO_URL = 'https://exp.host/--/api/v2/push/send';
    private const BATCH_SIZE = 100;

    public static function ensureTables(?PDO $pdo = null): void
    {
        $pdo = $pdo ?? db();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS user_push_tokens (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id BIGINT UNSIGNED NOT NULL,
              push_token VARCHAR(512) NOT NULL,
              platform VARCHAR(32) NOT NULL DEFAULT 'android',
              client VARCHAR(32) NOT NULL DEFAULT 'eas',
              device_id VARCHAR(128) NULL,
              is_active TINYINT(1) NOT NULL DEFAULT 1,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_user_push_token (push_token),
              KEY idx_upt_user (user_id, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS user_notification_campaigns (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              title VARCHAR(255) NOT NULL,
              body TEXT NOT NULL,
              audience ENUM('all_users','specific_user') NOT NULL DEFAULT 'all_users',
              target_phone VARCHAR(15) NULL,
              status ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
              scheduled_at DATETIME NULL,
              sent_at DATETIME NULL,
              sent_count INT UNSIGNED NOT NULL DEFAULT 0,
              fail_count INT UNSIGNED NOT NULL DEFAULT 0,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_unc_status (status, scheduled_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public static function registerToken(
        int $userId,
        string $pushToken,
        string $platform = 'android',
        string $client = 'eas',
        ?string $deviceId = null,
        ?PDO $pdo = null
    ): void {
        $pdo = $pdo ?? db();
        self::ensureTables($pdo);
        $platform = strtolower($platform);
        if (!in_array($platform, ['android', 'ios', 'web'], true)) {
            $platform = 'android';
        }
        $pdo->prepare(
            'INSERT INTO user_push_tokens (user_id, push_token, platform, client, device_id, is_active)
             VALUES (:u, :t, :plat, :c, :d, 1)
             ON DUPLICATE KEY UPDATE
               user_id = VALUES(user_id),
               platform = VALUES(platform),
               client = VALUES(client),
               device_id = VALUES(device_id),
               is_active = 1,
               updated_at = CURRENT_TIMESTAMP'
        )->execute([
            ':u' => $userId,
            ':t' => $pushToken,
            ':plat' => $platform,
            ':c' => $client !== '' ? $client : 'eas',
            ':d' => $deviceId !== null && $deviceId !== '' ? $deviceId : null,
        ]);
    }

    public static function unregisterToken(int $userId, ?string $pushToken = null, ?PDO $pdo = null): void
    {
        $pdo = $pdo ?? db();
        try {
            if ($pushToken !== null && $pushToken !== '') {
                $pdo->prepare(
                    'UPDATE user_push_tokens SET is_active = 0
                     WHERE user_id = :u AND push_token = :t'
                )->execute([':u' => $userId, ':t' => $pushToken]);
            } else {
                $pdo->prepare(
                    'UPDATE user_push_tokens SET is_active = 0 WHERE user_id = :u'
                )->execute([':u' => $userId]);
            }
        } catch (Throwable $e) {
            // Table may not exist yet
        }
    }

    /**
     * Create a campaign and optionally send immediately.
     *
     * @return array{id:int,status:string,sent_count:int,fail_count:int}
     */
    public static function createCampaign(
        string $title,
        string $body,
        string $audience = 'all_users',
        ?string $targetPhone = null,
        ?string $scheduledAt = null,
        bool $sendNow = true,
        ?PDO $pdo = null
    ): array {
        $pdo = $pdo ?? db();
        self::ensureTables($pdo);

        $audience = $audience === 'specific_user' ? 'specific_user' : 'all_users';
        if ($audience === 'specific_user' && ($targetPhone === null || $targetPhone === '')) {
            throw new InvalidArgumentException('target_phone required for specific_user');
        }

        $status = (!$sendNow && $scheduledAt !== null && $scheduledAt !== '') ? 'pending' : 'pending';
        $pdo->prepare(
            'INSERT INTO user_notification_campaigns
              (title, body, audience, target_phone, status, scheduled_at)
             VALUES (:title, :body, :aud, :phone, :status, :sched)'
        )->execute([
            ':title' => $title,
            ':body' => $body,
            ':aud' => $audience,
            ':phone' => $audience === 'specific_user' ? $targetPhone : null,
            ':status' => $status,
            ':sched' => (!$sendNow && $scheduledAt) ? $scheduledAt : null,
        ]);
        $id = (int) $pdo->lastInsertId();

        if ($sendNow || ($scheduledAt === null || $scheduledAt === '')) {
            return self::sendCampaign($id, $pdo);
        }

        return [
            'id' => $id,
            'status' => 'pending',
            'sent_count' => 0,
            'fail_count' => 0,
        ];
    }

    /**
     * Send a pending/failed campaign (or re-send by id).
     *
     * @return array{id:int,status:string,sent_count:int,fail_count:int}
     */
    public static function sendCampaign(int $campaignId, ?PDO $pdo = null): array
    {
        $pdo = $pdo ?? db();
        self::ensureTables($pdo);

        $st = $pdo->prepare('SELECT * FROM user_notification_campaigns WHERE id = :id LIMIT 1');
        $st->execute([':id' => $campaignId]);
        $campaign = $st->fetch();
        if (!$campaign) {
            throw new InvalidArgumentException('Campaign not found');
        }

        $pdo->prepare(
            "UPDATE user_notification_campaigns SET status = 'sending' WHERE id = :id"
        )->execute([':id' => $campaignId]);

        $tokens = self::tokensForCampaign($campaign, $pdo);
        $sent = 0;
        $fail = 0;

        if ($tokens === []) {
            $pdo->prepare(
                "UPDATE user_notification_campaigns
                 SET status = 'sent', sent_at = NOW(), sent_count = 0, fail_count = 0
                 WHERE id = :id"
            )->execute([':id' => $campaignId]);
            return [
                'id' => $campaignId,
                'status' => 'sent',
                'sent_count' => 0,
                'fail_count' => 0,
            ];
        }

        $title = (string) $campaign['title'];
        $body = (string) $campaign['body'];
        $messages = [];
        $tokenByIndex = [];
        foreach ($tokens as $token) {
            $messages[] = [
                'to' => $token,
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'priority' => 'high',
                'channelId' => 'default',
                'data' => [
                    'type' => 'admin_broadcast',
                    'campaign_id' => $campaignId,
                ],
            ];
            $tokenByIndex[] = $token;
        }

        try {
            $chunks = array_chunk($messages, self::BATCH_SIZE);
            $tokenChunks = array_chunk($tokenByIndex, self::BATCH_SIZE);
            foreach ($chunks as $i => $chunk) {
                $result = self::postExpo($chunk);
                $tickets = $result['data'] ?? [];
                if (!is_array($tickets)) {
                    $tickets = [];
                }
                foreach ($chunk as $idx => $_msg) {
                    $ticket = $tickets[$idx] ?? null;
                    $status = is_array($ticket) ? (string) ($ticket['status'] ?? '') : '';
                    if ($status === 'ok') {
                        $sent++;
                        continue;
                    }
                    $fail++;
                    $err = is_array($ticket) ? (string) ($ticket['details']['error'] ?? $ticket['message'] ?? '') : '';
                    if ($err === 'DeviceNotRegistered') {
                        $tok = $tokenChunks[$i][$idx] ?? null;
                        if ($tok) {
                            self::deactivateToken($tok, $pdo);
                        }
                    }
                }
            }

            $finalStatus = $sent > 0 || $fail === 0 ? 'sent' : 'failed';
            $pdo->prepare(
                'UPDATE user_notification_campaigns
                 SET status = :st, sent_at = NOW(), sent_count = :sc, fail_count = :fc
                 WHERE id = :id'
            )->execute([
                ':st' => $finalStatus,
                ':sc' => $sent,
                ':fc' => $fail,
                ':id' => $campaignId,
            ]);

            return [
                'id' => $campaignId,
                'status' => $finalStatus,
                'sent_count' => $sent,
                'fail_count' => $fail,
            ];
        } catch (Throwable $e) {
            $pdo->prepare(
                "UPDATE user_notification_campaigns
                 SET status = 'failed', sent_count = :sc, fail_count = :fc
                 WHERE id = :id"
            )->execute([
                ':sc' => $sent,
                ':fc' => max($fail, count($tokens) - $sent),
                ':id' => $campaignId,
            ]);
            throw $e;
        }
    }

    /** Process due scheduled campaigns (pending + scheduled_at <= NOW). */
    public static function processDueCampaigns(?PDO $pdo = null, int $limit = 20): int
    {
        $pdo = $pdo ?? db();
        self::ensureTables($pdo);
        $rows = $pdo->query(
            "SELECT id FROM user_notification_campaigns
             WHERE status = 'pending'
               AND scheduled_at IS NOT NULL
               AND scheduled_at <= NOW()
             ORDER BY scheduled_at ASC
             LIMIT " . (int) $limit
        )->fetchAll();
        $n = 0;
        foreach ($rows as $row) {
            try {
                self::sendCampaign((int) $row['id'], $pdo);
                $n++;
            } catch (Throwable $e) {
                // continue other campaigns
            }
        }
        return $n;
    }

    /** @return list<string> */
    private static function tokensForCampaign(array $campaign, PDO $pdo): array
    {
        if (($campaign['audience'] ?? '') === 'specific_user') {
            $phone = preg_replace('/\D+/', '', (string) ($campaign['target_phone'] ?? '')) ?? '';
            if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
                $phone = substr($phone, 2);
            }
            $st = $pdo->prepare(
                'SELECT t.push_token
                 FROM user_push_tokens t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE t.is_active = 1 AND u.phone = :p'
            );
            $st->execute([':p' => $phone]);
        } else {
            $st = $pdo->query(
                'SELECT t.push_token
                 FROM user_push_tokens t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE t.is_active = 1 AND u.is_active = 1'
            );
        }
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $t = trim((string) ($row['push_token'] ?? ''));
            if ($t !== '' && !in_array($t, $out, true)) {
                $out[] = $t;
            }
        }
        return $out;
    }

    private static function deactivateToken(string $token, PDO $pdo): void
    {
        try {
            $pdo->prepare(
                'UPDATE user_push_tokens SET is_active = 0 WHERE push_token = :t'
            )->execute([':t' => $token]);
        } catch (Throwable $e) {
            // ignore
        }
    }

    /** @param list<array<string,mixed>> $messages */
    private static function postExpo(array $messages): array
    {
        $accessToken = '';
        if (class_exists('Env')) {
            $accessToken = Env::get('EXPO_ACCESS_TOKEN', '') ?? '';
        }
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($accessToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }

        $ch = curl_init(self::EXPO_URL);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($messages, JSON_UNESCAPED_UNICODE),
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $errno) {
            throw new RuntimeException('Expo push failed: ' . ($err !== '' ? $err : 'network error'));
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid Expo push response (HTTP ' . $code . ')');
        }
        return $decoded;
    }
}
