<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use PDO;

final class RateLimiter
{
    public static function allow(string $action, int $maxHits, int $windowSeconds): bool
    {
        if ($maxHits < 1 || $windowSeconds < 1) return true;

        $client = self::clientHash();
        $now = time();
        $startTs = intdiv($now, $windowSeconds) * $windowSeconds;
        $windowStart = (new DateTimeImmutable('@' . $startTs))->format('Y-m-d H:i:s');
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            $s = $pdo->prepare('SELECT id,hit_count FROM request_rate_limits WHERE action_key=? AND client_hash=? AND window_start=? FOR UPDATE');
            $s->execute([$action,$client,$windowStart]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $hits = (int)$row['hit_count'];
                if ($hits >= $maxHits) {
                    $pdo->commit();
                    return false;
                }
                $u = $pdo->prepare('UPDATE request_rate_limits SET hit_count=hit_count+1 WHERE id=?');
                $u->execute([(int)$row['id']]);
            } else {
                $i = $pdo->prepare('INSERT INTO request_rate_limits(action_key,client_hash,window_start,hit_count) VALUES(?,?,?,1)');
                $i->execute([$action,$client,$windowStart]);
            }
            $pdo->commit();
        } catch (\Throwable) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return true;
        }

        if (random_int(1,100) === 1) {
            try {
                $pdo->exec("DELETE FROM request_rate_limits WHERE window_start < (NOW() - INTERVAL 2 DAY)");
            } catch (\Throwable) {}
        }
        return true;
    }

    public static function reject(int $retryAfter = 60): never
    {
        http_response_code(429);
        header('Retry-After: ' . max(1,$retryAfter));
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Troppe richieste. Riprova più tardi.');
    }

    private static function clientHash(): string
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,200);
        $salt = (string)(getenv('APP_KEY') ?: 'idemaclima-rate-limit');
        return hash('sha256',$ip.'|'.$ua.'|'.$salt);
    }
}
