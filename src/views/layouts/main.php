<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Gate Controller System' ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <!-- AlpineJS for interactive components -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="min-h-screen flex flex-col bg-gray-100">
    <?php if (!isset($content)) { $content = ''; } ?>
    <?php if (!isset($currentPage)) { $currentPage = ''; } ?>
    <!-- Top Navigation -->
    <?php if (isset($isAuthenticated) && $isAuthenticated): ?>
    <nav class="bg-blue-800 text-white shadow-md">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-3">
                <div class="flex items-center space-x-4">
                    <a href="/" class="text-xl font-bold">Gate Controller</a>
                    
                    <div class="hidden md:flex space-x-4">
                        <a href="/dashboard" class="py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'dashboard' ? 'bg-blue-700' : '' ?>">
                            Dashboard
                        </a>
                        <a href="/status" class="py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'status' ? 'bg-blue-700' : '' ?>">
                            Status
                        </a>
                        <a href="/devices" class="py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'devices' ? 'bg-blue-700' : '' ?>">
                            Devices
                        </a>
                        <a href="/gates" class="py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'gates' ? 'bg-blue-700' : '' ?>">
                            Gates
                        </a>
                        <?php if (isset($isAdmin) && $isAdmin): ?>
                        <a href="/users" class="py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'users' ? 'bg-blue-700' : '' ?>">
                            Users
                        </a>
                        <a href="/api-keys" class="py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'api-keys' ? 'bg-blue-700' : '' ?>">
                            API Keys
                        </a>
                        <?php endif; ?>
                        <a href="/index.php?route=logs" class="py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'logs' ? 'bg-blue-700' : '' ?>">
                            Logs
                        </a>
                        <a href="/index.php?route=help" class="py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'help' ? 'bg-blue-700' : '' ?>">
                            Help
                        </a>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <?php if (isset($currentUser)): ?>
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 focus:outline-none">
                            <div class="rounded-full bg-blue-600 w-8 h-8 flex items-center justify-center">
                                <span><?= substr($currentUser['username'], 0, 1) ?></span>
                            </div>
                            <span class="hidden md:inline"><?= htmlspecialchars($currentUser['username']) ?></span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        
                        <div x-show="open" 
                             @click.away="open = false"
                             class="absolute right-0 mt-2 w-48 bg-white rounded shadow-lg py-1 z-10">
                            <a href="/profile" class="block px-4 py-2 text-gray-800 hover:bg-blue-100">Profile</a>
                            <a href="/logout" class="block px-4 py-2 text-gray-800 hover:bg-blue-100">Logout</a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Mobile menu button -->
                    <div class="md:hidden" x-data="{ open: false }">
                        <button @click="open = !open" class="text-white focus:outline-none">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        
                        <!-- Mobile menu -->
                        <div x-show="open" 
                             @click.away="open = false"
                             class="absolute top-16 right-0 left-0 bg-blue-800 shadow-md p-4 z-20">
                            <a href="/dashboard" class="block py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'dashboard' ? 'bg-blue-700' : '' ?>">
                                Dashboard
                            </a>
                            <a href="/status" class="block py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'status' ? 'bg-blue-700' : '' ?>">
                                Status
                            </a>
                            <a href="/devices" class="block py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'devices' ? 'bg-blue-700' : '' ?>">
                                Devices
                            </a>
                            <a href="/gates" class="block py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'gates' ? 'bg-blue-700' : '' ?>">
                                Gates
                            </a>
                            <?php if (isset($isAdmin) && $isAdmin): ?>
                            <a href="/users" class="block py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'users' ? 'bg-blue-700' : '' ?>">
                                Users
                            </a>
                            <a href="/api-keys" class="block py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'api-keys' ? 'bg-blue-700' : '' ?>">
                                API Keys
                            </a>
                            <?php endif; ?>
                            <a href="/index.php?route=logs" class="block py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'logs' ? 'bg-blue-700' : '' ?>">
                                Logs
                            </a>
                            <a href="/index.php?route=help" class="block py-2 px-3 hover:bg-blue-700 rounded <?= $currentPage == 'help' ? 'bg-blue-700' : '' ?>">
                                Help
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main class="container mx-auto px-4 py-6 flex-1">
        <?php if (isset($flash) && !empty($flash)): ?>
            <div class="mb-6 <?= $flash['type'] === 'error' ? 'bg-red-100 border-red-500 text-red-700' : 'bg-green-100 border-green-500 text-green-700' ?> border-l-4 p-4 rounded">
                <div class="flex items-center">
                    <div class="py-1">
                        <i class="fas <?= $flash['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check' ?> mr-3"></i>
                    </div>
                    <div>
                        <p class="font-bold"><?= $flash['type'] === 'error' ? 'Error' : 'Success' ?></p>
                        <p class="text-sm"><?= htmlspecialchars($flash['message']) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($pageTitle)): ?>
            <h1 class="text-2xl font-bold mb-6"><?= htmlspecialchars($pageTitle) ?></h1>
        <?php endif; ?>
        
        <?= $content ?>
    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-200 py-4">
        <div class="container mx-auto px-4 text-center text-gray-600">
            <p>&copy; <?= date('Y') ?> InfiniteLimits LTD  - Gate Controller System</p>
        </div>
    </footer>

    <!-- Custom Scripts -->
    <?php if (isset($scripts)): ?>
        <?= $scripts ?>
    <?php endif; ?>
</body>
</html>
