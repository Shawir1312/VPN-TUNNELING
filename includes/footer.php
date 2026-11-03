            <!-- Modern Footer with Copyright Shawir -->
            <footer class="mt-12 pt-6 pb-4 text-center text-xs text-slate-400 dark:text-slate-500 border-t border-slate-200/70 dark:border-slate-800/80">
                <div class="flex flex-col sm:flex-row items-center justify-center gap-1.5 font-medium">
                    <span>&copy; <?= date('Y') ?> <strong class="text-slate-700 dark:text-slate-300 font-semibold">MikroTik VPN & NAT Manager</strong>.</span>
                    <span class="hidden sm:inline">&bull;</span>
                    <span class="flex items-center gap-1">
                        Developed by <strong class="text-blue-600 dark:text-blue-400 font-bold hover:underline cursor-pointer">Shawir</strong>
                    </span>
                    <span class="hidden sm:inline">&bull;</span>
                    <span>All Rights Reserved.</span>
                </div>
            </footer>
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
        <a href="mikhmon.php" class="flex flex-col items-center py-1 px-3 text-xs <?= $currentPage === 'mikhmon.php' ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-slate-500 dark:text-slate-400' ?>">
            <i data-lucide="flame" class="w-5 h-5 mb-0.5 text-amber-500"></i>
            <span>Mikhmon</span>
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

        // ==============================================================
        // MODERN LOADING CONTROLLER (TOP PROGRESS & GLASS OVERLAY)
        // ==============================================================
        let progressInterval = null;

        window.startTopProgress = function() {
            const bar = document.getElementById('topProgressBar');
            if (!bar) return;
            clearInterval(progressInterval);
            bar.style.transition = 'width 0.25s ease, opacity 0.2s ease';
            bar.style.opacity = '1';
            bar.style.width = '25%';
            
            let current = 25;
            progressInterval = setInterval(() => {
                if (current < 85) {
                    current += Math.random() * 12 + 5;
                    bar.style.width = Math.min(current, 88) + '%';
                }
            }, 200);
        };

        window.finishTopProgress = function() {
            const bar = document.getElementById('topProgressBar');
            if (!bar) return;
            clearInterval(progressInterval);
            bar.style.width = '100%';
            setTimeout(() => {
                bar.style.opacity = '0';
                setTimeout(() => {
                    bar.style.width = '0%';
                }, 300);
            }, 250);
        };

        window.showLoading = function(title = 'Memproses Data', subtitle = 'Sinkronisasi dengan MikroTik RouterOS...') {
            const overlay = document.getElementById('appLoadingOverlay');
            if (!overlay) return;
            const titleEl = document.getElementById('loadingTitleText');
            const subEl = document.getElementById('loadingSubtitle');
            if (titleEl) titleEl.innerText = title;
            if (subEl) subEl.innerText = subtitle;
            
            overlay.classList.remove('hidden', 'pointer-events-none');
            overlay.classList.add('flex');
            overlay.style.display = 'flex';
            requestAnimationFrame(() => {
                overlay.classList.remove('opacity-0');
                overlay.classList.add('opacity-100');
            });
            window.startTopProgress();
            if (window.lucide) lucide.createIcons();
        };

        window.hideLoading = function() {
            const overlay = document.getElementById('appLoadingOverlay');
            if (!overlay) return;
            overlay.classList.remove('opacity-100');
            overlay.classList.add('opacity-0');
            setTimeout(() => {
                overlay.classList.remove('flex');
                overlay.classList.add('hidden', 'pointer-events-none');
                overlay.style.display = 'none';
            }, 300);
            window.finishTopProgress();
        };

        // Otomatis aktifkan loading saat submit form standar (kecuali bertanda data-no-loading)
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!form || form.hasAttribute('data-no-loading')) return;
            
            // Jangan timpa custom loading message dari modal-modal khusus
            const customForms = ['formAddMikhmon', 'formEditMikhmon', 'formAddVpn', 'formEditVpn', 'formAddNat', 'formEditNat', 'formAddPool', 'formAddProfile'];
            if (form.id && customForms.includes(form.id)) return;
            
            window.showLoading('Menyimpan Data...', 'Mengirim perintah ke MikroTik...');
        });

        // Top progress bar saat klik link navigasi
        document.querySelectorAll('a[href]:not([target="_blank"]):not([href^="#"]):not([href^="javascript:"])').forEach(link => {
            link.addEventListener('click', () => {
                const href = link.getAttribute('href');
                if (href && !href.startsWith('#') && !href.startsWith('javascript:')) {
                    window.startTopProgress();
                }
            });
        });
    </script>
</body>
</html>

