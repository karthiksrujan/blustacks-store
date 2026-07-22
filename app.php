<?php
/**
 * App Detail Page
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = get_logged_in_user_id();

try {
    // 1. Fetch App details (Approved only, except if the logged-in user is the developer or an admin)
    $app_query = "
        SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.username AS developer_name
        FROM apps a
        JOIN categories c ON a.category_id = c.id
        JOIN users u ON a.developer_id = u.id
        WHERE a.id = :app_id
    ";
    $app_stmt = $pdo->prepare($app_query);
    $app_stmt->execute(['app_id' => $app_id]);
    $app = $app_stmt->fetch();

    if (!$app) {
        header("HTTP/1.1 404 Not Found");
        die("App not found.");
    }

    // Authorization check: if not approved OR not published, only developer owner or admin can view
    if ($app['status'] !== 'approved' || $app['is_published'] == 0) {
        $user_role = get_logged_in_user_role();
        if (!$user_id || ($user_id != $app['developer_id'] && $user_role !== 'admin')) {
            header("HTTP/1.1 403 Forbidden");
            if ($app['status'] !== 'approved') {
                die("Access Denied: This app is pending review.");
            } else {
                die("Access Denied: This app has not been published yet.");
            }
        }
    }

    $page_title = $app['name'];

    // 2. Fetch screenshots
    $ss_stmt = $pdo->prepare("SELECT * FROM screenshots WHERE app_id = ? ORDER BY order_index ASC");
    $ss_stmt->execute([$app_id]);
    $screenshots = $ss_stmt->fetchAll();

    // 3. Fetch permissions
    $perm_stmt = $pdo->prepare("SELECT permission_name FROM permissions WHERE app_id = ?");
    $perm_stmt->execute([$app_id]);
    $permissions_list = $perm_stmt->fetchAll(PDO::FETCH_COLUMN);

    // 4. Fetch related apps (same category, approved and published, limit 4)
    $related_stmt = $pdo->prepare("
        SELECT a.*, c.name AS category_name 
        FROM apps a
        JOIN categories c ON a.category_id = c.id
        WHERE a.category_id = :cat_id AND a.id != :app_id AND a.status = 'approved' AND a.is_published = 1
        ORDER BY a.download_count DESC
        LIMIT 4
    ");
    $related_stmt->execute(['cat_id' => $app['category_id'], 'app_id' => $app_id]);
    $related_apps = $related_stmt->fetchAll();

    // 5. Fetch reviews count and compute distribution
    $rating_dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $dist_stmt = $pdo->prepare("SELECT rating, COUNT(*) as count FROM reviews WHERE app_id = ? GROUP BY rating");
    $dist_stmt->execute([$app_id]);
    $dist_data = $dist_stmt->fetchAll();
    
    $total_reviews = 0;
    foreach ($dist_data as $row) {
        $rating_dist[(int)$row['rating']] = (int)$row['count'];
        $total_reviews += (int)$row['count'];
    }

    // 6. Fetch paginated reviews (newest first, 5 per page)
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 5;
    $offset = ($page - 1) * $limit;
    
    $reviews_stmt = $pdo->prepare("
        SELECT r.*, u.username 
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.app_id = :app_id
        ORDER BY r.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $reviews_stmt->bindValue(':app_id', $app_id, PDO::PARAM_INT);
    $reviews_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $reviews_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $reviews_stmt->execute();
    $reviews = $reviews_stmt->fetchAll();

    // Check user actions
    $has_downloaded_app = has_downloaded($pdo, $user_id, $app_id);
    $is_in_wishlist = is_wishlisted($pdo, $user_id, $app_id);
    
    // Check if user has already reviewed the app
    $has_reviewed = false;
    if ($user_id) {
        $review_check_stmt = $pdo->prepare("SELECT 1 FROM reviews WHERE app_id = ? AND user_id = ?");
        $review_check_stmt->execute([$app_id, $user_id]);
        $has_reviewed = (bool)$review_check_stmt->fetch();
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';

if ($app['status'] !== 'approved' || $app['is_published'] == 0): ?>
    <div class="bg-amber-500 text-white py-2.5 px-4 text-center text-sm font-semibold flex items-center justify-center gap-2 relative z-50 shadow-md">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>
            <?php if ($app['status'] !== 'approved'): ?>
                <strong>Preview Mode:</strong> This app listing is pending Administrator approval.
            <?php else: ?>
                <strong>Preview Mode:</strong> This app is approved but currently <strong>Unpublished (Draft)</strong>. Only you (the owner/admin) can view it.
            <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<!-- App Header Section (Banner & Info) -->
<section class="relative w-full">
    <!-- Wide aspect banner -->
    <div class="h-60 md:h-80 w-full overflow-hidden relative select-none">
        <img src="<?php echo get_app_banner_url($app); ?>" alt="<?php echo esc($app['name']); ?> banner" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-slate-50 via-slate-900/60 to-slate-900/10 dark:from-slate-950 dark:via-slate-950/80"></div>
    </div>
    
    <!-- App Metadata Box overlay -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 -mt-20 md:-mt-24 relative z-10">
        <div class="flex flex-col md:flex-row items-center md:items-end justify-between gap-6 bg-white dark:bg-slate-900 p-6 md:p-8 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl">
            <!-- Icon and Titles -->
            <div class="flex flex-col md:flex-row items-center md:items-end gap-6 text-center md:text-left w-full md:w-auto">
                <img src="<?php echo get_app_icon_url($app); ?>" alt="<?php echo esc($app['name']); ?> icon" 
                     class="w-28 h-28 md:w-32 md:h-32 rounded-3xl object-cover bg-white dark:bg-slate-800 border border-slate-200/50 dark:border-slate-700/50 shadow-md">
                <div class="space-y-2 min-w-0">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-primary-100 text-primary-800 dark:bg-primary-950/50 dark:text-primary-400">
                        <?php echo esc($app['category_name']); ?>
                    </span>
                    <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 dark:text-white truncate"><?php echo esc($app['name']); ?></h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Developed by <span class="font-semibold text-slate-700 dark:text-slate-300"><?php echo esc($app['developer_name']); ?></span>
                    </p>
                    <div class="flex flex-wrap items-center justify-center md:justify-start gap-4 text-xs text-slate-500 dark:text-slate-400 pt-1">
                        <span class="flex items-center gap-1"><i class="fa-solid fa-star text-yellow-400"></i> <strong class="text-slate-900 dark:text-white"><?php echo number_format($app['average_rating'], 1); ?></strong> (<?php echo $total_reviews; ?> reviews)</span>
                        <span>•</span>
                        <span class="flex items-center gap-1"><i class="fa-solid fa-download text-primary-500"></i> <?php echo number_format($app['download_count']); ?>+ downloads</span>
                        <span>•</span>
                        <span>Size: <?php echo format_bytes($app['file_size']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- CTAs -->
            <div class="flex items-center gap-3 w-full md:w-auto justify-center">
                <!-- Wishlist Toggle -->
                <?php if ($user_id): ?>
                    <button id="wishlist-btn" data-appid="<?php echo $app['id']; ?>" 
                            class="flex items-center justify-center p-3 rounded-full border border-slate-300 hover:border-red-500 hover:bg-red-50 dark:border-slate-700 dark:hover:border-red-500 dark:hover:bg-red-950/20 transition-all duration-200 text-lg <?php echo $is_in_wishlist ? 'text-red-500 border-red-300 bg-red-50 dark:border-red-900/50 dark:bg-red-950/20' : 'text-slate-400'; ?>"
                            title="<?php echo $is_in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                        <i class="<?php echo $is_in_wishlist ? 'fa-solid' : 'fa-regular'; ?> fa-heart"></i>
                    </button>
                <?php else: ?>
                    <a href="account.php" class="flex items-center justify-center p-3 rounded-full border border-slate-300 text-slate-400 hover:text-red-500 dark:border-slate-700 transition" title="Add to Wishlist">
                        <i class="fa-regular fa-heart"></i>
                    </a>
                <?php endif; ?>
                
                <!-- Download Button -->
                <a href="<?php echo BASE_URL; ?>download.php?id=<?php echo $app['id']; ?>" 
                   class="flex-1 md:flex-none inline-flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-700 text-white font-semibold px-8 py-3 rounded-full shadow-lg shadow-primary-500/10 hover:shadow-primary-500/20 transition-all duration-200">
                    <i class="fa-solid fa-download"></i>
                    <span>Download <?php echo $app['price'] == 0 ? 'Free' : '$' . number_format($app['price'], 2); ?></span>
                </a>
            </div>
        </div>
    </div>
</section>

<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left 2 Columns: Screenshots & Description & Reviews -->
        <div class="lg:col-span-2 space-y-12">
            
            <!-- Screenshots Carousel -->
            <?php if (!empty($screenshots)): ?>
                <section class="space-y-4">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Screenshots</h2>
                    <div class="relative overflow-hidden rounded-2xl bg-slate-950 border border-slate-200 dark:border-slate-800 aspect-[16/9] group">
                        <!-- Slides -->
                        <div id="screenshot-slides" class="flex h-full transition-transform duration-300 ease-out">
                            <?php foreach ($screenshots as $index => $ss): ?>
                                <div class="min-w-full h-full flex items-center justify-center bg-black">
                                    <img src="<?php echo get_screenshot_url($ss); ?>" alt="Screenshot <?php echo $index + 1; ?>" class="max-w-full max-h-full object-contain pointer-events-none select-none">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Navigation Arrows -->
                        <button id="ss-prev" class="absolute left-4 top-1/2 -translate-y-1/2 bg-slate-900/80 hover:bg-slate-800 text-white w-8 h-8 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-200 border border-slate-700/50">
                            <i class="fa-solid fa-chevron-left text-sm"></i>
                        </button>
                        <button id="ss-next" class="absolute right-4 top-1/2 -translate-y-1/2 bg-slate-900/80 hover:bg-slate-800 text-white w-8 h-8 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-200 border border-slate-700/50">
                            <i class="fa-solid fa-chevron-right text-sm"></i>
                        </button>
                        
                        <!-- Dot indicators -->
                        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-1.5">
                            <?php foreach ($screenshots as $index => $ss): ?>
                                <button class="ss-dot w-2 h-2 rounded-full bg-slate-500/50 transition-all" data-slide="<?php echo $index; ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- App Description -->
            <section class="bg-white dark:bg-slate-900 p-6 md:p-8 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-4">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Description</h2>
                <div class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed space-y-4">
                    <?php echo nl2br(esc($app['description'])); ?>
                </div>
            </section>

            <!-- Reviews Section -->
            <section class="space-y-6">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">User Reviews</h2>
                
                <!-- Ratings Chart Panel -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-white dark:bg-slate-900 p-6 border border-slate-200 dark:border-slate-800 rounded-2xl items-center shadow-sm">
                    <!-- Left: Average score -->
                    <div class="text-center md:border-r border-slate-100 dark:border-slate-800 py-4">
                        <p class="text-5xl font-black text-slate-900 dark:text-white"><?php echo number_format($app['average_rating'], 1); ?></p>
                        <div class="flex justify-center my-2">
                            <?php echo render_stars($app['average_rating'], 'w-5 h-5'); ?>
                        </div>
                        <p class="text-xs text-slate-400 dark:text-slate-500">Based on <?php echo $total_reviews; ?> reviews</p>
                    </div>
                    
                    <!-- Right: Progress bars -->
                    <div class="col-span-2 space-y-2">
                        <?php 
                        for ($star = 5; $star >= 1; $star--): 
                            $count = $rating_dist[$star];
                            $percentage = $total_reviews > 0 ? ($count / $total_reviews) * 100 : 0;
                        ?>
                            <div class="flex items-center gap-3 text-xs">
                                <span class="w-3 text-slate-600 dark:text-slate-400 font-semibold"><?php echo $star; ?></span>
                                <i class="fa-solid fa-star text-yellow-400 text-[10px]"></i>
                                <div class="flex-1 h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-yellow-400 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                                <span class="w-8 text-right text-slate-400"><?php echo $count; ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Leave a Review Form (If downloaded & logged in) -->
                <?php if ($user_id): ?>
                    <?php if ($has_downloaded_app): ?>
                        <?php if ($has_reviewed): ?>
                            <div class="bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900/50 p-4 rounded-xl text-sm text-blue-700 dark:text-blue-300">
                                <i class="fa-solid fa-circle-check mr-2"></i>You have already submitted a review for this application. You can view or delete it in your <a href="account.php" class="font-semibold underline">Account Profile</a>.
                            </div>
                        <?php else: ?>
                            <!-- Review Box -->
                            <form action="<?php echo BASE_URL; ?>api/leave_review.php" method="POST" class="bg-white dark:bg-slate-900 p-6 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-4 shadow-sm">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                
                                <h3 class="font-bold text-slate-900 dark:text-white text-base">Write a Review</h3>
                                
                                <!-- Star Picker -->
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Your Rating</label>
                                    <div class="flex items-center gap-1.5 text-2xl text-gray-300 cursor-pointer" id="star-picker">
                                        <i class="fa-solid fa-star star-option" data-value="1"></i>
                                        <i class="fa-solid fa-star star-option" data-value="2"></i>
                                        <i class="fa-solid fa-star star-option" data-value="3"></i>
                                        <i class="fa-solid fa-star star-option" data-value="4"></i>
                                        <i class="fa-solid fa-star star-option" data-value="5"></i>
                                    </div>
                                    <input type="hidden" name="rating" id="selected-rating" value="" required>
                                </div>
                                
                                <!-- Review Text -->
                                <div class="space-y-1">
                                    <label for="review_text" class="text-xs font-semibold text-slate-500 dark:text-slate-400">Review Text (max 500 characters)</label>
                                    <textarea name="review_text" id="review_text" rows="4" maxlength="500" required
                                              class="w-full rounded-xl border border-slate-300 bg-slate-50 p-3 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                                              placeholder="Share details of your experience with this app..."></textarea>
                                </div>
                                
                                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-2 rounded-full text-sm shadow-md transition">
                                    Submit Review
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="bg-yellow-50 dark:bg-yellow-950/20 border border-yellow-200 dark:border-yellow-900/50 p-4 rounded-xl text-sm text-yellow-800 dark:text-yellow-400">
                            <i class="fa-solid fa-triangle-exclamation mr-2"></i>You must download this application before you can leave a review.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="bg-slate-100 dark:bg-slate-800/50 p-4 rounded-xl text-center text-sm text-slate-500 dark:text-slate-400">
                        Please <a href="account.php" class="text-primary-600 dark:text-primary-400 font-semibold hover:underline">Sign In</a> to review this application.
                    </div>
                <?php endif; ?>

                <!-- Individual Reviews Cards -->
                <div class="space-y-4">
                    <?php if (empty($reviews)): ?>
                        <p class="text-center text-slate-400 dark:text-slate-500 py-6 text-sm">No reviews yet. Be the first to review!</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $rev): ?>
                            <div class="bg-white dark:bg-slate-900 p-5 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-850 flex items-center justify-center font-bold text-xs text-slate-700 dark:text-slate-300">
                                            <?php echo strtoupper(substr($rev['username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo esc($rev['username']); ?></p>
                                            <p class="text-[10px] text-slate-400 dark:text-slate-500"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <?php echo render_stars($rev['rating'], 'w-3.5 h-3.5'); ?>
                                </div>
                                <p class="text-slate-650 dark:text-slate-300 text-xs leading-relaxed">
                                    <?php echo nl2br(esc($rev['review_text'])); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Reviews Pagination -->
                        <?php if ($total_reviews > $limit): ?>
                            <?php $total_pages = ceil($total_reviews / $limit); ?>
                            <nav class="flex justify-center gap-1 mt-6">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="app.php?id=<?php echo $app_id; ?>&page=<?php echo $i; ?>#reviews" 
                                       class="px-3 py-1 text-xs rounded-md border <?php echo $page == $i ? 'bg-primary-600 border-primary-600 text-white font-semibold' : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-450'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </section>
            
        </div>
        
        <!-- Right Column: Sidebar Specs, Version History, Permissions, Related Apps -->
        <div class="space-y-8">
            
            <!-- Collapsible Version History -->
            <section class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <button id="version-header-btn" class="w-full flex items-center justify-between p-5 font-bold text-slate-900 dark:text-white hover:bg-slate-50 dark:hover:bg-slate-800/30 transition text-sm">
                    <span>Version History</span>
                    <i class="fa-solid fa-chevron-down transition" id="version-chevron"></i>
                </button>
                <div id="version-content" class="hidden p-5 border-t border-slate-100 dark:border-slate-800/80 text-xs space-y-4">
                    <div class="relative border-l border-slate-200 dark:border-slate-800 pl-4 space-y-4">
                        <!-- Current version -->
                        <div class="relative">
                            <span class="absolute -left-[21px] top-1 w-2.5 h-2.5 rounded-full bg-primary-600 ring-4 ring-white dark:ring-slate-900"></span>
                            <h4 class="font-bold text-slate-900 dark:text-white">v<?php echo esc($app['version']); ?> (Current)</h4>
                            <p class="text-slate-400 text-[10px] mt-0.5"><?php echo date('M d, Y', strtotime($app['updated_at'])); ?></p>
                            <p class="text-slate-600 dark:text-slate-400 mt-2">Active app version updates. Includes general enhancements and security updates.</p>
                        </div>
                        <!-- Seeded Initial version -->
                        <div class="relative">
                            <span class="absolute -left-[21px] top-1 w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-slate-700 ring-4 ring-white dark:ring-slate-900"></span>
                            <h4 class="font-bold text-slate-450 dark:text-slate-500">v1.0.0</h4>
                            <p class="text-slate-400 text-[10px] mt-0.5">Initial Release</p>
                            <p class="text-slate-500 dark:text-slate-500 mt-2">Initial production deployment to the AppStore.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Permissions / Requirements -->
            <section class="bg-white dark:bg-slate-900 p-6 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm space-y-4">
                <h3 class="font-bold text-slate-900 dark:text-white text-sm">System Permissions</h3>
                <?php if (empty($permissions_list) || (count($permissions_list) === 1 && $permissions_list[0] === 'None')): ?>
                    <p class="text-xs text-slate-400 dark:text-slate-500">No special permissions required.</p>
                <?php else: ?>
                    <ul class="space-y-2 text-xs">
                        <?php foreach ($permissions_list as $perm): ?>
                            <li class="flex items-center gap-2 text-slate-600 dark:text-slate-350 bg-slate-50 dark:bg-slate-950 p-2.5 rounded-lg border border-slate-100 dark:border-slate-800/80">
                                <i class="fa-solid fa-circle-exclamation text-primary-500 text-[10px]"></i>
                                <span><?php echo esc($perm); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <!-- Related Apps Section -->
            <?php if (!empty($related_apps)): ?>
                <section class="space-y-4">
                    <h3 class="font-bold text-slate-900 dark:text-white text-sm">Similar Applications</h3>
                    <div class="flex flex-col gap-4">
                        <?php foreach ($related_apps as $rapp): ?>
                            <a href="app.php?id=<?php echo $rapp['id']; ?>" 
                               class="group flex items-center gap-3 bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:shadow transition duration-200">
                                <img src="<?php echo get_app_icon_url($rapp); ?>" alt="<?php echo esc($rapp['name']); ?>" class="w-12 h-12 rounded-lg object-cover">
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-bold text-slate-900 dark:text-white text-xs truncate group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                        <?php echo esc($rapp['name']); ?>
                                    </h4>
                                    <p class="text-[10px] text-slate-400 truncate mt-0.5"><?php echo esc($rapp['short_desc']); ?></p>
                                    <div class="flex items-center gap-1 mt-1">
                                        <?php echo render_stars($rapp['average_rating'], 'w-2.5 h-2.5'); ?>
                                        <span class="text-[9px] text-slate-400 ml-0.5"><?php echo number_format($rapp['average_rating'], 1); ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        </div>
        
    </div>
</div>

<!-- Screenshots Carousel Slider script -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Screenshots carousel logic
        const ssSlides = document.getElementById('screenshot-slides');
        const ssItems = ssSlides ? ssSlides.children : [];
        const ssPrev = document.getElementById('ss-prev');
        const ssNext = document.getElementById('ss-next');
        const ssDots = document.querySelectorAll('.ss-dot');

        if (ssSlides && ssItems.length > 0) {
            let ssIndex = 0;
            const totalSs = ssItems.length;

            function updateSsCarousel() {
                ssSlides.style.transform = `translateX(-${ssIndex * 100}%)`;
                ssDots.forEach((dot, index) => {
                    if (index === ssIndex) {
                        dot.classList.add('bg-white', 'w-4');
                        dot.classList.remove('bg-slate-500/50');
                    } else {
                        dot.classList.remove('bg-white', 'w-4');
                        dot.classList.add('bg-slate-500/50');
                    }
                });
            }

            if (ssNext) {
                ssNext.addEventListener('click', () => {
                    ssIndex = (ssIndex + 1) % totalSs;
                    updateSsCarousel();
                });
            }
            if (ssPrev) {
                ssPrev.addEventListener('click', () => {
                    ssIndex = (ssIndex - 1 + totalSs) % totalSs;
                    updateSsCarousel();
                });
            }
            ssDots.forEach(dot => {
                dot.addEventListener('click', () => {
                    ssIndex = parseInt(dot.getAttribute('data-slide'));
                    updateSsCarousel();
                });
            });

            updateSsCarousel();
        }

        // Version Accordion toggle
        const versionHeader = document.getElementById('version-header-btn');
        const versionContent = document.getElementById('version-content');
        const versionChevron = document.getElementById('version-chevron');

        if (versionHeader && versionContent) {
            versionHeader.addEventListener('click', () => {
                versionContent.classList.toggle('hidden');
                versionChevron.classList.toggle('rotate-180');
            });
        }

        // Leave review Star Picker interaction
        const stars = document.querySelectorAll('#star-picker .star-option');
        const selectedRatingInput = document.getElementById('selected-rating');

        if (stars.length > 0 && selectedRatingInput) {
            stars.forEach(star => {
                star.addEventListener('mouseover', () => {
                    const hoverValue = parseInt(star.getAttribute('data-value'));
                    highlightStars(hoverValue);
                });

                star.addEventListener('mouseout', () => {
                    const currentValue = parseInt(selectedRatingInput.value) || 0;
                    highlightStars(currentValue);
                });

                star.addEventListener('click', () => {
                    const ratingValue = parseInt(star.getAttribute('data-value'));
                    selectedRatingInput.value = ratingValue;
                    highlightStars(ratingValue);
                });
            });

            function highlightStars(val) {
                stars.forEach(star => {
                    const starVal = parseInt(star.getAttribute('data-value'));
                    if (starVal <= val) {
                        star.classList.remove('text-gray-300');
                        star.classList.add('text-yellow-400');
                    } else {
                        star.classList.add('text-gray-300');
                        star.classList.remove('text-yellow-400');
                    }
                });
            }
        }

        // AJAX Wishlist functionality
        const wishlistBtn = document.getElementById('wishlist-btn');
        if (wishlistBtn) {
            wishlistBtn.addEventListener('click', () => {
                const appId = wishlistBtn.getAttribute('data-appid');
                wishlistBtn.disabled = true;

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
                    wishlistBtn.disabled = false;
                    if (data.status === 'added') {
                        wishlistBtn.classList.remove('text-slate-400');
                        wishlistBtn.classList.add('text-red-500', 'border-red-300', 'bg-red-50', 'dark:border-red-900/50', 'dark:bg-red-950/20');
                        const icon = wishlistBtn.querySelector('i');
                        icon.classList.remove('fa-regular');
                        icon.classList.add('fa-solid');
                        wishlistBtn.title = "Remove from Wishlist";
                    } else if (data.status === 'removed') {
                        wishlistBtn.classList.add('text-slate-400');
                        wishlistBtn.classList.remove('text-red-500', 'border-red-300', 'bg-red-50', 'dark:border-red-900/50', 'dark:bg-red-950/20');
                        const icon = wishlistBtn.querySelector('i');
                        icon.classList.remove('fa-solid');
                        icon.classList.add('fa-regular');
                        wishlistBtn.title = "Add to Wishlist";
                    } else if (data.error) {
                        alert(data.error);
                    }
                })
                .catch(err => {
                    wishlistBtn.disabled = false;
                    console.error("Wishlist toggle failed: ", err);
                });
            });
        }
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
