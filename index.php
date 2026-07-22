<?php
/**
 * App Store Homepage
 */
$page_title = "Discover Premium Apps";
require_once __DIR__ . '/includes/header.php';

try {
    // 1. Fetch Featured Apps (Top 4 by average rating)
    $featured_stmt = $pdo->query("
        SELECT a.*, c.name AS category_name, c.slug AS category_slug 
        FROM apps a
        JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'approved' AND a.is_published = 1
        ORDER BY a.average_rating DESC, a.download_count DESC
        LIMIT 4
    ");
    $featured_apps = $featured_stmt->fetchAll();

    // 2. Fetch Category Tiles
    $categories_stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
    $categories = $categories_stmt->fetchAll();

    // 3. Fetch New & Trending Apps (Top 8 by download count)
    $trending_stmt = $pdo->query("
        SELECT a.*, c.name AS category_name 
        FROM apps a
        JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'approved' AND a.is_published = 1
        ORDER BY a.download_count DESC
        LIMIT 8
    ");
    $trending_apps = $trending_stmt->fetchAll();

} catch (PDOException $e) {
    // Fallback empty data if DB errors
    $featured_apps = [];
    $categories = [];
    $trending_apps = [];
}
?>

<!-- Hero Section -->
<section class="relative bg-gradient-to-br from-slate-900 via-slate-800 to-slate-950 text-white overflow-hidden py-10 md:py-20 px-4 sm:px-6 lg:px-8">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(2,132,199,0.15),transparent_45%)]"></div>
    <div class="relative mx-auto max-w-7xl flex flex-col md:flex-row items-center justify-between gap-12">
        <div class="flex-1 space-y-6 text-center md:text-left">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary-500/10 text-primary-400 border border-primary-500/20">
                <i class="fa-solid fa-sparkles text-[10px]"></i> Discover a World of Possibilities
            </span>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-tight">
                The Next Generation <br>
                <span class="bg-gradient-to-r from-primary-400 to-blue-500 bg-clip-text text-transparent">Application Store</span>
            </h1>
            <p class="text-slate-300 text-lg max-w-xl mx-auto md:mx-0">
                Explore an extensive collection of utility, game, and productivity apps built by expert developers. Secured and optimized for your device.
            </p>
            <div class="flex flex-wrap items-center justify-center md:justify-start gap-4">
                <a href="<?php echo BASE_URL; ?>browse.php" class="bg-primary-600 hover:bg-primary-700 text-white font-semibold px-8 py-3 rounded-full shadow-lg shadow-primary-500/20 hover:shadow-primary-500/30 transform hover:-translate-y-0.5 transition duration-200">
                    Discover Apps
                </a>
                <a href="<?php echo BASE_URL; ?>account.php" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white font-semibold px-8 py-3 rounded-full hover:-translate-y-0.5 transition duration-200">
                    Create Account
                </a>
            </div>
        </div>
        <!-- Hero Decorative Image -->
        <div class="flex-1 w-full max-w-md hidden lg:block select-none">
            <div class="relative flex items-center justify-center">
                <div class="absolute w-72 h-72 rounded-full bg-primary-500/20 filter blur-3xl"></div>
                <div class="relative bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-2xl w-80 rotate-6 transform hover:rotate-3 transition duration-500">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-red-500"></span>
                            <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
                            <span class="w-3 h-3 rounded-full bg-green-500"></span>
                        </div>
                        <i class="fa-solid fa-grip text-slate-600"></i>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center gap-3 bg-slate-800/50 p-3 rounded-xl">
                            <div class="w-10 h-10 rounded-lg bg-primary-500 flex items-center justify-center text-white"><i class="fa-solid fa-gamepad"></i></div>
                            <div class="flex-1 h-3 bg-slate-700 rounded"></div>
                            <div class="w-8 h-4 bg-slate-700 rounded-full"></div>
                        </div>
                        <div class="flex items-center gap-3 bg-slate-800/50 p-3 rounded-xl">
                            <div class="w-10 h-10 rounded-lg bg-green-500 flex items-center justify-center text-white"><i class="fa-solid fa-briefcase"></i></div>
                            <div class="flex-1 h-3 bg-slate-700 rounded"></div>
                            <div class="w-8 h-4 bg-slate-700 rounded-full"></div>
                        </div>
                        <div class="flex items-center gap-3 bg-slate-800/50 p-3 rounded-xl">
                            <div class="w-10 h-10 rounded-lg bg-yellow-500 flex items-center justify-center text-white"><i class="fa-solid fa-wrench"></i></div>
                            <div class="flex-1 h-3 bg-slate-700 rounded"></div>
                            <div class="w-8 h-4 bg-slate-700 rounded-full"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 md:py-16 space-y-10 md:space-y-20">

    <!-- Featured Apps Carousel -->
    <?php if (!empty($featured_apps)): ?>
        <section class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Featured Applications</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Curated top rated apps on our platform</p>
                </div>
            </div>
            
            <!-- Carousel Container -->
            <div class="relative overflow-hidden rounded-3xl bg-slate-900 shadow-xl min-h-[360px] sm:min-h-0 aspect-[4/3] sm:aspect-[16/9] md:aspect-[21/9]">
                <!-- Slides Wrapper -->
                <div id="carousel-slides" class="flex h-full transition-transform duration-500 ease-out select-none">
                    <?php foreach ($featured_apps as $index => $app): ?>
                        <div class="min-w-full h-full relative flex items-center">
                            <!-- Background Banner -->
                            <img src="<?php echo get_app_banner_url($app); ?>" alt="<?php echo esc($app['name']); ?>" class="absolute inset-0 w-full h-full object-cover opacity-30 select-none pointer-events-none">
                            <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/70 to-transparent"></div>
                            
                            <!-- Slide Content -->
                            <div class="relative z-10 px-6 sm:px-12 md:px-16 w-full max-w-lg md:max-w-2xl text-white space-y-3 md:space-y-4">
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-primary-600 text-white">
                                    Featured <?php echo esc($app['category_name']); ?>
                                </span>
                                <div class="flex items-center gap-4 w-full">
                                    <img src="<?php echo get_app_icon_url($app); ?>" alt="<?php echo esc($app['name']); ?>" class="w-16 h-16 md:w-20 md:h-20 rounded-2xl flex-shrink-0 object-cover bg-white dark:bg-slate-800 shadow-md">
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-xl md:text-3xl font-bold truncate"><?php echo esc($app['name']); ?></h3>
                                        <p class="text-slate-300 text-xs md:text-sm mt-1 truncate"><?php echo esc($app['short_desc']); ?></p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs md:text-sm text-slate-300">
                                    <span class="flex items-center gap-1"><i class="fa-solid fa-star text-yellow-400"></i> <?php echo number_format($app['average_rating'], 1); ?></span>
                                    <span class="flex items-center gap-1"><i class="fa-solid fa-download text-primary-400"></i> <?php echo number_format($app['download_count']); ?>+ downloads</span>
                                    <span class="flex items-center gap-1"><i class="fa-solid fa-tag text-green-400"></i> <?php echo $app['price'] == 0 ? 'Free' : '$' . number_format($app['price'], 2); ?></span>
                                </div>
                                <a href="<?php echo BASE_URL; ?>app.php?id=<?php echo $app['id']; ?>" class="inline-block bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-2.5 rounded-full transition duration-200">
                                    View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Navigation Controls -->
                <button id="carousel-prev" class="absolute left-4 top-1/2 -translate-y-1/2 bg-slate-800/80 hover:bg-slate-700 text-white w-10 h-10 rounded-full hidden sm:flex items-center justify-center transition border border-slate-700/50 z-20">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <button id="carousel-next" class="absolute right-4 top-1/2 -translate-y-1/2 bg-slate-800/80 hover:bg-slate-700 text-white w-10 h-10 rounded-full hidden sm:flex items-center justify-center transition border border-slate-700/50 z-20">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>

                <!-- Indicator Dots -->
                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex space-x-2 z-20">
                    <?php foreach ($featured_apps as $index => $app): ?>
                        <button class="carousel-dot w-2.5 h-2.5 rounded-full bg-slate-500/50 transition-all duration-300" data-slide="<?php echo $index; ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Category Tiles -->
    <section class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Explore by Category</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Find applications by specific interests</p>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <?php foreach ($categories as $cat): ?>
                <a href="<?php echo BASE_URL; ?>browse.php?category=<?php echo urlencode($cat['slug']); ?>" 
                   class="flex flex-col items-center justify-center p-6 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-lg transform hover:-translate-y-1 transition-all duration-200 text-center group">
                    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center mb-4 group-hover:bg-primary-600 group-hover:text-white transition duration-200">
                        <i class="fa-solid fa-<?php echo esc($cat['icon']); ?> text-xl"></i>
                    </div>
                    <span class="font-semibold text-slate-900 dark:text-white text-sm"><?php echo esc($cat['name']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- New & Trending Section -->
    <section class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">New & Trending</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Most downloaded applications on the platform</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($trending_apps as $app): ?>
                <a href="<?php echo BASE_URL; ?>app.php?id=<?php echo $app['id']; ?>" 
                   class="group relative flex flex-col bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 hover:shadow-xl hover:-translate-y-1 hover:border-primary-500 dark:hover:border-primary-500 transition-all duration-200">
                    <div class="flex gap-4">
                        <img src="<?php echo get_app_icon_url($app); ?>" alt="<?php echo esc($app['name']); ?>" 
                             class="w-16 h-16 rounded-xl object-cover bg-slate-100 dark:bg-slate-800 border border-slate-200/50 dark:border-slate-700/50">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-xs font-semibold text-primary-600 dark:text-primary-400 truncate uppercase"><?php echo esc($app['category_name']); ?></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200">
                                    <i class="fa-solid fa-download text-primary-500 mr-1 text-[8px]"></i> <?php echo number_format($app['download_count']); ?>
                                </span>
                            </div>
                            <h3 class="font-bold text-slate-900 dark:text-white truncate mt-0.5 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                <?php echo esc($app['name']); ?>
                            </h3>
                            <div class="flex items-center gap-1 mt-1">
                                <?php echo render_stars($app['average_rating'], 'w-3 h-3'); ?>
                                <span class="text-xs text-slate-500 dark:text-slate-400 ml-1"><?php echo number_format($app['average_rating'], 1); ?></span>
                            </div>
                        </div>
                    </div>
                    <p class="text-slate-500 dark:text-slate-400 text-xs mt-3 line-clamp-2 leading-relaxed">
                        <?php echo esc($app['short_desc']); ?>
                    </p>
                    <div class="mt-4 flex items-center justify-between text-xs font-semibold pt-3 border-t border-slate-100 dark:border-slate-800/80">
                        <span class="text-slate-400">v<?php echo esc($app['version']); ?></span>
                        <span class="text-green-600 dark:text-green-400"><?php echo $app['price'] == 0 ? 'Free' : '$' . number_format($app['price'], 2); ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

</div>

<!-- Carousel Javascript -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const slides = document.getElementById('carousel-slides');
        const slideItems = slides ? slides.children : [];
        const prevBtn = document.getElementById('carousel-prev');
        const nextBtn = document.getElementById('carousel-next');
        const dots = document.querySelectorAll('.carousel-dot');

        if (!slides || slideItems.length === 0) return;

        let currentIndex = 0;
        const totalSlides = slideItems.length;
        let autoplayTimer;

        function updateCarousel() {
            slides.style.transform = `translateX(-${currentIndex * 100}%)`;
            dots.forEach((dot, index) => {
                if (index === currentIndex) {
                    dot.classList.add('bg-white', 'w-6');
                    dot.classList.remove('bg-slate-500/50');
                } else {
                    dot.classList.remove('bg-white', 'w-6');
                    dot.classList.add('bg-slate-500/50');
                }
            });
        }

        function showNextSlide() {
            currentIndex = (currentIndex + 1) % totalSlides;
            updateCarousel();
        }

        function showPrevSlide() {
            currentIndex = (currentIndex - 1 + totalSlides) % totalSlides;
            updateCarousel();
        }

        function startAutoplay() {
            autoplayTimer = setInterval(showNextSlide, 5000);
        }

        function resetAutoplay() {
            clearInterval(autoplayTimer);
            startAutoplay();
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                showNextSlide();
                resetAutoplay();
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                showPrevSlide();
                resetAutoplay();
            });
        }

        dots.forEach(dot => {
            dot.addEventListener('click', () => {
                currentIndex = parseInt(dot.getAttribute('data-slide'));
                updateCarousel();
                resetAutoplay();
            });
        });

        // Initialize Carousel
        updateCarousel();
        startAutoplay();
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
