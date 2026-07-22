<?php
/**
 * Shared Header Template
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';

$current_user_id = get_logged_in_user_id();
$current_username = get_logged_in_username();
$current_role = get_logged_in_user_role();
?>
<!DOCTYPE html>
<html lang="en" class="h-full overflow-x-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? esc($page_title) . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Tailwind Configuration -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0284c7',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                        },
                        darkBg: '#0f172a',
                        darkCard: '#1e293b',
                    }
                }
            }
        }
    </script>
    
    <!-- Inline Custom Styles -->
    <style>
        /* Smooth transitions */
        .theme-transition, .theme-transition *, .theme-transition *:before, .theme-transition *:after {
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.2s ease, fill 0.2s ease, stroke 0.2s ease, box-shadow 0.2s ease !important;
        }
        /* Custom scrollbar for premium feel */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        .dark ::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .dark ::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
    
    <!-- Dark Mode Initial Detection -->
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="theme-transition bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 flex flex-col min-h-screen antialiased overflow-x-hidden">

    <!-- Splash Screen -->
    <div id="splash-screen" class="fixed inset-0 flex flex-col items-center justify-center bg-slate-950 z-[99999] transition-all duration-700 ease-in-out" style="display: none;">
        <div class="flex flex-col items-center max-w-xs md:max-w-md px-4 text-center space-y-6">
            <!-- Center Logo -->
            <img src="<?php echo BASE_URL; ?>logo.png?v=2" alt="blustacksstore" class="w-64 md:w-80 h-auto animate-pulse brightness-110">
            <!-- Progress Line -->
            <div class="w-32 h-1 bg-slate-800 rounded-full overflow-hidden relative">
                <div id="splash-progress" class="absolute inset-y-0 left-0 bg-primary-500 rounded-full" style="width: 0%;"></div>
            </div>
        </div>
    </div>
    
    <style>
        @keyframes loadingProgress {
            0% { width: 0%; }
            100% { width: 100%; }
        }
    </style>

    <script>
        // Splash Screen Handler
        (function() {
            const splash = document.getElementById('splash-screen');
            const progress = document.getElementById('splash-progress');
            
            if (!sessionStorage.getItem('splash_displayed')) {
                // Show splash screen
                if (splash) {
                    splash.style.display = 'flex';
                    // Lock scrolling
                    document.documentElement.style.overflow = 'hidden';
                    document.body.style.overflow = 'hidden';
                }
                
                // Animate progress bar
                if (progress) {
                    progress.style.animation = 'loadingProgress 2s linear forwards';
                }

                // Transition out after 2 seconds
                setTimeout(() => {
                    if (splash) {
                        splash.classList.add('opacity-0', 'pointer-events-none');
                        // Unlock scrolling
                        document.documentElement.style.overflow = '';
                        document.body.style.overflow = '';
                        
                        setTimeout(() => {
                            splash.remove();
                        }, 700); // Remove from DOM after opacity fade transition
                    }
                    sessionStorage.setItem('splash_displayed', 'true');
                }, 2000);
            } else {
                // Instantly remove from DOM if already shown
                if (splash) {
                    splash.remove();
                }
            }
        })();
    </script>

    <!-- Navbar -->
    <header class="sticky top-0 z-50 w-full border-b border-slate-200 bg-white/80 backdrop-blur-md dark:border-slate-800 dark:bg-slate-950/80">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <!-- Brand Logo -->
            <div class="flex items-center gap-6">
                <a href="<?php echo BASE_URL; ?>" class="flex items-center">
                    <img src="<?php echo BASE_URL; ?>logo.png?v=2" alt="blustacksstore" class="h-12 w-auto dark:brightness-110">
                </a>
                
                <!-- Desktop Nav Navigation Links -->
                <nav class="hidden md:flex items-center space-x-1 text-sm font-medium">
                    <a href="<?php echo BASE_URL; ?>" class="rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white transition">Home</a>
                    <a href="<?php echo BASE_URL; ?>browse.php" class="rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white transition">Browse</a>
                    
                    <?php if ($current_role === 'developer' || $current_role === 'admin'): ?>
                        <a href="<?php echo BASE_URL; ?>developer/dashboard.php" class="rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white transition">
                            <i class="fa-solid fa-code mr-1"></i>Developer Portal
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($current_role === 'admin'): ?>
                        <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="rounded-md px-3 py-2 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30 transition">
                            <i class="fa-solid fa-shield-halved mr-1"></i>Admin Panel
                        </a>
                    <?php endif; ?>
                </nav>
            </div>

            <!-- Search and Action Icons -->
            <div class="flex flex-1 items-center justify-end gap-4 max-w-lg">
                <!-- Search Bar -->
                <div class="relative w-full max-w-xs md:max-w-sm hidden sm:block">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fa-solid fa-magnifying-glass text-slate-400 text-sm"></i>
                    </div>
                    <input type="text" id="global-search-input" 
                           class="w-full rounded-full border border-slate-300 bg-slate-100 py-1.5 pl-10 pr-4 text-sm text-slate-900 placeholder-slate-400 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-primary-500 dark:focus:bg-slate-950 transition" 
                           placeholder="Search apps..." autocomplete="off">
                    
                    <!-- Autocomplete Dropdown -->
                    <div id="search-autocomplete-dropdown" class="absolute left-0 right-0 mt-2 hidden rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-800 dark:bg-slate-900 z-50">
                        <div id="autocomplete-results" class="flex flex-col max-h-60 overflow-y-auto"></div>
                    </div>
                </div>

                <!-- Theme Toggle Button -->
                <button id="theme-toggle" class="rounded-full p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition" aria-label="Toggle theme">
                    <i id="theme-toggle-light-icon" class="fa-solid fa-sun hidden text-lg"></i>
                    <i id="theme-toggle-dark-icon" class="fa-solid fa-moon text-lg"></i>
                </button>

                <!-- Auth Buttons / User Profile Dropdown -->
                <?php if (is_logged_in()): ?>
                    <div class="relative">
                        <button id="user-menu-btn" class="flex items-center space-x-2 rounded-full p-1 focus:outline-none focus:ring-2 focus:ring-primary-500 transition">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-600 text-white font-semibold text-sm">
                                <?php echo strtoupper(substr($current_username, 0, 2)); ?>
                            </div>
                            <i class="fa-solid fa-chevron-down text-xs text-slate-500 hidden md:block"></i>
                        </button>
                        
                        <!-- User Menu Dropdown -->
                        <div id="user-menu-dropdown" class="absolute right-0 mt-2 w-48 origin-top-right rounded-xl border border-slate-200 bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none dark:border-slate-800 dark:bg-slate-900 hidden z-50">
                            <div class="border-b border-slate-200 px-4 py-2 dark:border-slate-800">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Signed in as</p>
                                <p class="truncate text-sm font-semibold"><?php echo esc($current_username); ?></p>
                            </div>
                            <a href="<?php echo BASE_URL; ?>account.php" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"><i class="fa-solid fa-circle-user mr-2 text-slate-400"></i>Account Profile</a>
                            <a href="<?php echo BASE_URL; ?>account.php?tab=wishlist" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"><i class="fa-solid fa-heart mr-2 text-slate-400"></i>Wishlist</a>
                            <a href="<?php echo BASE_URL; ?>account.php?tab=history" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"><i class="fa-solid fa-clock-rotate-left mr-2 text-slate-400"></i>Download History</a>
                            
                            <?php if ($current_role !== 'developer' && $current_role !== 'admin'): ?>
                                <hr class="border-slate-200 dark:border-slate-800 my-1">
                                <form action="<?php echo BASE_URL; ?>account.php" method="POST" class="block">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="become_developer">
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-primary-600 hover:bg-slate-100 dark:text-primary-400 dark:hover:bg-slate-800"><i class="fa-solid fa-laptop-code mr-2"></i>Become Developer</button>
                                </form>
                            <?php endif; ?>
                            
                            <hr class="border-slate-200 dark:border-slate-800 my-1">
                            <a href="<?php echo BASE_URL; ?>logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-slate-100 dark:text-red-400 dark:hover:bg-slate-800"><i class="fa-solid fa-right-from-bracket mr-2"></i>Sign Out</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>account.php" class="rounded-full bg-primary-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-primary-700 shadow-md shadow-primary-500/10 dark:bg-primary-500 dark:hover:bg-primary-600 transition">
                        Sign In
                    </a>
                <?php endif; ?>

                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="rounded-full p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 md:hidden transition" aria-label="Toggle navigation">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Search and Links -->
        <div id="mobile-menu" class="hidden border-b border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-950 md:hidden transition">
            <div class="relative w-full mb-3">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <i class="fa-solid fa-magnifying-glass text-slate-400 text-sm"></i>
                </div>
                <input type="text" id="global-search-input-mobile" 
                       class="w-full rounded-full border border-slate-300 bg-slate-100 py-1.5 pl-10 pr-4 text-sm text-slate-900 placeholder-slate-400 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-primary-500 dark:focus:bg-slate-950 transition" 
                       placeholder="Search apps..." autocomplete="off">
                <!-- Autocomplete Dropdown Mobile -->
                <div id="search-autocomplete-dropdown-mobile" class="absolute left-0 right-0 mt-2 hidden rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-800 dark:bg-slate-900 z-50">
                    <div id="autocomplete-results-mobile" class="flex flex-col max-h-60 overflow-y-auto"></div>
                </div>
            </div>
            
            <nav class="flex flex-col space-y-1">
                <a href="<?php echo BASE_URL; ?>" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 transition">Home</a>
                <a href="<?php echo BASE_URL; ?>browse.php" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 transition">Browse</a>
                
                <?php if ($current_role === 'developer' || $current_role === 'admin'): ?>
                    <a href="<?php echo BASE_URL; ?>developer/dashboard.php" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 transition"><i class="fa-solid fa-code mr-2 text-slate-400"></i>Developer Portal</a>
                <?php endif; ?>
                
                <?php if ($current_role === 'admin'): ?>
                    <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30 transition"><i class="fa-solid fa-shield-halved mr-2 text-red-500"></i>Admin Panel</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1">
