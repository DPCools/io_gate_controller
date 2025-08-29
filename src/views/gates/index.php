<div class="bg-white p-6 rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Gates</h2>
        <a href="/gates/create" class="btn-primary">
            <i class="fas fa-plus mr-2"></i> Add Gate
        </a>
    </div>
    
    <?php if (isset($deviceFilter)): ?>
        <div class="mb-6 bg-blue-50 p-4 rounded-lg border border-blue-200 flex justify-between items-center">
            <div>
                <h3 class="font-semibold">Filtered by Device: <?= htmlspecialchars($deviceFilter['name']) ?></h3>
                <p class="text-sm text-gray-600"><?= htmlspecialchars($deviceFilter['host']) ?></p>
            </div>
            <a href="/gates" class="btn-secondary btn-sm">
                <i class="fas fa-times mr-1"></i> Clear Filter
            </a>
        </div>
    <?php else: ?>
        <div class="mb-6">
            <label for="device-filter" class="block text-gray-700 font-medium mb-2">Filter by Device</label>
            <div class="flex space-x-2">
                <select id="device-filter" class="form-select w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Devices</option>
                    <?php foreach ($devices as $device): ?>
                        <option value="<?= $device['id'] ?>"><?= htmlspecialchars($device['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button id="apply-filter" class="btn-secondary">Apply</button>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (empty($gates)): ?>
        <div class="bg-gray-100 p-6 text-center rounded">
            <p class="text-gray-600">No gates found. Click "Add Gate" to create one.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IO Port</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pulse Time</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($gates as $gate): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($gate['name']) ?>
                                </div>
                                <?php if (!empty($gate['description'])): ?>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($gate['description']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($gate['close_enabled'])): ?>
                                    <div class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800" title="Auto-close enabled">
                                        <i class="fas fa-door-closed mr-1"></i> Auto-close after <?= (int)($gate['close_delay_seconds'] ?? 20) ?>s<?= !empty($gate['close_io_port']) ? ', port ' . htmlspecialchars((string)$gate['close_io_port']) : '' ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="/gates?device_id=<?= $gate['device_id'] ?>" class="text-sm text-blue-600 hover:text-blue-900">
                                    <?= htmlspecialchars($gate['device_name'] ?? 'Unknown') ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?= htmlspecialchars($gate['io_port']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?= htmlspecialchars($gate['pulse_seconds']) ?> sec
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    <?= date('Y-m-d', strtotime($gate['created_at'])) ?>
                                    <div class="text-xs">by <?= htmlspecialchars($gate['created_by_username'] ?? 'Unknown') ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button class="trigger-gate bg-green-600 text-white p-1 rounded hover:bg-green-700" 
                                            data-gate-id="<?= $gate['id'] ?>"
                                            data-gate-name="<?= htmlspecialchars($gate['name']) ?>"
                                            title="Trigger Gate">
                                        <i class="fas fa-bolt"></i>
                                    </button>
                                    <button class="test-gate bg-blue-600 text-white p-1 rounded hover:bg-blue-700" 
                                            data-gate-id="<?= $gate['id'] ?>"
                                            data-gate-name="<?= htmlspecialchars($gate['name']) ?>"
                                            title="Test Gate">
                                        <i class="fas fa-vial"></i>
                                    </button>
                                    <a href="/gates/edit?id=<?= $gate['id'] ?>" class="bg-yellow-600 text-white p-1 rounded hover:bg-yellow-700" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="delete-gate bg-red-600 text-white p-1 rounded hover:bg-red-700" 
                                            data-gate-id="<?= $gate['id'] ?>"
                                            data-gate-name="<?= htmlspecialchars($gate['name']) ?>"
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
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-6 rounded-lg shadow-lg max-w-md mx-auto">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Confirm Deletion</h3>
        <p class="mb-6">Are you sure you want to delete the gate "<span id="gateName"></span>"? This action cannot be undone.</p>
        <div class="flex justify-end space-x-3">
            <button id="cancelDelete" class="btn-secondary">Cancel</button>
            <button id="confirmDelete" class="btn-danger">Delete</button>
        </div>
    </div>
</div>

<!-- Trigger/Test Result Modal -->
<div id="actionResultModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-6 rounded-lg shadow-lg max-w-md mx-auto">
        <h3 class="text-lg font-bold text-gray-900 mb-4" id="actionTitle">Gate Action Result</h3>
        <div id="actionResult" class="mb-6 p-3 rounded"></div>
        <div class="flex justify-end">
            <button id="closeActionResult" class="btn-primary">Close</button>
        </div>
    </div>
</div>

<!-- JavaScript for Gate Management -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter by Device
    const deviceFilter = document.getElementById('device-filter');
    const applyFilter = document.getElementById('apply-filter');
    
    if (applyFilter) {
        applyFilter.addEventListener('click', function() {
            const deviceId = deviceFilter.value;
            if (deviceId) {
                window.location.href = `/gates?device_id=${deviceId}`;
            } else {
                window.location.href = '/gates';
            }
        });
    }
    
    // Delete Gate Modal
    const deleteModal = document.getElementById('deleteModal');
    const gateNameSpan = document.getElementById('gateName');
    const cancelDelete = document.getElementById('cancelDelete');
    const confirmDelete = document.getElementById('confirmDelete');
    let currentGateId = null;
    
    // Show delete modal
    document.querySelectorAll('.delete-gate').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            currentGateId = this.getAttribute('data-gate-id');
            gateNameSpan.textContent = this.getAttribute('data-gate-name');
            deleteModal.classList.remove('hidden');
        });
    });
    
    // Hide delete modal
    cancelDelete.addEventListener('click', function() {
        deleteModal.classList.add('hidden');
        currentGateId = null;
    });
    
    // Confirm delete
    confirmDelete.addEventListener('click', function() {
        if (currentGateId) {
            window.location.href = `/gates/delete?id=${currentGateId}`;
        }
    });
    
    // Also hide delete modal if clicking outside
    deleteModal.addEventListener('click', function(e) {
        if (e.target === deleteModal) {
            deleteModal.classList.add('hidden');
            currentGateId = null;
        }
    });
    
    // Action Result Modal
    const actionResultModal = document.getElementById('actionResultModal');
    const actionTitle = document.getElementById('actionTitle');
    const actionResult = document.getElementById('actionResult');
    const closeActionResult = document.getElementById('closeActionResult');
    
    // Trigger Gate
    document.querySelectorAll('.trigger-gate').forEach(btn => {
        btn.addEventListener('click', function() {
            const gateId = this.getAttribute('data-gate-id');
            const gateName = this.getAttribute('data-gate-name');
            
            actionTitle.textContent = `Triggering Gate: ${gateName}`;
            actionResult.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin text-blue-600 text-2xl"></i><p class="mt-2">Sending command...</p></div>';
            actionResult.className = 'mb-6 p-3 rounded bg-blue-50';
            actionResultModal.classList.remove('hidden');
            
            // Send trigger request
            fetch(`/gates/trigger?id=${gateId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    actionResult.innerHTML = '<div class="text-center"><i class="fas fa-check-circle text-green-600 text-2xl"></i><p class="mt-2">Gate triggered successfully!</p></div>';
                    actionResult.className = 'mb-6 p-3 rounded bg-green-50';
                } else {
                    actionResult.innerHTML = `<div class="text-center"><i class="fas fa-exclamation-circle text-red-600 text-2xl"></i><p class="mt-2">Failed to trigger gate:</p><p class="font-mono bg-gray-100 p-2 mt-2 text-sm">${data.message || 'Unknown error'}</p></div>`;
                    actionResult.className = 'mb-6 p-3 rounded bg-red-50';
                }
            })
            .catch(error => {
                actionResult.innerHTML = `<div class="text-center"><i class="fas fa-exclamation-circle text-red-600 text-2xl"></i><p class="mt-2">Error:</p><p class="font-mono bg-gray-100 p-2 mt-2 text-sm">${error.message || 'Unknown error'}</p></div>`;
                actionResult.className = 'mb-6 p-3 rounded bg-red-50';
            });
        });
    });
    
    // Test Gate
    document.querySelectorAll('.test-gate').forEach(btn => {
        btn.addEventListener('click', function() {
            const gateId = this.getAttribute('data-gate-id');
            const gateName = this.getAttribute('data-gate-name');
            
            actionTitle.textContent = `Testing Gate: ${gateName}`;
            actionResult.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin text-blue-600 text-2xl"></i><p class="mt-2">Testing connection...</p></div>';
            actionResult.className = 'mb-6 p-3 rounded bg-blue-50';
            actionResultModal.classList.remove('hidden');
            
            // Send test request
            fetch(`/gates/test?id=${gateId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    actionResult.innerHTML = '<div class="text-center"><i class="fas fa-check-circle text-green-600 text-2xl"></i><p class="mt-2">Test successful! Gate and device are working correctly.</p></div>';
                    actionResult.className = 'mb-6 p-3 rounded bg-green-50';
                } else {
                    actionResult.innerHTML = `<div class="text-center"><i class="fas fa-exclamation-circle text-red-600 text-2xl"></i><p class="mt-2">Test failed:</p><p class="font-mono bg-gray-100 p-2 mt-2 text-sm">${data.message || 'Unknown error'}</p></div>`;
                    actionResult.className = 'mb-6 p-3 rounded bg-red-50';
                }
            })
            .catch(error => {
                actionResult.innerHTML = `<div class="text-center"><i class="fas fa-exclamation-circle text-red-600 text-2xl"></i><p class="mt-2">Error:</p><p class="font-mono bg-gray-100 p-2 mt-2 text-sm">${error.message || 'Unknown error'}</p></div>`;
                actionResult.className = 'mb-6 p-3 rounded bg-red-50';
            });
        });
    });
    
    // Close action result modal
    closeActionResult.addEventListener('click', function() {
        actionResultModal.classList.add('hidden');
    });
    
    // Also hide action result modal if clicking outside
    actionResultModal.addEventListener('click', function(e) {
        if (e.target === actionResultModal) {
            actionResultModal.classList.add('hidden');
        }
    });
});
</script>
