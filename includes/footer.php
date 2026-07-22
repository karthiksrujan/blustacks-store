<?php
/**
 * Shared Footer Template
 */
?>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-200 bg-white py-12 dark:border-slate-800 dark:bg-slate-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Platform Info -->
                <div class="col-span-1 md:col-span-2">
                    <a href="<?php echo BASE_URL; ?>" class="flex items-center mb-4">
                        <img src="<?php echo BASE_URL; ?>logo.png?v=2" alt="blustacksstore" class="h-16 w-auto dark:brightness-110">
                    </a>
                    <p class="text-sm text-slate-500 dark:text-slate-400 max-w-sm">
                        Discover, download, and rate premium applications. A modern store platform built for developers to publish and users to enjoy.
                    </p>
                </div>
                <!-- Navigation links -->
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-4">Browse</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="<?php echo BASE_URL; ?>browse.php" class="text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400">All Apps</a></li>
                        <li><a href="<?php echo BASE_URL; ?>browse.php?category=games" class="text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400">Games</a></li>
                        <li><a href="<?php echo BASE_URL; ?>browse.php?category=productivity" class="text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400">Productivity</a></li>
                        <li><a href="<?php echo BASE_URL; ?>browse.php?category=tools" class="text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400">Tools</a></li>
                    </ul>
                </div>
                <!-- Platform details -->
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-4">Developer</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="<?php echo BASE_URL; ?>developer/dashboard.php" class="text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400">Developer Portal</a></li>
                        <li><a href="<?php echo BASE_URL; ?>developer/dashboard.php?tab=submit" class="text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400">Submit an App</a></li>
                        <li><a href="<?php echo BASE_URL; ?>account.php" class="text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400">Manage Account</a></li>
                    </ul>
                </div>
            </div>
            <div class="mt-8 border-t border-slate-200 pt-8 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-xs text-slate-400 dark:text-slate-500">
                    &copy; <?php echo date('Y'); ?> blustacksstore. Hosted on Infinity Free. All rights reserved.
                </p>
                <div class="flex space-x-6 text-sm text-slate-400 dark:text-slate-500">
                    <a href="#" class="hover:text-slate-600 dark:hover:text-slate-300">Privacy Policy</a>
                    <a href="#" class="hover:text-slate-600 dark:hover:text-slate-300">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Global Javascript -->
    <script>
        const baseUrl = "<?php echo BASE_URL; ?>";

        // 1. Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }

        // 2. User Menu Dropdown Toggle
        const userMenuBtn = document.getElementById('user-menu-btn');
        const userMenuDropdown = document.getElementById('user-menu-dropdown');
        if (userMenuBtn && userMenuDropdown) {
            userMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                userMenuDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', () => {
                userMenuDropdown.classList.add('hidden');
            });
        }

        // 3. Dark Mode Toggle
        const themeToggleBtn = document.getElementById('theme-toggle');
        const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
        const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');

        // Update visual icons based on current state
        function updateThemeIcons() {
            if (document.documentElement.classList.contains('dark')) {
                themeToggleLightIcon.classList.remove('hidden');
                themeToggleDarkIcon.classList.add('hidden');
            } else {
                themeToggleLightIcon.classList.add('hidden');
                themeToggleDarkIcon.classList.remove('hidden');
            }
        }
        updateThemeIcons();

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                document.body.classList.add('theme-transition');
                
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                }
                updateThemeIcons();
                
                setTimeout(() => {
                    document.body.classList.remove('theme-transition');
                }, 300);
            });
        }

        // 4. Live Search Autocomplete Functionality
        function setupAutocomplete(inputId, dropdownId, resultsId) {
            const input = document.getElementById(inputId);
            const dropdown = document.getElementById(dropdownId);
            const results = document.getElementById(resultsId);

            if (!input || !dropdown || !results) return;

            let debounceTimer;

            input.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                const query = input.value.trim();

                if (query.length < 2) {
                    dropdown.classList.add('hidden');
                    return;
                }

                debounceTimer = setTimeout(() => {
                    fetch(`${baseUrl}api/autocomplete.php?q=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(data => {
                            results.innerHTML = '';
                            if (data.length === 0) {
                                results.innerHTML = `
                                    <div class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                                        No apps found matching "${query}"
                                    </div>
                                `;
                            } else {
                                data.forEach(app => {
                                    const appItem = document.createElement('a');
                                    appItem.href = `${baseUrl}app.php?id=${app.id}`;
                                    appItem.className = "flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition text-left";
                                    appItem.innerHTML = `
                                        <img src="${app.icon_url}" alt="${app.name}" class="w-10 h-10 rounded-lg object-cover bg-slate-100 dark:bg-slate-800">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold truncate text-slate-900 dark:text-white">${app.name}</p>
                                            <p class="text-xs truncate text-slate-500 dark:text-slate-400">${app.short_desc}</p>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200">
                                            <i class="fa-solid fa-star text-yellow-400 mr-1 text-[10px]"></i> ${parseFloat(app.average_rating).toFixed(1)}
                                        </span>
                                    `;
                                    results.appendChild(appItem);
                                });
                            }
                            dropdown.classList.remove('hidden');
                        })
                        .catch(err => console.error("Autocomplete fetch error: ", err));
                }, 200);
            });

            // Close on click outside
            document.addEventListener('click', (e) => {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });
            
            // Show autocomplete when focusing back if there is query
            input.addEventListener('focus', () => {
                if (input.value.trim().length >= 2 && results.children.length > 0) {
                    dropdown.classList.remove('hidden');
                }
            });
        }

        // Setup both desktop and mobile autocompletes
        setupAutocomplete('global-search-input', 'search-autocomplete-dropdown', 'autocomplete-results');
        setupAutocomplete('global-search-input-mobile', 'search-autocomplete-dropdown-mobile', 'autocomplete-results-mobile');
    </script>
</body>
</html>
