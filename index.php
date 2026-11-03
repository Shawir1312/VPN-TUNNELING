<?php
require_once __DIR__ . '/includes/Auth.php';

if (!Auth::checkInstalled()) {
    header('Location: install.php');
    exit;
}

if (Auth::isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        if (Auth::attemptLogin($username, $password)) {
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Username atau password salah / akun dinonaktifkan.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MikroTik VPN & Dst-NAT Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
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
        const savedTheme = localStorage.getItem('theme_preference') || 'dark';
        if (savedTheme === 'dark' || (savedTheme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 min-h-screen flex items-center justify-center p-4">

    <!-- Top Glowing Progress Bar -->
    <div id="topProgressBar" class="fixed top-0 left-0 right-0 h-[3px] bg-gradient-to-r from-blue-500 via-cyan-400 to-indigo-500 z-[10000] opacity-0 transition-all duration-300 pointer-events-none shadow-[0_0_12px_rgba(59,130,246,0.85)]" style="width: 0%;"></div>

    <!-- Futuristic Glassmorphism Loading Overlay -->
    <div id="appLoadingOverlay" class="fixed inset-0 z-[9999] bg-slate-950/75 backdrop-blur-md hidden flex flex-col items-center justify-center transition-opacity duration-300 opacity-0 pointer-events-none">
        <div class="relative flex items-center justify-center mb-6">
            <div class="absolute -inset-8 bg-gradient-to-tr from-blue-600/35 via-cyan-500/30 to-indigo-600/35 rounded-full blur-2xl animate-glow-pulse pointer-events-none"></div>
            <div class="w-20 h-20 rounded-full border-[3px] border-transparent border-t-blue-500 border-r-cyan-400 animate-spin"></div>
            <div class="absolute w-14 h-14 rounded-full border-[2.5px] border-transparent border-b-indigo-500 border-l-purple-500 animate-spin-reverse"></div>
            <div class="absolute w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-cyan-500 flex items-center justify-center text-white shadow-lg shadow-blue-500/50 animate-pulse">
                <i data-lucide="shield" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="text-center space-y-1.5 px-4 max-w-sm">
            <h4 class="text-base font-bold text-white tracking-wide flex items-center justify-center gap-1.5">
                <span>Memverifikasi Kredensial</span>
                <span class="inline-flex gap-1 ml-0.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-bounce" style="animation-delay: 0ms"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-bounce" style="animation-delay: 150ms"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-bounce" style="animation-delay: 300ms"></span>
                </span>
            </h4>
            <p class="text-xs text-blue-200/80 font-mono">Menghubungkan ke sistem...</p>
        </div>
    </div>
    
    <!-- Theme Toggle Floating Button -->
    <div class="fixed top-4 right-4">
        <button id="themeToggleBtn" class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 shadow-md">
            <i data-lucide="sun" class="w-5 h-5 hidden dark:block text-amber-400"></i>
            <i data-lucide="moon" class="w-5 h-5 block dark:hidden text-slate-600"></i>
        </button>
    </div>

    <div class="max-w-md w-full bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-6 sm:p-8">
        
        <!-- Logo & Title -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-600 text-white shadow-lg shadow-blue-500/30 mb-3">
                <i data-lucide="shield" class="w-7 h-7"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">MikroTik<span class="text-blue-500">VPN</span></h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">SSTP, L2TP & Dst-NAT Manager</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-5 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 rounded-xl p-3.5 text-rose-700 dark:text-rose-400 text-sm flex items-center space-x-2">
                <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'inactive'): ?>
            <div class="mb-5 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 rounded-xl p-3.5 text-amber-700 dark:text-amber-400 text-sm">
                Akun Anda telah dinonaktifkan oleh administrator.
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <i data-lucide="user" class="w-4 h-4"></i>
                    </span>
                    <input type="text" name="username" required autofocus placeholder="Masukkan username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl pl-9 pr-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-blue-500 transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <i data-lucide="lock" class="w-4 h-4"></i>
                    </span>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl pl-9 pr-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-blue-500 transition">
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition shadow-lg shadow-blue-600/30 flex items-center justify-center space-x-2">
                <span>Masuk ke Dashboard</span>
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </form>

        <div class="mt-6 text-center text-xs text-slate-400 dark:text-slate-500 space-y-1.5">
            <div>Koneksi via MikroTik RouterOS API Port 8728</div>
            <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-center gap-1 font-medium">
                <span>&copy; <?= date('Y') ?> VPN Manager &bull; Developed by <strong class="text-blue-500 font-bold hover:underline">Shawir</strong></span>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        const themeBtn = document.getElementById('themeToggleBtn');
        if (themeBtn) {
            themeBtn.addEventListener('click', () => {
                const isDark = document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme_preference', isDark ? 'dark' : 'light');
                lucide.createIcons();
            });
        }

        // Tampilkan loading animation saat klik submit login
        document.querySelector('form').addEventListener('submit', () => {
            const bar = document.getElementById('topProgressBar');
            const overlay = document.getElementById('appLoadingOverlay');
            if (bar) {
                bar.style.opacity = '1';
                bar.style.width = '70%';
            }
            if (overlay) {
                overlay.classList.remove('hidden', 'pointer-events-none');
                requestAnimationFrame(() => {
                    overlay.classList.remove('opacity-0');
                    overlay.classList.add('opacity-100');
                });
            }
        });
    </script>
</body>
</html>

