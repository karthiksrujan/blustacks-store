<?php
/**
 * Admin Panel Dashboard
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Gate access
require_admin();

$admin_id = get_logged_in_user_id();
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';

$error_msg = '';
$success_msg = '';

try {
    // 1. Fetch Pending App Submissions
    $pending_stmt = $pdo->query("
        SELECT a.*, c.name AS category_name, u.username AS developer_name 
        FROM apps a
        JOIN categories c ON a.category_id = c.id
        JOIN users u ON a.developer_id = u.id
        WHERE a.status = 'pending'
        ORDER BY a.created_at ASC
    ");
    $pending_apps = $pending_stmt->fetchAll();

    // 2. Fetch Approved Apps
    $approved_stmt = $pdo->query("
        SELECT a.*, c.name AS category_name, u.username AS developer_name 
        FROM apps a
        JOIN categories c ON a.category_id = c.id
        JOIN users u ON a.developer_id = u.id
        WHERE a.status = 'approved'
        ORDER BY a.name ASC
    ");
    $approved_apps = $approved_stmt->fetchAll();

    // 3. Fetch Categories
    $categories_stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $categories_stmt->fetchAll();

    // 4. Fetch Platform Stats
    $stats = [];
    $stats['users_count'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['apps_count'] = (int)$pdo->query("SELECT COUNT(*) FROM apps WHERE status = 'approved'")->fetchColumn();
    $stats['downloads_count'] = (int)$pdo->query("SELECT COUNT(*) FROM downloads")->fetchColumn();

    // Top downloaded apps
    $top_apps_stmt = $pdo->query("
        SELECT a.id, a.name, a.download_count, c.name AS category_name
        FROM apps a
        JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'approved'
        ORDER BY a.download_count DESC
        LIMIT 5
    ");
    $top_apps = $top_apps_stmt->fetchAll();

    // 5. Fetch Admin Audit Logs
    $logs_stmt = $pdo->query("
        SELECT l.*, u.username AS admin_name, a.name AS app_name 
        FROM admin_logs l
        JOIN users u ON l.admin_id = u.id
        LEFT JOIN apps a ON l.app_id = a.id
        ORDER BY l.timestamp DESC
        LIMIT 50
    ");
    $admin_logs = $logs_stmt->fetchAll();

} catch (PDOException $e) {
    $error_msg = "Database query failed.";
    $pending_apps = [];
    $approved_apps = [];
    $categories = [];
    $top_apps = [];
    $admin_logs = [];
}

$page_title = "Admin Panel";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
    <!-- Header -->
    <div class="pb-6 border-b border-slate-200 dark:border-slate-800 mb-8">
        <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white">Admin Management Panel</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Review app requests, manage catalog options, and view audit trail logs</p>
    </div>

    <!-- Layout -->
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Sidebar Navigation -->
        <aside class="w-full lg:w-60 flex-shrink-0">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm space-y-1">
                <a href="dashboard.php?tab=pending" 
                   class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold transition <?php echo $active_tab === 'pending' ? 'bg-primary-600 text-white' : 'text-slate-650 hover:bg-slate-100 dark:text-slate-350 dark:hover:bg-slate-800'; ?>">
                    <i class="fa-solid fa-clock-rotate-left w-5 text-base"></i>
                    <span>Pending Apps (<?php echo count($pending_apps); ?>)</span>
                </a>
                <a href="dashboard.php?tab=approved" 
                   class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold transition <?php echo $active_tab === 'approved' ? 'bg-primary-600 text-white' : 'text-slate-650 hover:bg-slate-100 dark:text-slate-350 dark:hover:bg-slate-800'; ?>">
                    <i class="fa-solid fa-circle-check w-5 text-base"></i>
                    <span>Approved Catalog</span>
                </a>
                <a href="dashboard.php?tab=categories" 
                   class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold transition <?php echo $active_tab === 'categories' ? 'bg-primary-600 text-white' : 'text-slate-650 hover:bg-slate-100 dark:text-slate-350 dark:hover:bg-slate-800'; ?>">
                    <i class="fa-solid fa-folder-open w-5 text-base"></i>
                    <span>Categories CRUD</span>
                </a>
                <a href="dashboard.php?tab=analytics" 
                   class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold transition <?php echo $active_tab === 'analytics' ? 'bg-primary-600 text-white' : 'text-slate-650 hover:bg-slate-100 dark:text-slate-350 dark:hover:bg-slate-800'; ?>">
                    <i class="fa-solid fa-chart-pie w-5 text-base"></i>
                    <span>Platform Metrics</span>
                </a>
                <a href="dashboard.php?tab=logs" 
                   class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold transition <?php echo $active_tab === 'logs' ? 'bg-primary-600 text-white' : 'text-slate-650 hover:bg-slate-100 dark:text-slate-350 dark:hover:bg-slate-800'; ?>">
                    <i class="fa-solid fa-file-invoice w-5 text-base"></i>
                    <span>Audit logs</span>
                </a>
            </div>
        </aside>

        <!-- Main Panel -->
        <main class="flex-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 md:p-8 shadow-sm">
            
            <?php if ($active_tab === 'pending'): ?>
                <!-- Pending Apps Tab -->
                <div class="space-y-6">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white font-sans">Awaiting Moderation</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Review, test, and approve app submissions</p>
                    </div>

                    <?php if (empty($pending_apps)): ?>
                        <p class="text-center py-10 text-sm text-slate-400">All submissions are processed. No pending apps!</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 text-xs">
                                        <th class="py-3 px-4 font-semibold">App Details</th>
                                        <th class="py-3 px-4 font-semibold">Developer</th>
                                        <th class="py-3 px-4 font-semibold">Category</th>
                                        <th class="py-3 px-4 font-semibold">Submitted</th>
                                        <th class="py-3 px-4 font-semibold text-right">Moderation Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                                    <?php foreach ($pending_apps as $app): ?>
                                        <tr>
                                            <td class="py-4 px-4">
                                                <div class="flex items-center gap-3">
                                                    <img src="<?php echo get_app_icon_url($app); ?>" alt="" class="w-10 h-10 rounded-lg object-cover">
                                                    <div>
                                                        <a href="<?php echo BASE_URL; ?>app.php?id=<?php echo $app['id']; ?>" class="font-bold text-primary-600 hover:underline"><?php echo esc($app['name']); ?></a>
                                                        <span class="text-slate-400 text-[10px] block">v<?php echo esc($app['version']); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4 text-slate-550"><?php echo esc($app['developer_name']); ?></td>
                                            <td class="py-4 px-4 text-slate-500"><?php echo esc($app['category_name']); ?></td>
                                            <td class="py-4 px-4 text-slate-400 text-xs"><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                                            <td class="py-4 px-4 text-right">
                                                <div class="flex items-center justify-end gap-2 text-xs font-semibold">
                                                    <!-- Approve Form -->
                                                    <form action="review.php" method="POST" class="inline">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-1.5 rounded-full transition">Approve</button>
                                                    </form>
                                                    <!-- Reject Button -->
                                                    <button onclick="openRejectModal(<?php echo $app['id']; ?>)" class="bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 px-4 py-1.5 rounded-full transition dark:bg-red-950/20 dark:text-red-400 dark:border-red-900/50">Reject</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($active_tab === 'approved'): ?>
                <!-- Approved Apps Tab -->
                <div class="space-y-6">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Active Catalog</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Review active applications on the platform</p>
                    </div>

                    <?php if (empty($approved_apps)): ?>
                        <p class="text-center py-10 text-sm text-slate-400">No approved applications are currently cataloged.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 text-xs">
                                        <th class="py-3 px-4 font-semibold">Application</th>
                                        <th class="py-3 px-4 font-semibold">Developer</th>
                                        <th class="py-3 px-4 font-semibold">Category</th>
                                        <th class="py-3 px-4 font-semibold">Downloads</th>
                                        <th class="py-3 px-4 font-semibold">Visibility</th>
                                        <th class="py-3 px-4 font-semibold text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                                    <?php foreach ($approved_apps as $app): ?>
                                        <tr>
                                            <td class="py-4 px-4">
                                                <div class="flex items-center gap-3">
                                                    <img src="<?php echo get_app_icon_url($app); ?>" alt="" class="w-10 h-10 rounded-lg object-cover">
                                                    <div>
                                                        <a href="<?php echo BASE_URL; ?>app.php?id=<?php echo $app['id']; ?>" class="font-bold text-slate-900 dark:text-white hover:underline"><?php echo esc($app['name']); ?></a>
                                                        <span class="text-slate-400 text-[10px] block">v<?php echo esc($app['version']); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4 text-slate-550"><?php echo esc($app['developer_name']); ?></td>
                                            <td class="py-4 px-4 text-slate-500"><?php echo esc($app['category_name']); ?></td>
                                            <td class="py-4 px-4 font-semibold"><?php echo number_format($app['download_count']); ?></td>
                                            <td class="py-4 px-4 text-xs">
                                                <?php if ($app['is_published'] == 1): ?>
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-semibold bg-blue-50 text-blue-800 dark:bg-blue-950/20 dark:text-blue-400">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Published
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-semibold bg-slate-100 text-slate-650 dark:bg-slate-800 dark:text-slate-400">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Draft / Hidden
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-4 px-4 text-right">
                                                <a href="<?php echo BASE_URL; ?>developer/delete.php?id=<?php echo $app['id']; ?>&from_admin=1" 
                                                   onclick="return confirm('Are you sure you want to delete/ban this application from the platform?');" 
                                                   class="text-red-500 hover:text-red-700 text-xs font-semibold px-2 py-1"><i class="fa-solid fa-ban mr-1"></i>Delete / Ban</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($active_tab === 'categories'): ?>
                <!-- Manage Categories Tab -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Create Category -->
                    <div class="md:col-span-1 space-y-4 bg-slate-50 dark:bg-slate-950 p-6 rounded-2xl border border-slate-100 dark:border-slate-800">
                        <h3 class="font-bold text-slate-900 dark:text-white text-base">Add New Category</h3>
                        <form action="categories.php" method="POST" class="space-y-4">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="create">
                            
                            <div class="space-y-1">
                                <label for="cat_name" class="text-xs font-semibold text-slate-500">Name</label>
                                <input type="text" name="name" id="cat_name" required 
                                       class="w-full rounded-lg border border-slate-300 bg-white p-2 text-xs focus:outline-none focus:border-primary-500 dark:bg-slate-900 dark:border-slate-800 dark:text-white" 
                                       placeholder="e.g. Productivity">
                            </div>

                            <div class="space-y-1">
                                <label for="cat_slug" class="text-xs font-semibold text-slate-500">Slug</label>
                                <input type="text" name="slug" id="cat_slug" required 
                                       class="w-full rounded-lg border border-slate-300 bg-white p-2 text-xs focus:outline-none focus:border-primary-500 dark:bg-slate-900 dark:border-slate-800 dark:text-white" 
                                       placeholder="e.g. productivity">
                            </div>

                            <div class="space-y-1">
                                <label for="cat_icon" class="text-xs font-semibold text-slate-500">FontAwesome Icon Class</label>
                                <input type="text" name="icon" id="cat_icon" required 
                                       class="w-full rounded-lg border border-slate-300 bg-white p-2 text-xs focus:outline-none focus:border-primary-500 dark:bg-slate-900 dark:border-slate-800 dark:text-white" 
                                       placeholder="e.g. briefcase">
                                <p class="text-[10px] text-slate-400">Class suffix (e.g. gamepad for fa-gamepad).</p>
                            </div>

                            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-2 rounded-lg text-xs shadow-sm transition">
                                Create Category
                            </button>
                        </form>
                    </div>

                    <!-- Category Table -->
                    <div class="md:col-span-2 space-y-4">
                        <h3 class="font-bold text-slate-900 dark:text-white text-base">Current Categories</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-semibold uppercase">
                                        <th class="py-2 px-3">Icon</th>
                                        <th class="py-2 px-3">Name</th>
                                        <th class="py-2 px-3">Slug</th>
                                        <th class="py-2 px-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td class="py-3 px-3">
                                                <div class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-650 dark:text-slate-300 flex items-center justify-center">
                                                    <i class="fa-solid fa-<?php echo esc($cat['icon']); ?>"></i>
                                                </div>
                                            </td>
                                            <td class="py-3 px-3 font-semibold text-slate-900 dark:text-white"><?php echo esc($cat['name']); ?></td>
                                            <td class="py-3 px-3 text-slate-500"><?php echo esc($cat['slug']); ?></td>
                                            <td class="py-3 px-3 text-right">
                                                <form action="categories.php" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this category? Note that deleting will fail if there are apps categorized under it.');">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                                    <button type="submit" class="text-red-500 hover:text-red-700 font-semibold">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($active_tab === 'analytics'): ?>
                <!-- Platform Metrics Tab -->
                <div class="space-y-8">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Platform Overall Metrics</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Global statistics across the application platform</p>
                    </div>

                    <!-- Grid metrics -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-slate-50 dark:bg-slate-950 p-6 rounded-2xl border border-slate-100 dark:border-slate-800">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Registered Users</span>
                            <h3 class="text-4xl font-black text-slate-900 dark:text-white mt-1"><?php echo number_format($stats['users_count']); ?></h3>
                            <p class="text-[10px] text-slate-500 mt-1"><i class="fa-solid fa-users mr-1"></i>Active credentials</p>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-950 p-6 rounded-2xl border border-slate-100 dark:border-slate-800">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Live Applications</span>
                            <h3 class="text-4xl font-black text-slate-900 dark:text-white mt-1"><?php echo number_format($stats['apps_count']); ?></h3>
                            <p class="text-[10px] text-slate-500 mt-1"><i class="fa-solid fa-cubes mr-1"></i>Approved listings</p>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-950 p-6 rounded-2xl border border-slate-100 dark:border-slate-800">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Downloads Serviced</span>
                            <h3 class="text-4xl font-black text-slate-900 dark:text-white mt-1"><?php echo number_format($stats['downloads_count']); ?></h3>
                            <p class="text-[10px] text-slate-500 mt-1"><i class="fa-solid fa-download mr-1"></i>Downloads tracked</p>
                        </div>
                    </div>

                    <!-- Top Downloaded Apps List -->
                    <div class="bg-slate-50 dark:bg-slate-950 p-6 border border-slate-100 dark:border-slate-800 rounded-2xl space-y-4">
                        <h4 class="font-bold text-sm text-slate-900 dark:text-white">Top 5 Applications by Installs</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-semibold">
                                        <th class="py-2 px-3">Application</th>
                                        <th class="py-2 px-3">Category</th>
                                        <th class="py-2 px-3 text-right">Total Installs</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-850">
                                    <?php foreach ($top_apps as $tapp): ?>
                                        <tr>
                                            <td class="py-3 px-3">
                                                <a href="<?php echo BASE_URL; ?>app.php?id=<?php echo $tapp['id']; ?>" class="font-bold text-primary-600 hover:underline"><?php echo esc($tapp['name']); ?></a>
                                            </td>
                                            <td class="py-3 px-3 text-slate-500"><?php echo esc($tapp['category_name']); ?></td>
                                            <td class="py-3 px-3 text-right font-semibold text-slate-900 dark:text-white"><?php echo number_format($tapp['download_count']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($active_tab === 'logs'): ?>
                <!-- Admin Logs Tab -->
                <div class="space-y-6">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">System Audit Trails</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Audit log records of administrator operations</p>
                    </div>

                    <?php if (empty($admin_logs)): ?>
                        <p class="text-center py-10 text-sm text-slate-400">No audit logs recorded yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-semibold">
                                        <th class="py-2.5 px-3">Administrator</th>
                                        <th class="py-2.5 px-3">Operation Details</th>
                                        <th class="py-2.5 px-3">App Reference</th>
                                        <th class="py-2.5 px-3">Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                                    <?php foreach ($admin_logs as $log): ?>
                                        <tr>
                                            <td class="py-3 px-3 font-semibold text-slate-900 dark:text-white"><?php echo esc($log['admin_name']); ?></td>
                                            <td class="py-3 px-3 text-slate-650 dark:text-slate-350"><?php echo esc($log['action']); ?></td>
                                            <td class="py-3 px-3 text-slate-500"><?php echo $log['app_id'] ? esc($log['app_name']) : 'N/A'; ?></td>
                                            <td class="py-3 px-3 text-slate-400"><?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </main>
    </div>
</div>

<!-- Rejection Modal -->
<div id="reject-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Reject Application</h3>
        <form action="review.php" method="POST" class="space-y-4">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="app_id" id="reject-app-id" value="">
            
            <div class="space-y-1">
                <label for="reject_reason" class="text-xs font-semibold text-slate-500 dark:text-slate-400">Reason for Rejection</label>
                <textarea name="reason" id="reject_reason" rows="4" required class="w-full rounded-xl border border-slate-300 bg-slate-50 p-3 text-sm focus:outline-none focus:border-primary-500 dark:bg-slate-800 dark:border-slate-700 dark:text-white" placeholder="Provide details on why this app was rejected (e.g. invalid file, policy violation)..."></textarea>
            </div>
            
            <div class="flex justify-end gap-2 text-xs font-semibold pt-2">
                <button type="button" onclick="closeRejectModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-750 px-4 py-2 rounded-full dark:bg-slate-800 dark:text-slate-300">Cancel</button>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-full shadow">Reject App</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRejectModal(appId) {
        const modal = document.getElementById('reject-modal');
        const appIdInput = document.getElementById('reject-app-id');
        if (modal && appIdInput) {
            appIdInput.value = appId;
            modal.classList.remove('hidden');
        }
    }

    function closeRejectModal() {
        const modal = document.getElementById('reject-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
