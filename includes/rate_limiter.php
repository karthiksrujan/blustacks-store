<?php
/**
 * Rate Limiter Helpers using Database Tracking
 */

/**
 * Record a request attempt in the rate_limits table.
 * 
 * @param PDO $pdo
 * @param string $ip
 * @param string $endpoint
 * @param int|null $userId
 * @return bool
 */
function rate_limit_record(PDO $pdo, $ip, $endpoint, $userId = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO rate_limits (ip_address, endpoint, user_id, attempt_time) 
            VALUES (:ip, :endpoint, :user_id, NOW())
        ");
        return $stmt->execute([
            'ip' => $ip,
            'endpoint' => $endpoint,
            'user_id' => $userId
        ]);
    } catch (PDOException $e) {
        // Log error silently, do not break application flow
        return false;
    }
}

/**
 * Check if the rate limit has been exceeded.
 * 
 * @param PDO $pdo
 * @param string $ip
 * @param string $endpoint
 * @param int $limit Max allowed attempts
 * @param int $minutes Time window in minutes
 * @param int|null $userId Optional user ID for user-based limits
 * @return bool True if exceeded, False if allowed
 */
function rate_limit_exceeded(PDO $pdo, $ip, $endpoint, $limit, $minutes, $userId = null) {
    try {
        // Clean up old rate limits to keep the table clean
        $pdo->query("DELETE FROM rate_limits WHERE attempt_time < NOW() - INTERVAL 1 DAY");

        if ($userId !== null) {
            // Check both IP and User ID for stronger protection
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM rate_limits 
                WHERE (ip_address = :ip OR user_id = :user_id) 
                  AND endpoint = :endpoint 
                  AND attempt_time > NOW() - INTERVAL :minutes MINUTE
            ");
            $stmt->execute([
                'ip' => $ip,
                'user_id' => $userId,
                'endpoint' => $endpoint,
                'minutes' => $minutes
            ]);
        } else {
            // Check by IP only
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM rate_limits 
                WHERE ip_address = :ip 
                  AND endpoint = :endpoint 
                  AND attempt_time > NOW() - INTERVAL :minutes MINUTE
            ");
            $stmt->execute([
                'ip' => $ip,
                'endpoint' => $endpoint,
                'minutes' => $minutes
            ]);
        }

        $count = $stmt->fetchColumn();
        return $count >= $limit;
    } catch (PDOException $e) {
        // Fail open to avoid blocking users if database issues occur
        return false;
    }
}

/**
 * Get the client IP address (handles proxies/Cloudflare safely).
 * 
 * @return string
 */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // X_FORWARDED_FOR can be comma separated list, pick first IP
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}
