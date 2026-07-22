<?php
/**
 * Developer Submit / Edit App Form
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/../includes/functions.php';

// Gate access
require_developer();

$user_id = get_logged_in_user_id();
$user_role = get_logged_in_user_role();
$ip = get_client_ip();

$app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $app_id > 0;

$app = null;
$error_msg = '';
$success_msg = '';

// If editing, load app details and verify ownership
if ($is_edit) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM apps WHERE id = ?");
        $stmt->execute([$app_id]);
        $app = $stmt->fetch();

        if (!$app) {
            die("Error: Application not found.");
        }

        // Only developer owner or admin can edit
        if ($app['developer_id'] != $user_id && $user_role !== 'admin') {
            die("Error: Unauthorized access.");
        }

        // Fetch permissions for edit pre-populate
        $perm_stmt = $pdo->prepare("SELECT permission_name FROM permissions WHERE app_id = ?");
        $perm_stmt->execute([$app_id]);
        $app_perms = $perm_stmt->fetchAll(PDO::FETCH_COLUMN);
        $perms_string = implode(', ', $app_perms);

    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
} else {
    $perms_string = '';
}

// Fetch categories for dropdown
try {
    $cat_stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// POST Form Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Check
    csrf_enforce();

    // 1. Rate Limiting on Uploads (5 per hour)
    if (rate_limit_exceeded($pdo, $ip, 'upload', 5, 60, $user_id)) {
        $error_msg = "You have exceeded the file upload rate limit. Please try again in an hour.";
    } else {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $short_desc = isset($_POST['short_desc']) ? trim($_POST['short_desc']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $version = isset($_POST['version']) ? trim($_POST['version']) : '';
        $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
        $file_source = isset($_POST['file_source']) ? $_POST['file_source'] : 'upload';
        $external_url = isset($_POST['external_url']) ? trim($_POST['external_url']) : '';
        $permissions = isset($_POST['permissions']) ? trim($_POST['permissions']) : '';

        // Validation
        if (empty($name) || empty($short_desc) || empty($description) || $category_id <= 0 || empty($version)) {
            $error_msg = "Please fill in all required fields.";
        } elseif (mb_strlen($short_desc) > 120) {
            $error_msg = "Short description must be 120 characters or less.";
        } else {
            
            // Database transactions for schema consistency
            try {
                $pdo->beginTransaction();

                // 2. Handle File Uploads
                // Default placeholders / current files
                $icon_url = $is_edit ? $app['icon_url'] : '';
                $banner_url = $is_edit ? $app['banner_url'] : '';
                $file_path_or_url = $is_edit ? $app['file_path_or_url'] : '';
                $file_size = $is_edit ? $app['file_size'] : 0;

                // Create folder if missing
                if (!is_dir(UPLOAD_DIR . '/icons')) mkdir(UPLOAD_DIR . '/icons', 0755, true);
                if (!is_dir(UPLOAD_DIR . '/banners')) mkdir(UPLOAD_DIR . '/banners', 0755, true);
                if (!is_dir(UPLOAD_DIR . '/screenshots')) mkdir(UPLOAD_DIR . '/screenshots', 0755, true);
                if (!is_dir(UPLOAD_DIR . '/apps')) mkdir(UPLOAD_DIR . '/apps', 0755, true);

                // A. Icon Upload (Square image)
                if (isset($_FILES['icon_file'])) {
                    $icon_error = $_FILES['icon_file']['error'];
                    if ($icon_error === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['icon_file']['tmp_name'];
                        $orig_name = $_FILES['icon_file']['name'];
                        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                        
                        if (in_array($ext, ['png', 'jpg', 'jpeg']) && $_FILES['icon_file']['size'] <= 2097152) { // 2MB
                            $icon_url = bin2hex(random_bytes(16)) . '.' . $ext;
                            move_uploaded_file($tmp_name, UPLOAD_DIR . '/icons/' . $icon_url);
                        } else {
                            throw new Exception("Invalid icon file. Must be PNG/JPG under 2MB.");
                        }
                    } elseif ($icon_error !== UPLOAD_ERR_NO_FILE) {
                        if ($icon_error === UPLOAD_ERR_INI_SIZE || $icon_error === UPLOAD_ERR_FORM_SIZE) {
                            throw new Exception("Icon file is too large. Max size allowed is 2MB.");
                        }
                        throw new Exception("Icon upload error (code $icon_error). Please try again.");
                    } elseif (!$is_edit) {
                        throw new Exception("Please upload an app icon.");
                    }
                }

                // B. Banner Upload (Wide aspect image)
                if (isset($_FILES['banner_file'])) {
                    $banner_error = $_FILES['banner_file']['error'];
                    if ($banner_error === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['banner_file']['tmp_name'];
                        $orig_name = $_FILES['banner_file']['name'];
                        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                        
                        if (in_array($ext, ['png', 'jpg', 'jpeg']) && $_FILES['banner_file']['size'] <= 5242880) { // 5MB
                            $banner_url = bin2hex(random_bytes(16)) . '.' . $ext;
                            move_uploaded_file($tmp_name, UPLOAD_DIR . '/banners/' . $banner_url);
                        } else {
                            throw new Exception("Invalid banner file. Must be PNG/JPG under 5MB.");
                        }
                    } elseif ($banner_error !== UPLOAD_ERR_NO_FILE) {
                        if ($banner_error === UPLOAD_ERR_INI_SIZE || $banner_error === UPLOAD_ERR_FORM_SIZE) {
                            throw new Exception("Banner file is too large. Max size allowed is 5MB.");
                        }
                        throw new Exception("Banner upload error (code $banner_error). Please try again.");
                    } elseif (!$is_edit) {
                        throw new Exception("Please upload a wide banner image.");
                    }
                }

                // C. App Binary File / External Link
                if ($file_source === 'upload') {
                    if (isset($_FILES['app_file'])) {
                        $app_error = $_FILES['app_file']['error'];
                        if ($app_error === UPLOAD_ERR_OK) {
                            $tmp_name = $_FILES['app_file']['tmp_name'];
                            $orig_name = $_FILES['app_file']['name'];
                            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                            
                            if ($_FILES['app_file']['size'] > MAX_FILE_SIZE) {
                                throw new Exception("Application file exceeds 100MB limit.");
                            }

                            // Scan for malware using helper (MIME check, magic bytes, VirusTotal lookup)
                            $scan = scan_file_for_malware($tmp_name, $orig_name);
                            if (!$scan['success']) {
                                throw new Exception($scan['error']);
                            }

                            // Save file
                            $file_path_or_url = bin2hex(random_bytes(16)) . '.' . $ext;
                            move_uploaded_file($tmp_name, UPLOAD_DIR . '/apps/' . $file_path_or_url);
                            $file_size = $_FILES['app_file']['size'];
                        } elseif ($app_error !== UPLOAD_ERR_NO_FILE) {
                            if ($app_error === UPLOAD_ERR_INI_SIZE || $app_error === UPLOAD_ERR_FORM_SIZE) {
                                throw new Exception("The uploaded file exceeds the server's upload size limit (usually 10MB on free hosting). Please use the 'Provide External Download Link' option for files larger than 10MB.");
                            }
                            throw new Exception("App file upload error (code $app_error). Please try again.");
                        } elseif (!$is_edit) {
                            throw new Exception("Please upload an app binary file (.apk, .exe, .zip).");
                        }
                    }
                } else {
                    // External link source
                    if (empty($external_url) || !filter_var($external_url, FILTER_VALIDATE_URL)) {
                        throw new Exception("Please provide a valid external download URL.");
                    }
                    $file_path_or_url = $external_url;
                    $file_size = 0; // cannot read remote file size easily, default to 0
                }

                // Record rate limit attempt
                rate_limit_record($pdo, $ip, 'upload', $user_id);

                // Check required fields for new apps
                if (!$is_edit && (empty($icon_url) || empty($banner_url) || empty($file_path_or_url))) {
                    throw new Exception("Missing required media assets or download binaries.");
                }

                // 3. Save App row
                if ($is_edit) {
                    // Edits reset status to pending for safety!
                    $sql = "
                        UPDATE apps 
                        SET name = :name, description = :description, short_desc = :short_desc, 
                            category_id = :category_id, version = :version, price = :price, 
                            icon_url = :icon_url, banner_url = :banner_url, 
                            file_size = :file_size, file_path_or_url = :file_path_or_url, 
                            status = 'pending', updated_at = NOW()
                        WHERE id = :app_id
                    ";
                    $save_stmt = $pdo->prepare($sql);
                    $save_stmt->execute([
                        'name' => $name,
                        'description' => $description,
                        'short_desc' => $short_desc,
                        'category_id' => $category_id,
                        'version' => $version,
                        'price' => $price,
                        'icon_url' => $icon_url,
                        'banner_url' => $banner_url,
                        'file_size' => $file_size,
                        'file_path_or_url' => $file_path_or_url,
                        'app_id' => $app_id
                    ]);
                } else {
                    $sql = "
                        INSERT INTO apps (developer_id, name, description, short_desc, category_id, icon_url, banner_url, version, file_size, file_path_or_url, status, price, created_at, updated_at) 
                        VALUES (:dev_id, :name, :description, :short_desc, :category_id, :icon_url, :banner_url, :version, :file_size, :file_path_or_url, 'pending', :price, NOW(), NOW())
                    ";
                    $save_stmt = $pdo->prepare($sql);
                    $save_stmt->execute([
                        'dev_id' => $user_id,
                        'name' => $name,
                        'description' => $description,
                        'short_desc' => $short_desc,
                        'category_id' => $category_id,
                        'version' => $version,
                        'price' => $price,
                        'icon_url' => $icon_url,
                        'banner_url' => $banner_url,
                        'file_size' => $file_size,
                        'file_path_or_url' => $file_path_or_url
                    ]);
                    $app_id = $pdo->lastInsertId();
                }

                // 4. Handle Screenshots
                if (isset($_FILES['screenshot_files'])) {
                    $files = $_FILES['screenshot_files'];
                    $count = count($files['name']);
                    
                    // If we uploaded new screenshots, add them
                    for ($i = 0; $i < $count; $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK && $files['size'][$i] <= 5242880) {
                            $tmp_name = $files['tmp_name'][$i];
                            $orig_name = $files['name'][$i];
                            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                            
                            if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
                                $ss_filename = bin2hex(random_bytes(16)) . '.' . $ext;
                                if (move_uploaded_file($tmp_name, UPLOAD_DIR . '/screenshots/' . $ss_filename)) {
                                    $ss_stmt = $pdo->prepare("INSERT INTO screenshots (app_id, image_url, order_index) VALUES (?, ?, ?)");
                                    $ss_stmt->execute([$app_id, $ss_filename, $i]);
                                }
                            }
                        }
                    }
                }

                // 5. Handle Permissions
                // Clear old
                $clear_perm_stmt = $pdo->prepare("DELETE FROM permissions WHERE app_id = ?");
                $clear_perm_stmt->execute([$app_id]);

                if (!empty($permissions)) {
                    $perms_array = explode(',', $permissions);
                    $ins_perm_stmt = $pdo->prepare("INSERT INTO permissions (app_id, permission_name) VALUES (?, ?)");
                    foreach ($perms_array as $p_name) {
                        $p_name = trim($p_name);
                        if (!empty($p_name)) {
                            $ins_perm_stmt->execute([$app_id, $p_name]);
                        }
                    }
                }

                $pdo->commit();
                
                header("Location: " . BASE_URL . "developer/dashboard.php?success=1");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-10">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 md:p-8 shadow-xl space-y-6">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white"><?php echo $is_edit ? 'Edit App Listing' : 'Submit New Application'; ?></h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Provide application package binaries, details, and screenshot previews</p>
        </div>
        <hr class="border-slate-200 dark:border-slate-800">

        <?php if (!empty($error_msg)): ?>
            <div class="p-4 rounded-xl bg-red-50 text-red-800 border border-red-200 dark:bg-red-950/20 dark:text-red-400 dark:border-red-900/50 text-sm">
                <i class="fa-solid fa-circle-exclamation mr-2"></i><?php echo esc($error_msg); ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" class="space-y-6 text-sm">
            <?php csrf_field(); ?>

            <!-- App Name -->
            <div class="space-y-1">
                <label for="name" class="font-semibold text-slate-700 dark:text-slate-350">Application Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="name" required value="<?php echo $is_edit ? esc($app['name']) : ''; ?>"
                       class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                       placeholder="e.g. My Amazing Utility">
            </div>

            <!-- Short Description -->
            <div class="space-y-1">
                <label for="short_desc" class="font-semibold text-slate-700 dark:text-slate-350">Short Summary <span class="text-red-500">*</span> <span class="text-xs text-slate-400 font-normal">(max 120 chars)</span></label>
                <input type="text" name="short_desc" id="short_desc" required maxlength="120" value="<?php echo $is_edit ? esc($app['short_desc']) : ''; ?>"
                       class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                       placeholder="A brief tagline showing on listing cards.">
            </div>

            <!-- Full Description -->
            <div class="space-y-1">
                <label for="description" class="font-semibold text-slate-700 dark:text-slate-350">Full Description <span class="text-red-500">*</span></label>
                <textarea name="description" id="description" rows="6" required
                          class="w-full rounded-lg border border-slate-300 bg-slate-50 p-3 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                          placeholder="Explain what the application does, features, how to use..."><?php echo $is_edit ? esc($app['description']) : ''; ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Category -->
                <div class="space-y-1">
                    <label for="category_id" class="font-semibold text-slate-700 dark:text-slate-350">Category <span class="text-red-500">*</span></label>
                    <select name="category_id" id="category_id" required
                            class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                        <option value="">Choose category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($is_edit && $app['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo esc($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Version -->
                <div class="space-y-1">
                    <label for="version" class="font-semibold text-slate-700 dark:text-slate-350">Version <span class="text-red-500">*</span></label>
                    <input type="text" name="version" id="version" required value="<?php echo $is_edit ? esc($app['version']) : '1.0.0'; ?>"
                           class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                           placeholder="e.g. 1.0.0">
                </div>

                <!-- Price -->
                <div class="space-y-1">
                    <label for="price" class="font-semibold text-slate-700 dark:text-slate-350">Price <span class="text-xs text-slate-400 font-normal">(0.00 for Free)</span></label>
                    <input type="number" name="price" id="price" step="0.01" min="0" value="<?php echo $is_edit ? esc($app['price']) : '0.00'; ?>"
                           class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                           placeholder="e.g. 4.99">
                </div>
            </div>

            <!-- Permissions Requirements -->
            <div class="space-y-1">
                <label for="permissions" class="font-semibold text-slate-700 dark:text-slate-350">System Permissions <span class="text-xs text-slate-400 font-normal">(comma-separated list)</span></label>
                <input type="text" name="permissions" id="permissions" value="<?php echo esc($perms_string); ?>"
                       class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                       placeholder="e.g. Internet Access, Camera Control, Local Storage">
            </div>

            <!-- Media Uploads Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-5 bg-slate-50 dark:bg-slate-950/20 border border-slate-150 dark:border-slate-800 rounded-2xl">
                <!-- Icon file -->
                <div class="space-y-2">
                    <label class="font-semibold text-slate-700 dark:text-slate-350 block">App Icon <?php echo !$is_edit ? '<span class="text-red-500">*</span>' : ''; ?></label>
                    <input type="file" name="icon_file" accept=".png,.jpg,.jpeg" <?php echo !$is_edit ? 'required' : ''; ?>
                           class="text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-950/30 dark:file:text-primary-400">
                    <p class="text-[10px] text-slate-400">Square PNG/JPG, max 2MB.</p>
                </div>
                <!-- Banner file -->
                <div class="space-y-2">
                    <label class="font-semibold text-slate-700 dark:text-slate-350 block">Wide Banner Image <?php echo !$is_edit ? '<span class="text-red-500">*</span>' : ''; ?></label>
                    <input type="file" name="banner_file" accept=".png,.jpg,.jpeg" <?php echo !$is_edit ? 'required' : ''; ?>
                           class="text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-950/30 dark:file:text-primary-400">
                    <p class="text-[10px] text-slate-400">Wide aspect ratio (16:9), max 5MB.</p>
                </div>
            </div>

            <!-- Screenshots Upload -->
            <div class="space-y-2">
                <label class="font-semibold text-slate-700 dark:text-slate-350 block">Upload Screenshots <span class="text-xs text-slate-400 font-normal">(select multiple files, max 5)</span></label>
                <input type="file" name="screenshot_files[]" accept=".png,.jpg,.jpeg" multiple
                       class="text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-950/30 dark:file:text-primary-400">
                <p class="text-[10px] text-slate-400">PNG/JPG screenshots, max 5MB each. Replaces existing screenshots if edit.</p>
            </div>

            <!-- Download Binaries Source -->
            <div class="space-y-4 p-5 bg-slate-50 dark:bg-slate-950/20 border border-slate-150 dark:border-slate-800 rounded-2xl">
                <h3 class="font-bold text-slate-900 dark:text-white text-base">Download Package Binary</h3>
                
                <!-- Source Toggle -->
                <div class="flex gap-6">
                    <label class="inline-flex items-center text-sm cursor-pointer">
                        <input type="radio" name="file_source" value="upload" checked id="src-upload" class="text-primary-600 focus:ring-primary-500">
                        <span class="ml-2 text-slate-700 dark:text-slate-300">Upload Package (.apk, .exe, .zip)</span>
                    </label>
                    <label class="inline-flex items-center text-sm cursor-pointer">
                        <input type="radio" name="file_source" value="url" <?php echo ($is_edit && filter_var($app['file_path_or_url'], FILTER_VALIDATE_URL)) ? 'checked' : ''; ?> id="src-url" class="text-primary-600 focus:ring-primary-500">
                        <span class="ml-2 text-slate-700 dark:text-slate-300">Provide External Download Link</span>
                    </label>
                </div>

                <!-- File upload box -->
                <div class="space-y-2 transition duration-200" id="pkg-upload-box">
                    <label class="font-semibold text-slate-700 dark:text-slate-350 block">Upload Package Binary <?php echo !$is_edit ? '<span class="text-red-500">*</span>' : ''; ?></label>
                    <input type="file" name="app_file" accept=".apk,.exe,.zip"
                           class="text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-950/30 dark:file:text-primary-400">
                    <p class="text-[10px] text-slate-400">Accepts APK, EXE, and ZIP formats, up to 100MB. Automatically scanned for malware.</p>
                </div>

                <!-- URL link input -->
                <div class="space-y-1 transition duration-200 hidden" id="pkg-url-box">
                    <label for="external_url" class="font-semibold text-slate-700 dark:text-slate-350 block">External Download Link <span class="text-red-500">*</span></label>
                    <input type="url" name="external_url" id="external_url" value="<?php echo ($is_edit && filter_var($app['file_path_or_url'], FILTER_VALIDATE_URL)) ? esc($app['file_path_or_url']) : ''; ?>"
                           class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                           placeholder="https://example.com/downloads/my_app.apk">
                    <p class="text-[10px] text-slate-400">Standard workaround for hosting files larger than 100MB.</p>
                </div>
            </div>

            <!-- Footer CTAs -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <a href="<?php echo BASE_URL; ?>developer/dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-750 px-6 py-2.5 rounded-full font-semibold transition dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                    Cancel
                </a>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-2.5 rounded-full font-semibold shadow-md transition">
                    <?php echo $is_edit ? 'Save Changes' : 'Submit for Review'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const srcUpload = document.getElementById('src-upload');
        const srcUrl = document.getElementById('src-url');
        const pkgUploadBox = document.getElementById('pkg-upload-box');
        const pkgUrlBox = document.getElementById('pkg-url-box');

        function togglePkgSource() {
            if (srcUpload && srcUpload.checked) {
                pkgUploadBox.classList.remove('hidden');
                pkgUrlBox.classList.add('hidden');
            } else if (srcUrl && srcUrl.checked) {
                pkgUploadBox.classList.add('hidden');
                pkgUrlBox.classList.remove('hidden');
            }
        }

        if (srcUpload && srcUrl) {
            srcUpload.addEventListener('change', togglePkgSource);
            srcUrl.addEventListener('change', togglePkgSource);
            
            // Trigger initial
            togglePkgSource();
        }
    });
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
