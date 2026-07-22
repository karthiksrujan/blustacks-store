<?php
/**
 * Browse Apps Page
 */
$page_title = "Browse Applications";
require_once __DIR__ . '/includes/header.php';

// Get filter inputs
$category_slug = isset($_GET['category']) ? trim($_GET['category']) : '';
$min_rating = isset($_GET['rating']) ? (float)$_GET['rating'] : 0.0;
$price_filter = isset($_GET['price']) ? trim($_GET['price']) : 'all';
$sort_by = isset($_GET['sort']) ? trim($_GET['sort']) : 'trending';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // 1. Fetch categories for filter dropdown
    $cat_list_stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories_list = $cat_list_stmt->fetchAll();

    // 2. Build dynamic SQL query for filtering
    $sql = "
        SELECT a.*, c.name AS category_name, c.slug AS category_slug 
        FROM apps a
        JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'approved' AND a.is_published = 1
    ";
    
    $params = [];

    // Filter by category
    if (!empty($category_slug)) {
        $sql .= " AND c.slug = :category_slug";
        $params['category_slug'] = $category_slug;
    }

    // Filter by rating
    if ($min_rating > 0) {
        $sql .= " AND a.average_rating >= :min_rating";
        $params['min_rating'] = $min_rating;
    }

    // Filter by price
    if ($price_filter === 'free') {
        $sql .= " AND a.price = 0";
    } elseif ($price_filter === 'paid') {
        $sql .= " AND a.price > 0";
    }

    // Filter by search query
    if (!empty($search_query)) {
        $sql .= " AND (a.name LIKE :search OR a.description LIKE :search OR a.short_desc LIKE :search)";
        $params['search'] = '%' . $search_query . '%';
    }

    // Sorting logic
    switch ($sort_by) {
        case 'newest':
            $sql .= " ORDER BY a.created_at DESC";
            break;
        case 'top-rated':
            $sql .= " ORDER BY a.average_rating DESC, a.download_count DESC";
            break;
        case 'downloads':
            $sql .= " ORDER BY a.download_count DESC";
            break;
        case 'trending':
        default:
            $sql .= " ORDER BY (a.download_count * a.average_rating) DESC, a.download_count DESC";
            break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $apps = $stmt->fetchAll();

} catch (PDOException $e) {
    $apps = [];
    $categories_list = [];
}
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
    <div class="flex flex-col lg:flex-row gap-8">
        
        <!-- Filter Sidebar -->
        <aside class="w-full lg:w-64 flex-shrink-0">
            <form action="" method="GET" id="filter-form" class="space-y-4 lg:space-y-6 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 lg:p-6 shadow-sm sticky top-24">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                    <h2 class="font-bold text-lg text-slate-900 dark:text-white flex items-center gap-2"><i class="fa-solid fa-sliders text-primary-500"></i> Filters</h2>
                    <div class="flex items-center gap-4">
                        <button type="button" id="mobile-filter-toggle" class="lg:hidden text-xs text-primary-600 dark:text-primary-400 font-bold flex items-center gap-1">
                            <span>Show Filters</span> <i class="fa-solid fa-chevron-down text-[10px]"></i>
                        </button>
                        <a href="browse.php" class="text-xs text-slate-400 hover:text-primary-600 dark:hover:text-primary-400">Clear All</a>
                    </div>
                </div>

                <div id="filter-fields-container" class="hidden lg:block space-y-6">
                <!-- Search Local -->
                <div class="space-y-2">
                    <label for="search-input" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Keyword Search</label>
                    <div class="relative">
                        <input type="text" name="search" id="local-search-input" value="<?php echo esc($search_query); ?>" 
                               class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2 pl-3 pr-8 text-sm focus:border-primary-500 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                               placeholder="Search current list..." autocomplete="off">
                        <?php if (!empty($search_query)): ?>
                            <a href="browse.php?category=<?php echo urlencode($category_slug); ?>&rating=<?php echo $min_rating; ?>&price=<?php echo $price_filter; ?>&sort=<?php echo $sort_by; ?>" 
                               class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        <?php endif; ?>
                        <!-- Autocomplete dropdown local -->
                        <div id="local-search-dropdown" class="absolute left-0 right-0 mt-2 hidden rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-800 dark:bg-slate-900 z-50">
                            <div id="local-autocomplete-results" class="flex flex-col max-h-60 overflow-y-auto"></div>
                        </div>
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="space-y-2">
                    <label for="category-select" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Category</label>
                    <select name="category" id="category-select" onchange="this.form.submit()" 
                            class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                        <option value="">All Categories</option>
                        <?php foreach ($categories_list as $cat): ?>
                            <option value="<?php echo esc($cat['slug']); ?>" <?php echo $category_slug === $cat['slug'] ? 'selected' : ''; ?>>
                                <?php echo esc($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Rating Filter -->
                <div class="space-y-2">
                    <label for="rating-select" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Minimum Rating</label>
                    <select name="rating" id="rating-select" onchange="this.form.submit()" 
                            class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                        <option value="0" <?php echo $min_rating == 0 ? 'selected' : ''; ?>>Any Rating</option>
                        <option value="4" <?php echo $min_rating == 4 ? 'selected' : ''; ?>>4.0+ Stars</option>
                        <option value="3" <?php echo $min_rating == 3 ? 'selected' : ''; ?>>3.0+ Stars</option>
                        <option value="2" <?php echo $min_rating == 2 ? 'selected' : ''; ?>>2.0+ Stars</option>
                        <option value="1" <?php echo $min_rating == 1 ? 'selected' : ''; ?>>1.0+ Stars</option>
                    </select>
                </div>

                <!-- Price Filter -->
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Price</label>
                    <div class="flex flex-col gap-2">
                        <label class="inline-flex items-center text-sm">
                            <input type="radio" name="price" value="all" <?php echo $price_filter === 'all' ? 'checked' : ''; ?> onchange="this.form.submit()" 
                                   class="text-primary-600 focus:ring-primary-500 dark:bg-slate-800 dark:border-slate-700">
                            <span class="ml-2 text-slate-700 dark:text-slate-300">All (Free & Paid)</span>
                        </label>
                        <label class="inline-flex items-center text-sm">
                            <input type="radio" name="price" value="free" <?php echo $price_filter === 'free' ? 'checked' : ''; ?> onchange="this.form.submit()" 
                                   class="text-primary-600 focus:ring-primary-500 dark:bg-slate-800 dark:border-slate-700">
                            <span class="ml-2 text-slate-700 dark:text-slate-300">Free</span>
                        </label>
                        <label class="inline-flex items-center text-sm">
                            <input type="radio" name="price" value="paid" <?php echo $price_filter === 'paid' ? 'checked' : ''; ?> onchange="this.form.submit()" 
                                   class="text-primary-600 focus:ring-primary-500 dark:bg-slate-800 dark:border-slate-700">
                            <span class="ml-2 text-slate-700 dark:text-slate-300">Paid Only</span>
                        </label>
                    </div>
                </div>

                <!-- Sort Filter -->
                <div class="space-y-2">
                    <label for="sort-select" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Sort By</label>
                    <select name="sort" id="sort-select" onchange="this.form.submit()" 
                            class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2 text-sm focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                        <option value="trending" <?php echo $sort_by === 'trending' ? 'selected' : ''; ?>>Trending</option>
                        <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest Release</option>
                        <option value="top-rated" <?php echo $sort_by === 'top-rated' ? 'selected' : ''; ?>>Top Rated</option>
                        <option value="downloads" <?php echo $sort_by === 'downloads' ? 'selected' : ''; ?>>Downloads</option>
                    </select>
                </div>
                </div> <!-- End #filter-fields-container -->
            </form>
        </aside>

        <!-- Main Product Grid -->
        <main class="flex-1 space-y-6">
            <!-- Summary Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">
                        <?php 
                            if (!empty($category_slug)) {
                                $c_name = '';
                                foreach ($categories_list as $cat) {
                                    if ($cat['slug'] === $category_slug) $c_name = $cat['name'];
                                }
                                echo esc($c_name) . " Apps";
                            } else {
                                echo "All Applications";
                            }
                        ?>
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Showing <?php echo count($apps); ?> matching results
                    </p>
                </div>
            </div>

            <!-- Apps Grid -->
            <?php if (empty($apps)): ?>
                <div class="flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 shadow-sm">
                    <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mb-4">
                        <i class="fa-solid fa-folder-open text-2xl"></i>
                    </div>
                    <h3 class="font-bold text-lg text-slate-900 dark:text-white mb-2">No Apps Found</h3>
                    <p class="text-slate-500 dark:text-slate-400 max-w-sm">
                        We couldn't find any applications matching your filters. Try clearing some criteria or typing a different keyword.
                    </p>
                    <a href="browse.php" class="bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-2.5 rounded-full mt-6 shadow-md transition">
                        Reset Filters
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                    <?php foreach ($apps as $app): ?>
                        <a href="app.php?id=<?php echo $app['id']; ?>" 
                           class="group flex flex-col bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:shadow-xl hover:-translate-y-1 hover:border-primary-500 dark:hover:border-primary-500 transition-all duration-200">
                            <div class="flex gap-4">
                                <img src="<?php echo get_app_icon_url($app); ?>" alt="<?php echo esc($app['name']); ?>" 
                                     class="w-16 h-16 rounded-xl object-cover bg-slate-100 dark:bg-slate-800 border border-slate-200/50 dark:border-slate-700/50">
                                <div class="flex-1 min-w-0">
                                    <span class="text-xs font-semibold text-primary-600 dark:text-primary-400 uppercase tracking-wider"><?php echo esc($app['category_name']); ?></span>
                                    <h3 class="font-bold text-slate-900 dark:text-white truncate mt-0.5 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                        <?php echo esc($app['name']); ?>
                                    </h3>
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <?php echo render_stars($app['average_rating'], 'w-3 h-3'); ?>
                                        <span class="text-xs text-slate-500 dark:text-slate-400 ml-1"><?php echo number_format($app['average_rating'], 1); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <p class="text-slate-500 dark:text-slate-400 text-xs mt-4 line-clamp-2 leading-relaxed flex-1">
                                <?php echo esc($app['short_desc']); ?>
                            </p>

                            <div class="mt-4 flex items-center justify-between border-t border-slate-100 dark:border-slate-800/80 pt-3">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                    <i class="fa-solid fa-download text-primary-500"></i> <?php echo number_format($app['download_count']); ?> downloads
                                </span>
                                <span class="text-xs font-bold text-slate-950 dark:text-white">
                                    <?php echo $app['price'] == 0 ? 'Free' : '$' . number_format($app['price'], 2); ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

    </div>
</div>

<!-- Local Search Autocomplete Script -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const localSearch = document.getElementById('local-search-input');
        const localDropdown = document.getElementById('local-search-dropdown');
        const localResults = document.getElementById('local-autocomplete-results');

        if (!localSearch || !localDropdown || !localResults) return;

        let debounceTimer;

        localSearch.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            const query = localSearch.value.trim();

            if (query.length < 2) {
                localDropdown.classList.add('hidden');
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`${baseUrl}api/autocomplete.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        localResults.innerHTML = '';
                        if (data.length === 0) {
                            localResults.innerHTML = `
                                <div class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                                    No apps found
                                </div>
                            `;
                        } else {
                            data.forEach(app => {
                                const appItem = document.createElement('a');
                                appItem.href = `${baseUrl}app.php?id=${app.id}`;
                                appItem.className = "flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition text-left";
                                appItem.innerHTML = `
                                    <img src="${app.icon_url}" alt="${app.name}" class="w-8 h-8 rounded-lg object-cover">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold truncate text-slate-900 dark:text-white">${app.name}</p>
                                    </div>
                                `;
                                appItem.addEventListener('click', (e) => {
                                    // Set search value and submit form instead of direct link if desired
                                    localSearch.value = app.name;
                                    localDropdown.classList.add('hidden');
                                });
                                localResults.appendChild(appItem);
                            });
                        }
                        localDropdown.classList.remove('hidden');
                    })
                    .catch(err => console.error("Local Autocomplete error: ", err));
            }, 200);
        });

        document.addEventListener('click', (e) => {
            if (!localSearch.contains(e.target) && !localDropdown.contains(e.target)) {
                localDropdown.classList.add('hidden');
            }
        });

        // Mobile Filter Toggle
        const filterToggleBtn = document.getElementById('mobile-filter-toggle');
        const filterFieldsContainer = document.getElementById('filter-fields-container');
        if (filterToggleBtn && filterFieldsContainer) {
            filterToggleBtn.addEventListener('click', () => {
                const isHidden = filterFieldsContainer.classList.contains('hidden');
                if (isHidden) {
                    filterFieldsContainer.classList.remove('hidden');
                    filterToggleBtn.innerHTML = '<span>Hide Filters</span> <i class="fa-solid fa-chevron-up text-[10px]"></i>';
                } else {
                    filterFieldsContainer.classList.add('hidden');
                    filterToggleBtn.innerHTML = '<span>Show Filters</span> <i class="fa-solid fa-chevron-down text-[10px]"></i>';
                }
            });
        }
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
