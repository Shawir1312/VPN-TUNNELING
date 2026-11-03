<?php
require_once __DIR__ . '/Auth.php';
$currentUser = Auth::requireLogin();
$currentPage = basename($_SERVER['PHP_SELF']);
$routerProfile = Auth::getRouterProfile();
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'MikroTik VPN & NAT Manager' ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 50: '#eff6ff', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' },
                        darkbg: '#0f172a',
                        darkcard: '#1e293b',
                        darkborder: '#334155'
                    }
                }
            }
        }
    </script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Smooth theme transition */
        html.transitioning, html.transitioning * {
            transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease !important;
        }

        /* Modern Loading Keyframes */
        @keyframes spin-reverse {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
        }
        .animate-spin-reverse {
            animation: spin-reverse 1.2s linear infinite;
        }
        @keyframes glow-pulse {
            0%, 100% { opacity: 0.35; transform: scale(0.92); }
            50% { opacity: 0.75; transform: scale(1.08); }
        }
        .animate-glow-pulse {
            animation: glow-pulse 2s ease-in-out infinite;
        }
    </style>
    <script>
        // Inisialisasi tema sebelum render agar tidak berkedip putih
        const savedTheme = localStorage.getItem('theme_preference') || '<?= $currentUser['theme_preference'] ?? 'dark' ?>';
        if (savedTheme === 'dark' || (savedTheme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 min-h-screen flex flex-col antialiased">

    <!-- Modern Top Glowing Progress Bar -->
    <div id="topProgressBar" class="fixed top-0 left-0 right-0 h-[3px] bg-gradient-to-r from-blue-500 via-cyan-400 to-indigo-500 z-[10000] opacity-0 transition-all duration-300 pointer-events-none shadow-[0_0_12px_rgba(59,130,246,0.85)]" style="width: 0%;"></div>

    <!-- Futuristic Glassmorphism Loading Overlay -->
    <div id="appLoadingOverlay" class="fixed inset-0 z-[99999] bg-slate-950/85 backdrop-blur-md hidden items-center justify-center transition-opacity duration-300 opacity-0 pointer-events-none p-4" style="text-align: center;">
        <div class="relative flex flex-col items-center justify-center text-center max-w-sm w-full mx-auto p-6 sm:p-8 rounded-3xl bg-slate-900/90 border border-slate-700/60 shadow-2xl backdrop-blur-xl">
            <!-- Glow Ambient Behind Card -->
            <div class="absolute -inset-4 bg-gradient-to-r from-blue-600/30 via-cyan-500/25 to-indigo-600/30 rounded-3xl blur-2xl pointer-events-none"></div>

            <!-- Animated Spinner Rings & Shield Container -->
            <div class="relative flex items-center justify-center w-24 h-24 mb-5">
                <!-- Ambient Glow Blob -->
                <div class="absolute -inset-4 bg-gradient-to-tr from-blue-500/35 via-cyan-400/30 to-indigo-500/35 rounded-full blur-xl animate-glow-pulse pointer-events-none"></div>
                <!-- Outer Spinning Gradient Ring -->
                <div class="w-20 h-20 rounded-full border-[3px] border-transparent border-t-blue-500 border-r-cyan-400 animate-spin"></div>
                <!-- Middle Reverse-Spinning Ring -->
                <div class="absolute w-14 h-14 rounded-full border-[2.5px] border-transparent border-b-indigo-500 border-l-purple-500 animate-spin-reverse"></div>
                <!-- Center Shield Pulse -->
                <div class="absolute w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-cyan-500 flex items-center justify-center text-white shadow-lg shadow-blue-500/50 animate-pulse">
                    <i data-lucide="shield" class="w-5 h-5"></i>
                </div>
            </div>
            
            <!-- Loading Text Info -->
            <div class="w-full text-center space-y-2">
                <h4 id="loadingTitle" class="text-base sm:text-lg font-bold text-white tracking-wide flex items-center justify-center gap-2">
                    <span id="loadingTitleText">Memproses Data</span>
                    <span class="inline-flex gap-1 ml-0.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-bounce" style="animation-delay: 0ms"></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-bounce" style="animation-delay: 150ms"></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-bounce" style="animation-delay: 300ms"></span>
                    </span>
                </h4>
                <p id="loadingSubtitle" class="text-xs text-blue-200/80 font-mono tracking-tight leading-relaxed">Sinkronisasi dengan MikroTik RouterOS...</p>
            </div>
        </div>
    </div>

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-30 bg-white/80 dark:bg-slate-900/80 backdrop-blur border-b border-slate-200 dark:border-slate-800 px-4 py-3">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            
            <!-- Brand & Mobile Menu Button -->
            <div class="flex items-center space-x-3">
                <button id="mobileMenuBtn" class="lg:hidden p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
                <a href="dashboard.php" class="flex items-center space-x-2.5">
                    <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white font-bold shadow-md shadow-blue-500/20">
                        <i data-lucide="shield" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <span class="font-bold text-base sm:text-lg tracking-tight text-slate-900 dark:text-white">MikroTik<span class="text-blue-500">VPN</span></span>
                        <span class="hidden sm:inline-block text-[10px] px-1.5 py-0.5 ml-1.5 bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 font-semibold rounded">v1.3</span>
                    </div>
                </a>
            </div>

            <!-- Router Status & Quick Tools -->
            <div class="flex items-center space-x-2 sm:space-x-4">
                
                <!-- Router Health Pill -->
                <div id="routerStatusPill" class="flex items-center space-x-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="hidden sm:inline"><?= htmlspecialchars($routerProfile['host'] ?? 'RouterOS') ?></span>
                    <span class="sm:hidden">Online</span>
                </div>

                <!-- Theme Toggle Button -->
                <button id="themeToggleBtn" title="Ganti Tema (Light/Dark)" class="p-2 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    <i data-lucide="sun" class="w-5 h-5 hidden dark:block text-amber-400"></i>
                    <i data-lucide="moon" class="w-5 h-5 block dark:hidden text-slate-600"></i>
                </button>

                <!-- User Dropdown / Profile -->
                <div class="relative">
                    <button id="userDropdownBtn" class="flex items-center space-x-2 p-1.5 sm:px-3 sm:py-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        <div class="w-7 h-7 rounded-lg bg-blue-500/20 text-blue-600 dark:text-blue-400 font-bold flex items-center justify-center text-xs">
                            <?= strtoupper(substr($currentUser['username'], 0, 2)) ?>
                        </div>
                        <div class="hidden sm:block text-left text-xs">
                            <div class="font-semibold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($currentUser['username']) ?></div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400 capitalize"><?= htmlspecialchars($currentUser['role']) ?></div>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 hidden sm:block"></i>
                    </button>
                    <!-- Dropdown Menu -->
                    <div id="userDropdownMenu" class="hidden absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 py-1 z-50">
                        <div class="px-4 py-2 border-b border-slate-100 dark:border-slate-700/60">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Masuk sebagai:</p>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate"><?= htmlspecialchars($currentUser['full_name']) ?></p>
                        </div>
                        <a href="settings.php" class="flex items-center space-x-2 px-4 py-2 text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <i data-lucide="settings" class="w-4 h-4"></i>
                            <span>Pengaturan Akun</span>
                        </a>
                        <div class="border-t border-slate-100 dark:border-slate-700/60 my-1"></div>
                        <a href="logout.php" class="flex items-center space-x-2 px-4 py-2 text-xs text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            <span>Keluar (Logout)</span>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto w-full flex-1 flex">

        <!-- Desktop Sidebar -->
        <aside class="hidden lg:block w-64 p-4 border-r border-slate-200 dark:border-slate-800 flex-shrink-0">
            <div class="sticky top-20 space-y-1">
                <div class="px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    Menu Utama
                </div>
                <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $currentPage === 'dashboard.php' ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="vpn.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $currentPage === 'vpn.php' ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                    <span>VPN (SSTP & L2TP)</span>
                </a>
                <a href="nat.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $currentPage === 'nat.php' ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                    <i data-lucide="network" class="w-5 h-5"></i>
                    <span>Port Forward (Dst-NAT)</span>
                </a>
                <a href="mikhmon.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $currentPage === 'mikhmon.php' ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                    <i data-lucide="flame" class="w-5 h-5 text-amber-500"></i>
                    <span>Mikhmon Online</span>
                </a>

                <?php if ($currentUser['role'] === 'superadmin'): ?>
                    <div class="pt-4 px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        Administrasi
                    </div>
                    <a href="admins.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $currentPage === 'admins.php' ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                        <i data-lucide="users" class="w-5 h-5"></i>
                        <span>Manajemen Admin</span>
                    </a>
                <?php endif; ?>

                <div class="pt-4 px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    Sistem
                </div>
                <a href="settings.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $currentPage === 'settings.php' ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                    <i data-lucide="sliders" class="w-5 h-5"></i>
                    <span>Pengaturan Router</span>
                </a>
            </div>
        </aside>

        <!-- Mobile Drawer Overlay -->
        <div id="mobileDrawerBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden lg:hidden"></div>
        <aside id="mobileDrawer" class="fixed top-0 bottom-0 left-0 w-72 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 p-5 z-50 transform -translate-x-full transition-transform duration-300 lg:hidden flex flex-col">
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                <span class="font-bold text-lg text-slate-900 dark:text-white">MikroTik<span class="text-blue-500">VPN</span></span>
                <button id="closeDrawerBtn" class="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="py-4 space-y-1.5 flex-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center space-x-3 px-3.5 py-3 rounded-xl text-sm font-medium <?= $currentPage === 'dashboard.php' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="vpn.php" class="flex items-center space-x-3 px-3.5 py-3 rounded-xl text-sm font-medium <?= $currentPage === 'vpn.php' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                    <span>VPN (SSTP & L2TP)</span>
                </a>
                <a href="nat.php" class="flex items-center space-x-3 px-3.5 py-3 rounded-xl text-sm font-medium <?= $currentPage === 'nat.php' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                    <i data-lucide="network" class="w-5 h-5"></i>
                    <span>Port Forward (Dst-NAT)</span>
                </a>
                <a href="mikhmon.php" class="flex items-center space-x-3 px-3.5 py-3 rounded-xl text-sm font-medium <?= $currentPage === 'mikhmon.php' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                    <i data-lucide="flame" class="w-5 h-5 text-amber-500"></i>
                    <span>Mikhmon Online</span>
                </a>
                <?php if ($currentUser['role'] === 'superadmin'): ?>
                    <a href="admins.php" class="flex items-center space-x-3 px-3.5 py-3 rounded-xl text-sm font-medium <?= $currentPage === 'admins.php' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                        <i data-lucide="users" class="w-5 h-5"></i>
                        <span>Manajemen Admin</span>
                    </a>
                <?php endif; ?>
                <a href="settings.php" class="flex items-center space-x-3 px-3.5 py-3 rounded-xl text-sm font-medium <?= $currentPage === 'settings.php' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                    <i data-lucide="sliders" class="w-5 h-5"></i>
                    <span>Pengaturan Router</span>
                </a>
            </div>
            <div class="pt-4 border-t border-slate-200 dark:border-slate-800">
                <a href="logout.php" class="flex items-center space-x-3 px-3.5 py-3 rounded-xl text-sm font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30">
                    <i data-lucide="log-out" class="w-5 h-5"></i>
                    <span>Keluar (Logout)</span>
                </a>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 min-w-0 pb-20 lg:pb-8 flex flex-col justify-between">

