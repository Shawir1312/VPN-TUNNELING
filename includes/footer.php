        </main>
    </div>

    <!-- Mobile Bottom Navigation Bar (< 1024px) -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 z-30 bg-white/95 dark:bg-slate-900/95 backdrop-blur border-t border-slate-200 dark:border-slate-800 px-2 py-1.5 flex justify-around items-center">
        <a href="dashboard.php" class="flex flex-col items-center py-1 px-3 text-xs <?= $currentPage === 'dashboard.php' ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-slate-500 dark:text-slate-400' ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5 mb-0.5"></i>
            <span>Dashboard</span>
        </a>
        <a href="vpn.php" class="flex flex-col items-center py-1 px-3 text-xs <?= $currentPage === 'vpn.php' ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-slate-500 dark:text-slate-400' ?>">
            <i data-lucide="shield-check" class="w-5 h-5 mb-0.5"></i>
            <span>VPN</span>
        </a>
        <a href="nat.php" class="flex flex-col items-center py-1 px-3 text-xs <?= $currentPage === 'nat.php' ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-slate-500 dark:text-slate-400' ?>">
            <i data-lucide="network" class="w-5 h-5 mb-0.5"></i>
            <span>Dst-NAT</span>
        </a>
        <?php if (($currentUser['role'] ?? '') === 'superadmin'): ?>
            <a href="admins.php" class="flex flex-col items-center py-1 px-3 text-xs <?= $currentPage === 'admins.php' ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-slate-500 dark:text-slate-400' ?>">
                <i data-lucide="users" class="w-5 h-5 mb-0.5"></i>
                <span>Admin</span>
            </a>
        <?php else: ?>
            <a href="settings.php" class="flex flex-col items-center py-1 px-3 text-xs <?= $currentPage === 'settings.php' ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-slate-500 dark:text-slate-400' ?>">
                <i data-lucide="sliders" class="w-5 h-5 mb-0.5"></i>
                <span>Settings</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- Global Scripts -->
    <script>
        // Init Lucide Icons
        lucide.createIcons();

        // Mobile Drawer Toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const closeDrawerBtn = document.getElementById('closeDrawerBtn');
        const mobileDrawer = document.getElementById('mobileDrawer');
        const mobileDrawerBackdrop = document.getElementById('mobileDrawerBackdrop');

        function toggleDrawer(open) {
            if (open) {
                mobileDrawer.classList.remove('-translate-x-full');
                mobileDrawerBackdrop.classList.remove('hidden');
            } else {
                mobileDrawer.classList.add('-translate-x-full');
                mobileDrawerBackdrop.classList.add('hidden');
            }
        }

        if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', () => toggleDrawer(true));
        if (closeDrawerBtn) closeDrawerBtn.addEventListener('click', () => toggleDrawer(false));
        if (mobileDrawerBackdrop) mobileDrawerBackdrop.addEventListener('click', () => toggleDrawer(false));

        // User Dropdown Toggle
        const userDropdownBtn = document.getElementById('userDropdownBtn');
        const userDropdownMenu = document.getElementById('userDropdownMenu');
        if (userDropdownBtn && userDropdownMenu) {
            userDropdownBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdownMenu.classList.toggle('hidden');
            });
            document.addEventListener('click', () => {
                userDropdownMenu.classList.add('hidden');
            });
        }

        // Theme Toggle (Light / Dark)
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                document.documentElement.classList.add('transitioning');
                const isDark = document.documentElement.classList.toggle('dark');
                const newTheme = isDark ? 'dark' : 'light';
                localStorage.setItem('theme_preference', newTheme);
                
                // Re-render icons
                lucide.createIcons();

                // Sinkronisasi ke DB user via async fetch
                fetch('api/theme_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ theme: newTheme })
                }).catch(() => {});

                setTimeout(() => {
                    document.documentElement.classList.remove('transitioning');
                }, 300);
            });
        }
    </script>
</body>
</html>

