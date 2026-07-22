<?php
/**
 * User Account Portal (Unified Login, Signup, and Dashboard)
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate_limiter.php';
require_once __DIR__ . '/includes/functions.php';

$ip = get_client_ip();
$error_msg = '';
$success_msg = '';

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // All post actions require CSRF protection
    csrf_enforce();
    $action = $_POST['action'];

    if ($action === 'login') {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        // 1. Check Rate Limiting
        if (rate_limit_exceeded($pdo, $ip, 'login', 5, 15)) {
            $error_msg = "Too many login attempts. Please wait 15 minutes.";
        } elseif (empty($email) || empty($password)) {
            $error_msg = "Please fill in all fields.";
        } else {
            // 2. Validate Credentials
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    $success_msg = "Logged in successfully!";
                    
                    // Redirect
                    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : BASE_URL . 'account.php';
                    header("Location: " . $redirect);
                    exit;
                } else {
                    // Record attempt for rate limit
                    rate_limit_record($pdo, $ip, 'login');
                    $error_msg = "Invalid email or password.";
                }
            } catch (PDOException $e) {
                $error_msg = "An error occurred. Please try again later.";
            }
        }

    } elseif ($action === 'register') {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : 'user';

        // Validate role selection
        if (!in_array($role, ['user', 'developer'])) {
            $role = 'user';
        }

        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $error_msg = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = "Invalid email address.";
        } elseif ($password !== $confirm_password) {
            $error_msg = "Passwords do not match.";
        } elseif (strlen($password) < 8) {
            $error_msg = "Password must be at least 8 characters long.";
        } else {
            try {
                // Check if username or email already exists
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $check_stmt->execute([$username, $email]);
                
                if ($check_stmt->fetch()) {
                    $error_msg = "Username or email is already registered.";
                } else {
                    // Create user
                    $pw_hash = password_hash($password, PASSWORD_BCRYPT);
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password_hash, role, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $insert_stmt->execute([$username, $email, $pw_hash, $role]);

                    $user_id = $pdo->lastInsertId();
                    
                    // Set session
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role;

                    header("Location: " . BASE_URL . "account.php?welcome=1");
                    exit;
                }
            } catch (PDOException $e) {
                $error_msg = "An error occurred during registration. Please try again.";
            }
        }

    } elseif ($action === 'become_developer') {
        // Upgrade role to developer
        if (is_logged_in() && get_logged_in_user_role() === 'user') {
            try {
                $uid = get_logged_in_user_id();
                $up_stmt = $pdo->prepare("UPDATE users SET role = 'developer' WHERE id = ?");
                if ($up_stmt->execute([$uid])) {
                    $_SESSION['role'] = 'developer';
                    header("Location: " . BASE_URL . "developer/dashboard.php?activated=1");
                    exit;
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to activate developer status.";
            }
        }

    } elseif ($action === 'change_password') {
        // Password change logic (Logged In)
        if (!is_logged_in()) {
            die("Unauthorized.");
        }
        $uid = get_logged_in_user_id();
        $curr_pass = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $conf_pass = $_POST['confirm_new_password'] ?? '';

        if (empty($curr_pass) || empty($new_pass) || empty($conf_pass)) {
            $error_msg = "Please fill in all password fields.";
        } elseif ($new_pass !== $conf_pass) {
            $error_msg = "New passwords do not match.";
        } elseif (strlen($new_pass) < 8) {
            $error_msg = "New password must be at least 8 characters long.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$uid]);
                $pw_hash = $stmt->fetchColumn();

                if (password_verify($curr_pass, $pw_hash)) {
                    $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
                    $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $update_stmt->execute([$new_hash, $uid]);
                    $success_msg = "Password updated successfully.";
                } else {
                    $error_msg = "Current password is incorrect.";
                }
            } catch (PDOException $e) {
                $error_msg = "An error occurred while changing password.";
            }
        }

    } elseif ($action === 'delete_review') {
        // Delete review action
        if (!is_logged_in()) {
            die("Unauthorized.");
        }
        $review_id = isset($_POST['review_id']) ? (int)$_POST['review_id'] : 0;
        $uid = get_logged_in_user_id();
        
        try {
            // Check if review exists and belongs to user
            $stmt = $pdo->prepare("SELECT app_id FROM reviews WHERE id = ? AND user_id = ?");
            $stmt->execute([$review_id, $uid]);
            $app_id = $stmt->fetchColumn();

            if ($app_id) {
                $del_stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
                $del_stmt->execute([$review_id]);

                // Recalculate average rating
                $avg_stmt = $pdo->prepare("SELECT AVG(rating) FROM reviews WHERE app_id = ?");
                $avg_stmt->execute([$app_id]);
                $avg_rating = (float)$avg_stmt->fetchColumn();

                $update_stmt = $pdo->prepare("UPDATE apps SET average_rating = ? WHERE id = ?");
                $update_stmt->execute([$avg_rating, $app_id]);

                $success_msg = "Review deleted successfully.";
            } else {
                $error_msg = "Review not found or unauthorized.";
            }
        } catch (PDOException $e) {
            $error_msg = "Database error while deleting review.";
        }
    }
}

// Fetch user dashboard data if logged in
$user_data = [];
$downloads_history = [];
$wishlist_items = [];
$my_reviews = [];

if (is_logged_in()) {
    $uid = get_logged_in_user_id();
    
    try {
        // User profile info
        $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $user_stmt->execute([$uid]);
        $user_data = $user_stmt->fetch();

        // Download history
        $dl_stmt = $pdo->prepare("
            SELECT d.downloaded_at, a.id AS app_id, a.name, a.icon_url, a.short_desc, a.version, a.file_size
            FROM downloads d
            JOIN apps a ON d.app_id = a.id
            WHERE d.user_id = ? AND (a.status = 'approved' AND a.is_published = 1 OR a.developer_id = ?)
            ORDER BY d.downloaded_at DESC
        ");
        $dl_stmt->execute([$uid, $uid]);
        $downloads_history = $dl_stmt->fetchAll();

        // Wishlist
        $wl_stmt = $pdo->prepare("
            SELECT w.created_at, a.id AS app_id, a.name, a.icon_url, a.short_desc, a.average_rating, a.download_count, a.price
            FROM wishlist w
            JOIN apps a ON w.app_id = a.id
            WHERE w.user_id = ? AND (a.status = 'approved' AND a.is_published = 1 OR a.developer_id = ?)
            ORDER BY w.created_at DESC
        ");
        $wl_stmt->execute([$uid, $uid]);
        $wishlist_items = $wl_stmt->fetchAll();

        // Reviews written by this user
        $rev_stmt = $pdo->prepare("
            SELECT r.*, a.name AS app_name, a.icon_url
            FROM reviews r
            JOIN apps a ON r.app_id = a.id
            WHERE r.user_id = ? AND (a.status = 'approved' AND a.is_published = 1 OR a.developer_id = ?)
            ORDER BY r.created_at DESC
        ");
        $rev_stmt->execute([$uid, $uid]);
        $my_reviews = $rev_stmt->fetchAll();

    } catch (PDOException $e) {
        $error_msg = "Database error fetching profile details.";
    }
}

$page_title = is_logged_in() ? "My Account" : "Sign In / Sign Up";
require_once __DIR__ . '/includes/header.php';
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
    
    <!-- Status Messages -->
    <?php if (!empty($error_msg)): ?>
        <div class="mb-6 p-4 rounded-xl bg-red-50 text-red-800 border border-red-200 dark:bg-red-950/20 dark:text-red-400 dark:border-red-900/50 text-sm">
            <i class="fa-solid fa-circle-exclamation mr-2"></i><?php echo esc($error_msg); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($success_msg)): ?>
        <div class="mb-6 p-4 rounded-xl bg-green-50 text-green-800 border border-green-200 dark:bg-green-950/20 dark:text-green-400 dark:border-green-900/50 text-sm">
            <i class="fa-solid fa-circle-check mr-2"></i><?php echo esc($success_msg); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['timeout'])): ?>
        <div class="mb-6 p-4 rounded-xl bg-yellow-50 text-yellow-800 border border-yellow-200 dark:bg-yellow-950/20 dark:text-yellow-400 dark:border-yellow-900/50 text-sm">
            <i class="fa-solid fa-triangle-exclamation mr-2"></i>Your session has expired due to inactivity. Please log in again.
        </div>
    <?php endif; ?>

    <?php if (!is_logged_in()): ?>
        
        <!-- ========================================== -->
        <!-- SIGN IN / SIGN UP FORMS -->
        <!-- ========================================== -->
        <div class="max-w-md mx-auto bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-xl p-8 space-y-6">
            
            <!-- Auth Form Tabs -->
            <div class="flex border-b border-slate-200 dark:border-slate-800">
                <button id="tab-login-btn" class="flex-1 pb-4 text-center font-bold border-b-2 border-primary-500 text-primary-600 dark:text-primary-400 text-sm">
                    Sign In
                </button>
                <button id="tab-register-btn" class="flex-1 pb-4 text-center font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-b-2 border-transparent text-sm">
                    Create Account
                </button>
            </div>

            <!-- Login Form -->
            <form action="" method="POST" id="form-login" class="space-y-4">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="login">
                
                <div class="space-y-1">
                    <label for="login_email" class="text-xs font-semibold text-slate-500 dark:text-slate-400">Email Address</label>
                    <input type="email" name="email" id="login_email" required
                           class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                           placeholder="you@example.com">
                </div>
                
                <div class="space-y-1">
                    <label for="login_password" class="text-xs font-semibold text-slate-500 dark:text-slate-400">Password</label>
                    <input type="password" name="password" id="login_password" required
                           class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                           placeholder="••••••••">
                </div>
                
                <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-2.5 rounded-full text-sm shadow-md transition">
                    Sign In
                </button>
            </form>

            <!-- Register Form -->
            <form action="" method="POST" id="form-register" class="space-y-4 hidden">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="register">

                <div class="space-y-1">
                    <label for="register_username" class="text-xs font-semibold text-slate-500 dark:text-slate-400">Username</label>
                    <input type="text" name="username" id="register_username" required
                           class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                           placeholder="john_doe">
                </div>

                <div class="space-y-1">
                    <label for="register_email" class="text-xs font-semibold text-slate-500 dark:text-slate-400">Email Address</label>
                    <input type="email" name="email" id="register_email" required
                           class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                           placeholder="you@example.com">
                </div>

                <div class="space-y-1">
                    <label for="register_password" class="text-xs font-semibold text-slate-500 dark:text-slate-400">Password (min 8 characters)</label>
                    <input type="password" name="password" id="register_password" required minlength="8"
                           class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                           placeholder="••••••••">
                </div>

                <div class="space-y-1">
                    <label for="register_confirm" class="text-xs font-semibold text-slate-500 dark:text-slate-400">Confirm Password</label>
                    <input type="password" name="confirm_password" id="register_confirm" required
                           class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                           placeholder="••••••••">
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">I want to register as a:</label>
                    <div class="flex gap-4">
                        <label class="inline-flex items-center text-sm">
                            <input type="radio" name="role" value="user" checked class="text-primary-600 focus:ring-primary-500 dark:bg-slate-800 dark:border-slate-700">
                            <span class="ml-2 text-slate-700 dark:text-slate-300">Standard User</span>
                        </label>
                        <label class="inline-flex items-center text-sm">
                            <input type="radio" name="role" value="developer" class="text-primary-600 focus:ring-primary-500 dark:bg-slate-800 dark:border-slate-700">
                            <span class="ml-2 text-slate-700 dark:text-slate-300">Developer</span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-2.5 rounded-full text-sm shadow-md transition">
                    Create Account
                </button>
            </form>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const tabLogin = document.getElementById('tab-login-btn');
                const tabRegister = document.getElementById('tab-register-btn');
                const formLogin = document.getElementById('form-login');
                const formRegister = document.getElementById('form-register');

                if (tabLogin && tabRegister && formLogin && formRegister) {
                    tabLogin.addEventListener('click', () => {
                        tabLogin.className = "flex-1 pb-4 text-center font-bold border-b-2 border-primary-500 text-primary-600 dark:text-primary-400 text-sm";
                        tabRegister.className = "flex-1 pb-4 text-center font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-b-2 border-transparent text-sm";
                        formLogin.classList.remove('hidden');
                        formRegister.classList.add('hidden');
                    });

                    tabRegister.addEventListener('click', () => {
                        tabRegister.className = "flex-1 pb-4 text-center font-bold border-b-2 border-primary-500 text-primary-600 dark:text-primary-400 text-sm";
                        tabLogin.className = "flex-1 pb-4 text-center font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-b-2 border-transparent text-sm";
                        formRegister.classList.remove('hidden');
                        formLogin.classList.add('hidden');
                    });
                }
            });
        </script>

    <?php else: ?>

        <!-- ========================================== -->
        <!-- LOGGED IN USER DASHBOARD -->
        <!-- ========================================== -->
        <div class="flex flex-col lg:flex-row gap-8">
            
            <!-- Navigation Tabs Sidebar -->
            <?php 
            $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
            $tabs = [
                'profile' => ['label' => 'Profile info', 'icon' => 'fa-circle-user'],
                'wishlist' => ['label' => 'My Wishlist', 'icon' => 'fa-heart'],
                'history' => ['label' => 'Download History', 'icon' => 'fa-clock-rotate-left'],
                'reviews' => ['label' => 'My Reviews', 'icon' => 'fa-comment-dots'],
                'settings' => ['label' => 'Settings', 'icon' => 'fa-gears'],
            ];
            ?>
            <aside class="w-full lg:w-64 flex-shrink-0">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm space-y-1">
                    <?php foreach ($tabs as $key => $tb): ?>
                        <a href="account.php?tab=<?php echo $key; ?>" 
                           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold transition <?php echo $active_tab === $key ? 'bg-primary-600 text-white' : 'text-slate-650 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'; ?>">
                            <i class="fa-solid <?php echo $tb['icon']; ?> text-base w-5"></i>
                            <span><?php echo $tb['label']; ?></span>
                        </a>
                    <?php endforeach; ?>
                    <hr class="border-slate-200 dark:border-slate-800 my-2">
                    <a href="logout.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold text-red-650 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/20 transition">
                        <i class="fa-solid fa-right-from-bracket text-base w-5"></i>
                        <span>Sign Out</span>
                    </a>
                </div>
            </aside>

            <!-- Main Tab Panel -->
            <main class="flex-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 md:p-8 shadow-sm">
                
                <?php if ($active_tab === 'profile'): ?>
                    <!-- Profile Tab -->
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Profile Details</h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Information about your store account</p>
                        </div>
                        <hr class="border-slate-200 dark:border-slate-800">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                            <div class="space-y-1">
                                <span class="text-xs font-semibold text-slate-400">Username</span>
                                <p class="text-slate-900 dark:text-white font-medium text-base"><?php echo esc($user_data['username']); ?></p>
                            </div>
                            <div class="space-y-1">
                                <span class="text-xs font-semibold text-slate-400">Email Address</span>
                                <p class="text-slate-900 dark:text-white font-medium text-base"><?php echo esc($user_data['email']); ?></p>
                            </div>
                            <div class="space-y-1">
                                <span class="text-xs font-semibold text-slate-400">Account Type</span>
                                <p class="text-slate-900 dark:text-white font-medium text-base capitalize"><?php echo esc($user_data['role']); ?></p>
                            </div>
                            <div class="space-y-1">
                                <span class="text-xs font-semibold text-slate-400">Registered Since</span>
                                <p class="text-slate-900 dark:text-white font-medium text-base"><?php echo date('F d, Y', strtotime($user_data['created_at'])); ?></p>
                            </div>
                        </div>

                        <?php if ($user_data['role'] === 'user'): ?>
                            <hr class="border-slate-200 dark:border-slate-800">
                            <div class="bg-primary-50 dark:bg-primary-950/20 border border-primary-100 dark:border-primary-900/50 p-6 rounded-2xl flex flex-col md:flex-row items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <h3 class="font-bold text-slate-900 dark:text-white text-base">Register as a Developer</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Submit and manage application listings on the store, track analytics, and more.</p>
                                </div>
                                <form action="" method="POST">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="become_developer">
                                    <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-2.5 rounded-full text-sm shadow-md transition whitespace-nowrap">
                                        Become Developer
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($active_tab === 'wishlist'): ?>
                    <!-- Wishlist Tab -->
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">My Wishlist</h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Applications you have saved for later</p>
                        </div>
                        <hr class="border-slate-200 dark:border-slate-800">
                        
                        <?php if (empty($wishlist_items)): ?>
                            <p class="text-center text-slate-400 py-10 text-sm">Your wishlist is empty. Browse apps and tap the heart icon to save them here!</p>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($wishlist_items as $item): ?>
                                    <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-100 dark:border-slate-800">
                                        <a href="app.php?id=<?php echo $item['app_id']; ?>" class="flex items-center gap-3 flex-1 min-w-0 group">
                                            <img src="<?php echo get_app_icon_url($item); ?>" alt="<?php echo esc($item['name']); ?>" class="w-12 h-12 rounded-xl object-cover bg-white dark:bg-slate-900">
                                            <div class="min-w-0">
                                                <h4 class="font-bold text-slate-900 dark:text-white text-sm truncate group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors"><?php echo esc($item['name']); ?></h4>
                                                <p class="text-[10px] text-slate-400 truncate mt-0.5"><?php echo esc($item['short_desc']); ?></p>
                                            </div>
                                        </a>
                                        <div class="flex items-center gap-3">
                                            <form action="" method="POST">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_wishlist">
                                                <!-- We can repurpose review delete or hit local form redirect back -->
                                                <button type="button" class="wishlist-remove-btn text-red-500 hover:text-red-700 text-xs px-2.5 py-1" data-appid="<?php echo $item['app_id']; ?>">
                                                    Remove
                                                </button>
                                            </form>
                                            <a href="app.php?id=<?php echo $item['app_id']; ?>" class="bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold px-4 py-1.5 rounded-full shadow-sm transition">
                                                View
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <script>
                                document.addEventListener('DOMContentLoaded', () => {
                                    const removeBtns = document.querySelectorAll('.wishlist-remove-btn');
                                    removeBtns.forEach(btn => {
                                        btn.addEventListener('click', () => {
                                            const appId = btn.getAttribute('data-appid');
                                            btn.disabled = true;
                                            
                                            fetch(`${baseUrl}api/wishlist.php`, {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/x-www-form-urlencoded',
                                                    'X-CSRF-TOKEN': '<?php echo csrf_token(); ?>'
                                                },
                                                body: `app_id=${encodeURIComponent(appId)}`
                                            })
                                            .then(response => response.json())
                                            .then(data => {
                                                if (data.status === 'removed') {
                                                    // Reload tab page
                                                    window.location.reload();
                                                }
                                            })
                                            .catch(err => {
                                                btn.disabled = false;
                                                console.error(err);
                                            });
                                        });
                                    });
                                });
                            </script>
                        <?php endif; ?>
                    </div>

                <?php elseif ($active_tab === 'history'): ?>
                    <!-- Download History Tab -->
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Download History</h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Applications you have downloaded</p>
                        </div>
                        <hr class="border-slate-200 dark:border-slate-800">
                        
                        <?php if (empty($downloads_history)): ?>
                            <p class="text-center text-slate-400 py-10 text-sm">You haven't downloaded any applications yet.</p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 text-xs">
                                            <th class="py-3 px-4 font-semibold">Application</th>
                                            <th class="py-3 px-4 font-semibold">Version</th>
                                            <th class="py-3 px-4 font-semibold">File Size</th>
                                            <th class="py-3 px-4 font-semibold">Downloaded At</th>
                                            <th class="py-3 px-4 font-semibold text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                                        <?php foreach ($downloads_history as $dl): ?>
                                            <tr>
                                                <td class="py-4 px-4">
                                                    <a href="app.php?id=<?php echo $dl['app_id']; ?>" class="flex items-center gap-3 group">
                                                        <img src="<?php echo get_app_icon_url($dl); ?>" alt="<?php echo esc($dl['name']); ?>" class="w-10 h-10 rounded-lg object-cover">
                                                        <span class="font-bold text-slate-900 dark:text-white truncate group-hover:text-primary-600 transition-colors"><?php echo esc($dl['name']); ?></span>
                                                    </a>
                                                </td>
                                                <td class="py-4 px-4 text-slate-500">v<?php echo esc($dl['version']); ?></td>
                                                <td class="py-4 px-4 text-slate-500"><?php echo format_bytes($dl['file_size']); ?></td>
                                                <td class="py-4 px-4 text-slate-400 text-xs"><?php echo date('M d, Y H:i', strtotime($dl['downloaded_at'])); ?></td>
                                                <td class="py-4 px-4 text-right">
                                                    <a href="download.php?id=<?php echo $dl['app_id']; ?>" class="inline-flex items-center gap-1.5 bg-primary-100 text-primary-750 hover:bg-primary-200 text-xs font-semibold px-4 py-2 rounded-full dark:bg-primary-950/30 dark:text-primary-400 dark:hover:bg-primary-900/40 transition">
                                                        <i class="fa-solid fa-cloud-arrow-down"></i> Re-download
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($active_tab === 'reviews'): ?>
                    <!-- My Reviews Tab -->
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">My Reviews</h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Reviews you have written on this store</p>
                        </div>
                        <hr class="border-slate-200 dark:border-slate-800">
                        
                        <?php if (empty($my_reviews)): ?>
                            <p class="text-center text-slate-400 py-10 text-sm">You haven't written any reviews yet.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($my_reviews as $rev): ?>
                                    <div class="p-5 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-100 dark:border-slate-800 space-y-3">
                                        <div class="flex items-center justify-between">
                                            <a href="app.php?id=<?php echo $rev['app_id']; ?>" class="flex items-center gap-2 group">
                                                <img src="<?php echo get_app_icon_url($rev); ?>" alt="" class="w-8 h-8 rounded-lg object-cover">
                                                <span class="font-bold text-slate-900 dark:text-white text-sm group-hover:text-primary-600 transition-colors"><?php echo esc($rev['app_name']); ?></span>
                                            </a>
                                            <div class="flex items-center gap-4">
                                                <?php echo render_stars($rev['rating'], 'w-3.5 h-3.5'); ?>
                                                
                                                <!-- Delete review button -->
                                                <form action="" method="POST" onsubmit="return confirm('Are you sure you want to delete this review?');">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete_review">
                                                    <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <p class="text-slate-650 dark:text-slate-300 text-xs leading-relaxed"><?php echo nl2br(esc($rev['review_text'])); ?></p>
                                        <p class="text-[10px] text-slate-400"><?php echo date('M d, Y H:i', strtotime($rev['created_at'])); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($active_tab === 'settings'): ?>
                    <!-- Settings Tab -->
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Account Settings</h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage passwords and configurations</p>
                        </div>
                        <hr class="border-slate-200 dark:border-slate-800">
                        
                        <!-- Change Password Panel -->
                        <form action="" method="POST" class="max-w-md space-y-4">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="change_password">
                            
                            <h3 class="font-bold text-slate-900 dark:text-white text-base">Change Password</h3>
                            
                            <div class="space-y-1">
                                <label for="current_password" class="text-xs font-semibold text-slate-500 dark:text-slate-400">Current Password</label>
                                <input type="password" name="current_password" id="current_password" required
                                       class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                                       placeholder="••••••••">
                            </div>

                            <div class="space-y-1">
                                <label for="new_password" class="text-xs font-semibold text-slate-500 dark:text-slate-400">New Password (min 8 characters)</label>
                                <input type="password" name="new_password" id="new_password" required minlength="8"
                                       class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                                       placeholder="••••••••">
                            </div>

                            <div class="space-y-1">
                                <label for="confirm_new_password" class="text-xs font-semibold text-slate-500 dark:text-slate-400">Confirm New Password</label>
                                <input type="password" name="confirm_new_password" id="confirm_new_password" required
                                       class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                                       placeholder="••••••••">
                            </div>

                            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-2.5 rounded-full text-sm shadow-md transition">
                                Update Password
                            </button>
                        </form>
                    </div>

                <?php endif; ?>

            </main>
        </div>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
