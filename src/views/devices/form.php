<div class="bg-white p-6 rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold"><?= isset($device) ? 'Edit Device' : 'Add New Device' ?></h2>
        <a href="<?= $baseUrl ?>/devices" class="btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i> Back to Devices
        </a>
    </div>
    
    <form method="post" action="<?= isset($device) ? ($baseUrl . '/devices/edit?id=' . urlencode($device['id'])) : ($baseUrl . '/device/create') ?>" id="deviceForm">
        <?php if (isset($device)): ?>
            <input type="hidden" name="id" value="<?= $device['id'] ?>">
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Basic Information -->
            <div class="col-span-2 bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4">Basic Information</h3>
                
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 font-medium mb-2">Name *</label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        class="form-input w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        placeholder="Enter device name"
                        required
                        value="<?= htmlspecialchars($device['name'] ?? '') ?>"
                    >
                    <p class="text-sm text-gray-500 mt-1">A unique name to identify this device</p>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="block text-gray-700 font-medium mb-2">Description</label>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-textarea w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        placeholder="Enter device description"
                        rows="3"
                    ><?= htmlspecialchars($device['description'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- Connection Settings -->
            <div class="col-span-2 md:col-span-1 bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4">Connection Settings</h3>
                
                <div class="mb-4">
                    <label for="scheme" class="block text-gray-700 font-medium mb-2">Connection Type *</label>
                    <select 
                        id="scheme" 
                        name="scheme" 
                        class="form-select w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    >
                        <option value="http" <?= isset($device) && $device['scheme'] === 'http' ? 'selected' : '' ?>>HTTP</option>
                        <option value="https" <?= isset($device) && $device['scheme'] === 'https' ? 'selected' : '' ?>>HTTPS</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="host" class="block text-gray-700 font-medium mb-2">Host *</label>
                    <input 
                        type="text" 
                        id="host" 
                        name="host" 
                        class="form-input w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        placeholder="e.g. 192.168.1.100 or device.example.com"
                        required
                        value="<?= htmlspecialchars($device['host'] ?? '') ?>"
                    >
                </div>
                
                <div class="mb-4">
                    <label for="port" class="block text-gray-700 font-medium mb-2">Port *</label>
                    <input 
                        type="number" 
                        id="port" 
                        name="port" 
                        class="form-input w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        placeholder="80 for HTTP, 443 for HTTPS"
                        required
                        min="1"
                        max="65535"
                        value="<?= htmlspecialchars($device['port'] ?? '80') ?>"
                    >
                </div>
                
                <div class="mb-4">
                    <label for="base_path" class="block text-gray-700 font-medium mb-2">Base Path</label>
                    <input 
                        type="text" 
                        id="base_path" 
                        name="base_path" 
                        class="form-input w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        placeholder="e.g. /axis-cgi"
                        value="<?= htmlspecialchars($device['base_path'] ?? '/') ?>"
                    >
                    <p class="text-sm text-gray-500 mt-1">Base path to VAPIX API endpoint (default: /)</p>
                </div>
                
                <div class="mb-4 flex items-center">
                    <!-- Hidden input ensures a value is always submitted when checkbox is unchecked -->
                    <input type="hidden" name="insecure" value="0">
                    <input 
                        type="checkbox" 
                        id="insecure" 
                        name="insecure" 
                        value="1"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        <?= isset($device) && $device['insecure'] ? 'checked' : '' ?>
                    >
                    <label for="insecure" class="ml-2 block text-gray-700">
                        Skip TLS verification (insecure)
                    </label>
                </div>
                
                <div class="mb-4 flex items-center">
                    <!-- Hidden input ensures a value is always submitted when checkbox is unchecked -->
                    <input type="hidden" name="tlsv1_2" value="0">
                    <input 
                        type="checkbox" 
                        id="tlsv1_2" 
                        name="tlsv1_2" 
                        value="1"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        <?= isset($device) && $device['tlsv1_2'] ? 'checked' : '' ?>
                    >
                    <label for="tlsv1_2" class="ml-2 block text-gray-700">
                        Force TLS v1.2
                    </label>
                </div>
            </div>
            
            <!-- Authentication -->
            <div class="col-span-2 md:col-span-1 bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4">Authentication</h3>
                
                <div class="mb-4">
                    <label for="auth" class="block text-gray-700 font-medium mb-2">Authentication Type *</label>
                    <select 
                        id="auth" 
                        name="auth" 
                        class="form-select w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    >
                        <option value="basic" <?= isset($device) && $device['auth'] === 'basic' ? 'selected' : '' ?>>Basic Authentication</option>
                        <option value="digest" <?= isset($device) && $device['auth'] === 'digest' ? 'selected' : '' ?>>Digest Authentication</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="username" class="block text-gray-700 font-medium mb-2">Username *</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-input w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        placeholder="Device username"
                        required
                        value="<?= htmlspecialchars($device['username'] ?? '') ?>"
                    >
                </div>
                
                <div class="mb-4">
                    <label for="password" class="block text-gray-700 font-medium mb-2">
                        <?= isset($device) ? 'Password (leave blank to keep current)' : 'Password *' ?>
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        placeholder="Device password"
                        <?= isset($device) ? '' : 'required' ?>
                    >
                </div>
                
                <?php if (isset($device)): ?>
                    <div class="mb-4">
                        <label for="confirm_password" class="block text-gray-700 font-medium mb-2">Confirm Password</label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-input w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                            placeholder="Confirm password if changing"
                        >
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end space-x-3">
            <a href="<?= $baseUrl ?>/devices" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">
                <?= isset($device) ? 'Update Device' : 'Add Device' ?>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Port auto-fill based on scheme
    const schemeSelect = document.getElementById('scheme');
    const portInput = document.getElementById('port');
    
    // Only auto-fill if port is at default value
    schemeSelect.addEventListener('change', function() {
        const currentPort = parseInt(portInput.value, 10);
        if (currentPort === 80 || currentPort === 443) {
            portInput.value = this.value === 'https' ? '443' : '80';
        }
    });
    
    // Form validation
    const deviceForm = document.getElementById('deviceForm');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    deviceForm.addEventListener('submit', function(e) {
        // If we're editing and both password fields have values, check they match
        if (confirmPasswordInput && passwordInput.value && confirmPasswordInput.value) {
            if (passwordInput.value !== confirmPasswordInput.value) {
                e.preventDefault();
                alert('Passwords do not match');
                return false;
            }
        }
        
        return true;
    });
});
</script>
