<?php
/**
 * Global Utility Functions
 */

/**
 * Escape HTML output for XSS prevention.
 * 
 * @param string|null $value
 * @return string
 */
function esc($value) {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format bytes to human readable format (KB, MB, GB).
 * 
 * @param int $bytes
 * @param int $precision
 * @return string
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Render visual star ratings using SVG.
 * 
 * @param float $rating Rating from 0 to 5
 * @param string $size Tailwind size class (default: w-4 h-4)
 * @return string HTML output
 */
function render_stars($rating, $size = 'w-4 h-4') {
    $rating = (float)$rating;
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
    $emptyStars = 5 - $fullStars - $halfStar;
    
    $html = '<div class="flex items-center text-yellow-400 gap-0.5">';
    
    // Full stars
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<svg class="' . $size . '" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>';
    }
    
    // Half star
    if ($halfStar) {
        $html .= '<svg class="' . $size . '" fill="currentColor" viewBox="0 0 20 20">
            <defs>
                <linearGradient id="halfStarGrad">
                    <stop offset="50%" stop-color="currentColor"/>
                    <stop offset="50%" stop-color="#D1D5DB"/>
                </linearGradient>
            </defs>
            <path fill="url(#halfStarGrad)" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
        </svg>';
    }
    
    // Empty stars
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<svg class="' . $size . ' text-gray-300 dark:text-gray-600" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Check if a user has wishlisted a specific app.
 * 
 * @param PDO $pdo
 * @param int $userId
 * @param int $appId
 * @return bool
 */
function is_wishlisted(PDO $pdo, $userId, $appId) {
    if (!$userId) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND app_id = ?");
    $stmt->execute([$userId, $appId]);
    return (bool)$stmt->fetch();
}

/**
 * Check if a user has downloaded a specific app.
 * 
 * @param PDO $pdo
 * @param int $userId
 * @param int $appId
 * @return bool
 */
function has_downloaded(PDO $pdo, $userId, $appId) {
    if (!$userId) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM downloads WHERE user_id = ? AND app_id = ? LIMIT 1");
    $stmt->execute([$userId, $appId]);
    return (bool)$stmt->fetch();
}

/**
 * Scan an uploaded file for malware and integrity.
 * Checks extension whitelist, MIME type, file headers (magic bytes), and optionally VirusTotal.
 * 
 * @param string $filePath Full path to the uploaded file
 * @param string $originalName Original file name to extract extension
 * @return array ['success' => bool, 'error' => string]
 */
function scan_file_for_malware($filePath, $originalName) {
    if (!file_exists($filePath)) {
        return ['success' => false, 'error' => 'File does not exist for scanning.'];
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $whitelist = ['apk', 'exe', 'zip'];
    
    // 1. Check Extension Whitelist
    if (!in_array($extension, $whitelist)) {
        return ['success' => false, 'error' => 'Unsupported file extension. Only APK, EXE, and ZIP are allowed.'];
    }

    // 2. Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);

    $validMimes = [
        'zip' => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'],
        'apk' => ['application/vnd.android.package-archive', 'application/zip', 'application/x-zip-compressed'],
        'exe' => ['application/x-msdownload', 'application/x-msdos-program', 'application/octet-stream']
    ];

    if (!isset($validMimes[$extension]) || !in_array($mimeType, $validMimes[$extension])) {
        // Log MIME type mismatch but be cautious as exe/apk can sometimes report application/octet-stream
        if ($extension === 'exe' && $mimeType !== 'application/octet-stream' && $mimeType !== 'application/x-msdownload') {
            return ['success' => false, 'error' => 'MIME-type mismatch. File content does not match extension.'];
        }
    }

    // 3. Verify Magic Bytes / File Signatures
    $handle = fopen($filePath, 'rb');
    if (!$handle) {
        return ['success' => false, 'error' => 'Unable to read file signature.'];
    }
    $bytes = fread($handle, 4);
    fclose($handle);

    $hex = bin2hex($bytes);

    if ($extension === 'zip' || $extension === 'apk') {
        // ZIP/APK should start with PK signature (504b0304)
        if (strpos($hex, '504b0304') !== 0) {
            return ['success' => false, 'error' => 'Invalid file structure. Zip/APK header signature missing.'];
        }
    } elseif ($extension === 'exe') {
        // EXE should start with MZ signature (4d5a)
        if (strpos($hex, '4d5a') !== 0) {
            return ['success' => false, 'error' => 'Invalid file structure. Executable header signature missing.'];
        }
    }

    // 4. Optional VirusTotal API Hash Check
    if (defined('VIRUSTOTAL_API_KEY') && VIRUSTOTAL_API_KEY !== '') {
        $fileHash = hash_file('sha256', $filePath);
        $url = 'https://www.virustotal.com/api/v3/files/' . $fileHash;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-apikey: ' . VIRUSTOTAL_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $maliciousStats = $data['data']['attributes']['last_analysis_stats']['malicious'] ?? 0;
            if ($maliciousStats > 0) {
                return ['success' => false, 'error' => 'VirusTotal flagged this file as malicious! Threat count: ' . $maliciousStats];
            }
        }
        // If 404, the file is unknown/new to VirusTotal, which is fine (we don't force upload 100MB on shared hosting)
    }

    return ['success' => true, 'error' => ''];
}

/**
 * Get dynamic or actual app icon URL.
 * 
 * @param array $app App row data
 * @return string
 */
function get_app_icon_url($app) {
    $url = $app['icon_url'] ?? '';
    if (empty($url)) {
        return 'https://ui-avatars.com/api/?name=' . urlencode($app['name'] ?? 'App') . '&background=0284c7&color=fff&size=128&bold=true';
    }
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return $url;
    }
    // Check local upload
    $localFile = UPLOAD_DIR . '/icons/' . $url;
    if (file_exists($localFile)) {
        return BASE_URL . 'uploads/icons/' . $url;
    }
    // Fallback UI Avatar
    return 'https://ui-avatars.com/api/?name=' . urlencode($app['name'] ?? 'App') . '&background=0284c7&color=fff&size=128&bold=true';
}

/**
 * Get dynamic or actual app banner URL.
 * 
 * @param array $app App row data
 * @return string
 */
function get_app_banner_url($app) {
    $url = $app['banner_url'] ?? '';
    if (empty($url)) {
        return 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=1200&auto=format&fit=crop&q=60';
    }
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return $url;
    }
    $localFile = UPLOAD_DIR . '/banners/' . $url;
    if (file_exists($localFile)) {
        return BASE_URL . 'uploads/banners/' . $url;
    }
    // Fallback gradient banner
    return 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=1200&auto=format&fit=crop&q=60';
}

/**
 * Get screenshot URL.
 * 
 * @param array|string $screenshot Screenshot data array or image string
 * @return string
 */
function get_screenshot_url($screenshot) {
    $url = is_array($screenshot) ? ($screenshot['image_url'] ?? '') : $screenshot;
    if (empty($url)) {
        return 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800&auto=format&fit=crop&q=60';
    }
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return $url;
    }
    $localFile = UPLOAD_DIR . '/screenshots/' . $url;
    if (file_exists($localFile)) {
        return BASE_URL . 'uploads/screenshots/' . $url;
    }
    // Fallback screenshot
    return 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800&auto=format&fit=crop&q=60';
}

