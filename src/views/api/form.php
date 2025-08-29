<div class="bg-white p-6 rounded-lg shadow-md">
    <div class="mb-6">
        <h2 class="text-2xl font-bold"><?= $isEdit ? 'Edit' : 'Create' ?> API Key</h2>
        <p class="text-gray-500 mt-1">
            <?= $isEdit ? 'Update the details for this API key.' : 'Create a new API key for CRM integration.' ?>
        </p>
    </div>
    
    <form method="post" action="<?= $isEdit ? ($baseUrl . '/api-keys/edit?id=' . urlencode($apiKey['id'])) : ($baseUrl . '/api-keys/create') ?>" id="apiKeyForm">
        <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Name -->
            <div class="col-span-2">
                <label for="name" class="block text-sm font-medium text-gray-700">Name *</label>
                <input type="text" id="name" name="name" 
                       class="form-input mt-1 block w-full" 
                       value="<?= htmlspecialchars($apiKey['name'] ?? '') ?>" 
                       placeholder="e.g., CRM Integration" required>
                <p class="text-xs text-gray-500 mt-1">A descriptive name for this API key</p>
            </div>
            
            <!-- Description -->
            <div class="col-span-2">
                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                <textarea id="description" name="description" 
                          class="form-textarea mt-1 block w-full" 
                          rows="2" 
                          placeholder="e.g., Used by the CRM system to trigger gates"><?= htmlspecialchars($apiKey['description'] ?? '') ?></textarea>
            </div>
            
            <!-- Expiration -->
            <div>
                <label for="expires_at" class="block text-sm font-medium text-gray-700">Expiration Date</label>
                <input type="date" id="expires_at" name="expires_at" 
                       class="form-input mt-1 block w-full" 
                       value="<?= !empty($apiKey['expires_at']) ? date('Y-m-d', strtotime($apiKey['expires_at'])) : '' ?>">
                <p class="text-xs text-gray-500 mt-1">Leave blank for no expiration</p>
            </div>
            
            <!-- Active Status -->
            <div>
                <label for="active" class="block text-sm font-medium text-gray-700">Status</label>
                <select id="active" name="active" class="form-select mt-1 block w-full">
                    <option value="1" <?= isset($apiKey['active']) && $apiKey['active'] == 1 ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= isset($apiKey['active']) && $apiKey['active'] == 0 ? 'selected' : '' ?>>Inactive</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Inactive keys cannot be used for API requests</p>
            </div>
            
            <!-- IP Restriction -->
            <div class="col-span-2">
                <label for="allowed_ips" class="block text-sm font-medium text-gray-700">IP Restrictions</label>
                <input type="text" id="allowed_ips" name="allowed_ips" 
                       class="form-input mt-1 block w-full" 
                       value="<?= htmlspecialchars($apiKey['allowed_ips'] ?? '') ?>" 
                       placeholder="e.g., 192.168.1.1, 10.0.0.0/24">
                <p class="text-xs text-gray-500 mt-1">Optional. Comma-separated list of IP addresses or CIDR ranges allowed to use this key. Leave blank to allow any IP.</p>
            </div>
            
            <?php if ($isEdit): ?>
            <div class="col-span-2 border-t border-gray-200 pt-4 mt-2">
                <div class="flex items-center">
                    <span class="text-sm font-medium text-gray-700 mr-4">Created:</span>
                    <span class="text-sm text-gray-500">
                        <?= date('Y-m-d H:i', strtotime($apiKey['created_at'])) ?> by <?= htmlspecialchars($apiKey['created_by_username']) ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!$isEdit): ?>
        <div class="mt-6 p-4 bg-yellow-50 rounded-lg">
            <h3 class="text-sm font-medium text-yellow-800 mb-2">Important Note</h3>
            <p class="text-sm text-yellow-700">
                The API key will be shown only once after creation. Make sure to save it in a secure location.
            </p>
        </div>
        <?php endif; ?>
        
        <div class="mt-6 flex justify-between items-center">
            <a href="<?= $baseUrl ?>/api-keys" class="btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
            <button type="submit" class="btn-primary">
                <i class="fas fa-save mr-1"></i> <?= $isEdit ? 'Update' : 'Create' ?> API Key
            </button>
        </div>
    </form>
    
    <?php if ($showNewApiKey): ?>
    <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg" id="newApiKeyAlert">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-600"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-green-800">API Key Created Successfully</h3>
                <div class="mt-2 text-sm text-green-700">
                    <p class="mb-2">Your new API key:</p>
                    <div class="flex items-center p-2 bg-white border border-gray-200 rounded">
                        <span class="font-mono text-sm mr-2" id="newApiKeyValue"><?= htmlspecialchars($newApiKey) ?></span>
                        <button onclick="copyNewApiKey()" class="text-blue-600 hover:text-blue-800 focus:outline-none" title="Copy to clipboard">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <p class="mt-2">
                        <strong>Important:</strong> This key will not be shown again. Please copy it now and store it securely.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('apiKeyForm');
    
    form.addEventListener('submit', function(event) {
        const nameField = document.getElementById('name');
        
        if (!nameField.value.trim()) {
            event.preventDefault();
            nameField.classList.add('border-red-500');
            
            const errorMsg = document.createElement('p');
            errorMsg.className = 'text-red-500 text-xs mt-1';
            errorMsg.textContent = 'API key name is required';
            
            const existingError = nameField.parentNode.querySelector('.text-red-500');
            if (!existingError) {
                nameField.parentNode.appendChild(errorMsg);
            }
        }
    });
});

<?php if ($showNewApiKey): ?>
function copyNewApiKey() {
    const apiKey = document.getElementById('newApiKeyValue').textContent;
    navigator.clipboard.writeText(apiKey).then(function() {
        // Show feedback
        const button = event.target.closest('button');
        const originalIcon = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i>';
        
        setTimeout(() => {
            button.innerHTML = originalIcon;
        }, 2000);
    });
}

// Auto-dismiss the alert after 1 minute
setTimeout(() => {
    const alert = document.getElementById('newApiKeyAlert');
    if (alert) {
        alert.remove();
    }
}, 60000);

// Scroll the newly created API key into view
window.addEventListener('load', () => {
    const alert = document.getElementById('newApiKeyAlert');
    if (alert) {
        alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
<?php endif; ?>
</script>
