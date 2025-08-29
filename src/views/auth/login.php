<div class="flex justify-center items-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800">Gate Controller</h2>
            <p class="text-gray-600 mt-1">Sign in to access your account</p>
        </div>
        
        <form method="post" action="<?php echo $config['app']['base_url'] ?? ''; ?>/login">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 font-medium mb-2">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-input w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                    placeholder="Enter your username"
                    required
                    autofocus
                    value="<?= htmlspecialchars($username ?? '') ?>"
                >
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                    placeholder="Enter your password"
                    required
                >
            </div>
            
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        id="remember" 
                        name="remember" 
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        <?= isset($remember) && $remember ? 'checked' : '' ?>
                    >
                    <label for="remember" class="ml-2 block text-gray-700">
                        Remember me
                    </label>
                </div>
            </div>
            
            <div>
                <button 
                    type="submit" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    Sign In
                </button>
            </div>
        </form>
    </div>
</div>
