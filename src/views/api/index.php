<div class="bg-white p-6 rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">API Keys</h2>
        <a href="<?= $baseUrl ?>/api-keys/create" class="btn-primary">
            <i class="fas fa-plus mr-1"></i> Create New API Key
        </a>
    </div>
    
    <?php if (empty($apiKeys)): ?>
        <div class="bg-gray-100 p-6 text-center rounded">
            <i class="fas fa-key text-gray-500 text-3xl mb-2"></i>
            <p class="text-gray-500">No API keys found. Create your first API key to integrate with CRM systems.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">API Key</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($apiKeys as $key): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($key['name']) ?></div>
                                <?php if (!empty($key['description'])): ?>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($key['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <span class="text-sm text-gray-900 font-mono" id="key-<?= $key['id'] ?>">
                                        <?= htmlspecialchars($key['api_key_masked']) ?>
                                    </span>
                                    <button onclick="copyToClipboard('<?= htmlspecialchars($key['api_key']) ?>', this)" 
                                            class="ml-2 text-gray-400 hover:text-blue-500 focus:outline-none" title="Copy to clipboard">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($key['active']): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div><?= date('Y-m-d', strtotime($key['created_at'])) ?></div>
                                <div class="text-xs"><?= htmlspecialchars($key['created_by_username']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if ($key['expires_at']): ?>
                                    <?php 
                                        $expiryDate = strtotime($key['expires_at']);
                                        $now = time();
                                        $expired = $expiryDate < $now;
                                        $expiryClass = $expired ? 'text-red-600' : 'text-gray-500';
                                    ?>
                                    <span class="<?= $expiryClass ?>">
                                        <?= date('Y-m-d', $expiryDate) ?>
                                        <?php if ($expired): ?>
                                            <span class="block text-xs font-medium">Expired</span>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400">Never</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex space-x-2">
                                    <a href="<?= $baseUrl ?>/api-keys/edit?id=<?= $key['id'] ?>" class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <button 
                                        onclick="confirmDelete('<?= htmlspecialchars($key['name']) ?>', <?= $key['id'] ?>)"
                                        class="text-red-600 hover:text-red-900"
                                        title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
        <h3 class="text-lg font-bold mb-4">Confirm Deletion</h3>
        <p class="mb-6">Are you sure you want to delete the API key "<span id="apiKeyName" class="font-semibold"></span>"? This action cannot be undone and any systems using this key will lose access.</p>
        
        <div class="flex justify-end space-x-3">
            <button onclick="closeDeleteModal()" class="btn-secondary">
                Cancel
            </button>
            <form id="deleteForm" method="post" action="">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn-danger">
                    <i class="fas fa-trash mr-1"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text, button) {
    // Create a temporary input element
    const input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    
    // Show feedback
    const originalIcon = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i>';
    button.classList.add('text-green-500');
    
    setTimeout(() => {
        button.innerHTML = originalIcon;
        button.classList.remove('text-green-500');
    }, 2000);
}

function confirmDelete(name, id) {
    document.getElementById('apiKeyName').textContent = name;
    document.getElementById('deleteForm').action = '<?= $baseUrl ?>/api-keys/delete?id=' + id;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}
</script>
