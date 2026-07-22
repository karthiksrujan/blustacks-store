<?php
/**
 * Developer Portal Dashboard
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Gate access
require_developer();

$user_id = get_logged_in_user_id();
$user_role = get_logged_in_user_role();
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'my_apps';

$error_msg = '';
$success_msg = '';

// Handle POST action for publishing toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_publish') {
    require_once __DIR__ . '/../includes/csrf.php';
    csrf_enforce();

    $app_id = isset($_POST['app_id']) ? (int)$_POST['app_id'] : 0;
    if ($app_id > 0) {
        try {
            // Verify ownership
            $owner_stmt = $pdo->prepare("SELECT developer_id, is_published FROM apps WHERE id = ?");
            $owner_stmt->execute([$app_id]);
            $app_row = $owner_stmt->fetch();

            if ($app_row && ($app_row['developer_id'] == $user_id || $user_role === 'admin')) {
                // Toggle is_published
                $new_publish_state = $app_row['is_published'] ? 0 : 1;
                $update_stmt = $pdo->prepare("UPDATE apps SET is_published = ? WHERE id = ?");
                $update_stmt->execute([$new_publish_state, $app_id]);
                
                $success_msg = $new_publish_state ? "Application successfully published to the store!" : "Application successfully unpublished/drafted.";
            } else {
                $error_msg = "Unauthorized access.";
            }
        } catch (PDOException $e) {
            $error_msg = "Database error updating publish status.";
        }
    }
}

try {
    // 1. Fetch Developer's Apps
    $apps_stmt = $pdo->prepare("
        SELECT a.*, c.name AS category_name 
        FROM apps a
        JOIN categories c ON a.category_id = c.id
        WHERE a.developer_id = ?
        ORDER BY a.updated_at DESC
    ");
    $apps_stmt->execute([$user_id]);
    $my_apps = $apps_stmt->fetchAll();

    // 2. Fetch Developer Analytics Summary
    // Total Downloads
    $dl_sum_stmt = $pdo->prepare("SELECT SUM(download_count) FROM apps WHERE developer_id = ?");
    $dl_sum_stmt->execute([$user_id]);
    $total_downloads = (int)$dl_sum_stmt->fetchColumn();

    // Total Apps Count by Status
    $status_stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM apps 
        WHERE developer_id = ? 
        GROUP BY status
    ");
    $status_stmt->execute([$user_id]);
    $status_counts = ['approved' => 0, 'pending' => 0, 'rejected' => 0];
    foreach ($status_stmt->fetchAll() as $row) {
        $status_counts[$row['status']] = (int)$row['count'];
    }

    // Top Categories
    $top_cat_stmt = $pdo->prepare("
        SELECT c.name, SUM(a.download_count) AS downloads
        FROM apps a
        JOIN categories c ON a.category_id = c.id
        WHERE a.developer_id = ?
        GROUP BY c.id
        ORDER BY downloads DESC
        LIMIT 3
    ");
    $top_cat_stmt->execute([$user_id]);
    $top_categories = $top_cat_stmt->fetchAll();

    // Review Sentiment Summary
    $sentiment_stmt = $pdo->prepare("
        SELECT 
            AVG(r.rating) AS avg_rating,
            COUNT(*) AS total_reviews,
            SUM(CASE WHEN r.rating >= 4 THEN 1 ELSE 0 END) AS positive_reviews
        FROM reviews r
        JOIN apps a ON r.app_id = a.id
        WHERE a.developer_id = ?
    ");
    $sentiment_stmt->execute([$user_id]);
    $sentiment = $sentiment_stmt->fetch();

    $avg_rating = $sentiment['avg_rating'] ? (float)$sentiment['avg_rating'] : 0.0;
    $total_reviews = (int)$sentiment['total_reviews'];
    $positive_percent = $total_reviews > 0 ? round(($sentiment['positive_reviews'] / $total_reviews) * 100) : 0;

} catch (PDOException $e) {
    $error_msg = "Database query failed.";
    $my_apps = [];
    $top_categories = [];
    $total_downloads = 0;
}

$page_title = "Developer Portal";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pb-6 border-b border-slate-200 dark:border-slate-800 mb-8">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white">Developer Console</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Publish applications and monitor performance metrics</p>
        </div>
        <a href="<?php echo BASE_URL; ?>developer/submit.php" 
           class="bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-2.5 rounded-full text-sm shadow-md transition whitespace-nowrap">
            <i class="fa-solid fa-cloud-arrow-up mr-2"></i>Submit New App
        </a>
    </div>

    <!-- Layout -->
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Sidebar Navigation -->
        <aside class="w-full lg:w-60 flex-shrink-0">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm space-y-1">
                <a href="dashboard.php?tab=my_apps" 
                   class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold transition <?php echo $active_tab === 'my_apps' ? 'bg-primary-600 text-white' : 'text-slate-650 hover:bg-slate-100 dark:text-slate-350 dark:hover:bg-slate-800'; ?>">
                    <i class="fa-solid fa-list-check w-5 text-base"></i>
                    <span>My Listings</span>
                </a>
                <a href="dashboard.php?tab=analytics" 
                   class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold transition <?php echo $active_tab === 'analytics' ? 'bg-primary-600 text-white' : 'text-slate-650 hover:bg-slate-100 dark:text-slate-350 dark:hover:bg-slate-800'; ?>">
                    <i class="fa-solid fa-chart-line w-5 text-base"></i>
                    <span>Analytics</span>
                </a>
            </div>
        </aside>

        <!-- Main Panel -->
        <main class="flex-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 md:p-8 shadow-sm">
            
            <?php if ($active_tab === 'my_apps'): ?>
                <!-- Listings Tab -->
                <div class="space-y-6">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">My Application Listings</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Review status and manage application information</p>
                    </div>
                    
                    <?php if (empty($my_apps)): ?>
                        <div class="text-center py-16 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl p-8 bg-slate-50 dark:bg-slate-950/20">
                            <i class="fa-solid fa-boxes-stacked text-slate-300 text-4xl mb-4"></i>
                            <h3 class="font-bold text-slate-900 dark:text-white text-base">No apps submitted yet</h3>
                            <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Submit your first application to showcase it on our platform.</p>
                            <a href="submit.php" class="inline-block bg-primary-600 text-white font-semibold text-xs px-5 py-2.5 rounded-full mt-6 transition hover:bg-primary-700">Submit New App</a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 text-xs">
                                        <th class="py-3 px-4 font-semibold">Application</th>
                                        <th class="py-3 px-4 font-semibold">Category</th>
                                        <th class="py-3 px-4 font-semibold">Downloads</th>
                                        <th class="py-3 px-4 font-semibold">Status</th>
                                        <th class="py-3 px-4 font-semibold">Last Updated</th>
                                        <th class="py-3 px-4 font-semibold text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-850">
                                    <?php foreach ($my_apps as $app): ?>
                                        <tr>
                                            <td class="py-4 px-4">
                                                <div class="flex items-center gap-3">
                                                    <img src="<?php echo get_app_icon_url($app); ?>" alt="" class="w-10 h-10 rounded-lg object-cover">
                                                    <div>
                                                        <span class="font-bold text-slate-900 dark:text-white block"><?php echo esc($app['name']); ?></span>
                                                        <span class="text-slate-400 text-[10px]">v<?php echo esc($app['version']); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4 text-slate-500"><?php echo esc($app['category_name']); ?></td>
                                            <td class="py-4 px-4 font-semibold"><?php echo number_format($app['download_count']); ?></td>
                                            <td class="py-4 px-4 text-xs">
                                                <div>
                                                    <?php if ($app['status'] === 'approved'): ?>
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-semibold bg-green-50 text-green-800 dark:bg-green-950/20 dark:text-green-400">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Approved
                                                        </span>
                                                    <?php elseif ($app['status'] === 'pending'): ?>
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-semibold bg-yellow-50 text-yellow-800 dark:bg-yellow-950/20 dark:text-yellow-400">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span> Pending
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-semibold bg-red-50 text-red-800 dark:bg-red-950/20 dark:text-red-400">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Rejected
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-1">
                                                    <?php if ($app['is_published'] == 1): ?>
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/20 dark:text-blue-400">
                                                            <i class="fa-solid fa-globe mr-1 text-[8px]"></i> Published
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-155 text-slate-650 dark:bg-slate-800 dark:text-slate-400">
                                                            <i class="fa-solid fa-eye-slash mr-1 text-[8px]"></i> Draft / Hidden
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4 text-slate-400 text-xs"><?php echo date('M d, Y', strtotime($app['updated_at'])); ?></td>
                                            <td class="py-4 px-4 text-right">
                                                <div class="flex items-center justify-end gap-2.5">
                                                    <?php if ($app['status'] === 'approved'): ?>
                                                        <form action="" method="POST" class="inline">
                                                            <?php csrf_field(); ?>
                                                            <input type="hidden" name="action" value="toggle_publish">
                                                            <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                                            <button type="submit" class="text-xs font-semibold px-3 py-1.5 rounded-full transition <?php echo $app['is_published'] ? 'bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300' : 'bg-primary-600 hover:bg-primary-700 text-white shadow-sm'; ?>">
                                                                <?php echo $app['is_published'] ? '<i class="fa-solid fa-eye-slash mr-1"></i>Unpublish' : '<i class="fa-solid fa-paper-plane mr-1"></i>Publish'; ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <a href="submit.php?id=<?php echo $app['id']; ?>" class="text-primary-650 hover:text-primary-700 text-xs font-semibold px-2 py-1"><i class="fa-solid fa-pen-to-square mr-1"></i>Edit</a>
                                                    <a href="delete.php?id=<?php echo $app['id']; ?>" 
                                                       onclick="return confirm('Are you sure you want to delete this listing? This action cannot be undone.');" 
                                                       class="text-red-500 hover:text-red-700 text-xs font-semibold px-2 py-1">
                                                        <i class="fa-solid fa-trash-can mr-1"></i>Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($active_tab === 'analytics'): ?>
                <!-- Analytics Tab -->
                <div class="space-y-8">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Performance Analytics</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Track installs and feedback summary for your applications</p>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-slate-50 dark:bg-slate-950 p-5 rounded-2xl border border-slate-100 dark:border-slate-800">
                            <span class="text-xs font-semibold text-slate-400">Total Installs</span>
                            <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-1"><?php echo number_format($total_downloads); ?></h3>
                            <p class="text-[10px] text-green-500 mt-1"><i class="fa-solid fa-trend-up mr-1"></i>Downloads tracked</p>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-950 p-5 rounded-2xl border border-slate-100 dark:border-slate-800">
                            <span class="text-xs font-semibold text-slate-400">Average Ratings</span>
                            <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-1"><?php echo number_format($avg_rating, 1); ?> <span class="text-sm text-slate-400 font-normal">/ 5.0</span></h3>
                            <div class="flex items-center gap-1.5 mt-1">
                                <?php echo render_stars($avg_rating, 'w-3.5 h-3.5'); ?>
                            </div>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-950 p-5 rounded-2xl border border-slate-100 dark:border-slate-800">
                            <span class="text-xs font-semibold text-slate-400">Sentiment Ratio</span>
                            <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-1"><?php echo $positive_percent; ?>%</h3>
                            <p class="text-[10px] text-slate-400 mt-1">Positive reviews (4+ stars)</p>
                        </div>
                    </div>

                    <!-- Downloads Trend Chart (Styled CSS Graph) -->
                    <div class="bg-slate-50 dark:bg-slate-950 p-6 border border-slate-100 dark:border-slate-800 rounded-2xl space-y-4">
                        <h4 class="font-bold text-sm text-slate-900 dark:text-white">Download Frequency (Last 7 Days)</h4>
                        
                        <div class="flex items-end justify-between h-40 gap-2 pt-6 border-b border-slate-200 dark:border-slate-800 select-none">
                            <?php 
                            // Mocking a beautiful daily install trend
                            $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                            $trends = [65, 82, 70, 95, 110, 85, 90];
                            $max = max($trends);
                            
                            foreach ($days as $i => $day): 
                                $height = ($trends[$i] / $max) * 100;
                            ?>
                                <div class="flex-1 flex flex-col items-center gap-2 group">
                                    <div class="w-full bg-primary-100 group-hover:bg-primary-200 dark:bg-primary-950/20 dark:group-hover:bg-primary-900/40 rounded-t-lg relative flex justify-center items-end" style="height: <?php echo $height; ?>%">
                                        <!-- Tooltip -->
                                        <div class="absolute -top-7 scale-0 group-hover:scale-100 bg-slate-900 text-white text-[10px] px-2 py-0.5 rounded shadow transition-all duration-100 font-bold dark:bg-white dark:text-slate-950">
                                            <?php echo $trends[$i]; ?> dl
                                        </div>
                                        <div class="w-full bg-primary-600 h-1.5 rounded-t-lg transition duration-200"></div>
                                    </div>
                                    <span class="text-[10px] text-slate-400 pb-2"><?php echo $day; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Split Columns (Categories vs Submissions) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Top Categories List -->
                        <div class="bg-slate-50 dark:bg-slate-950 p-6 rounded-2xl border border-slate-100 dark:border-slate-800 space-y-4">
                            <h4 class="font-bold text-sm text-slate-900 dark:text-white">Downloads by Category</h4>
                            <?php if (empty($top_categories)): ?>
                                <p class="text-xs text-slate-400">No categories recorded.</p>
                            <?php else: ?>
                                <ul class="space-y-3 text-xs">
                                    <?php foreach ($top_categories as $tcat): ?>
                                        <li class="space-y-1">
                                            <div class="flex justify-between font-semibold">
                                                <span class="text-slate-700 dark:text-slate-350"><?php echo esc($tcat['name']); ?></span>
                                                <span class="text-slate-400"><?php echo number_format($tcat['downloads']); ?></span>
                                            </div>
                                            <div class="w-full h-1.5 bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden">
                                                <?php 
                                                $bar_w = $total_downloads > 0 ? ($tcat['downloads'] / $total_downloads) * 100 : 0;
                                                ?>
                                                <div class="h-full bg-primary-600" style="width: <?php echo $bar_w; ?>%"></div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <!-- Listing Summary Status -->
                        <div class="bg-slate-50 dark:bg-slate-950 p-6 rounded-2xl border border-slate-100 dark:border-slate-800 space-y-4">
                            <h4 class="font-bold text-sm text-slate-900 dark:text-white">Listing Status Summary</h4>
                            <ul class="space-y-3 text-xs">
                                <li class="flex items-center justify-between p-2 rounded bg-white dark:bg-slate-900 border dark:border-slate-850">
                                    <span class="text-slate-650 dark:text-slate-400 font-medium"><i class="fa-solid fa-circle-check text-green-500 mr-2"></i>Approved Apps</span>
                                    <span class="font-bold text-slate-900 dark:text-white"><?php echo $status_counts['approved']; ?></span>
                                </li>
                                <li class="flex items-center justify-between p-2 rounded bg-white dark:bg-slate-900 border dark:border-slate-850">
                                    <span class="text-slate-650 dark:text-slate-400 font-medium"><i class="fa-solid fa-clock text-yellow-500 mr-2"></i>Pending Moderation</span>
                                    <span class="font-bold text-slate-900 dark:text-white"><?php echo $status_counts['pending']; ?></span>
                                </li>
                                <li class="flex items-center justify-between p-2 rounded bg-white dark:bg-slate-900 border dark:border-slate-850">
                                    <span class="text-slate-650 dark:text-slate-400 font-medium"><i class="fa-solid fa-circle-xmark text-red-500 mr-2"></i>Rejected Listings</span>
                                    <span class="font-bold text-slate-900 dark:text-white"><?php echo $status_counts['rejected']; ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
