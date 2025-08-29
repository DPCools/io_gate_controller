<div class="bg-white p-6 rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Devices</h2>
        <a href="/devices/create" class="btn-primary">
            <i class="fas fa-plus mr-2"></i> Add Device
        </a>
    </div>
    
    <?php if (empty($devices)): ?>
        <div class="bg-gray-100 p-6 text-center rounded">
            <p class="text-gray-600">No devices found. Click "Add Device" to create one.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Host</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($devices as $device): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($device['name']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    <?= htmlspecialchars($device['host'] . ($device['port'] != 80 && $device['port'] != 443 ? ':' . $device['port'] : '')) ?>
                                </div>
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    <?= date('Y-m-d', strtotime($device['created_at'])) ?>
                                    <div class="text-xs">by <?= htmlspecialchars($device['created_by_username'] ?? 'Unknown') ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex space-x-3">
                                    <a href="/devices/view?id=<?= $device['id'] ?>" class="text-blue-600 hover:text-blue-900" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="/devices/edit?id=<?= $device['id'] ?>" class="text-yellow-600 hover:text-yellow-900" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" class="text-red-600 hover:text-red-900 delete-device" 
                                       data-device-id="<?= $device['id'] ?>"
                                       data-device-name="<?= htmlspecialchars($device['name']) ?>"
                                       title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
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
        <p class="mb-6">Are you sure you want to delete the device "<span id="deviceName"></span>"? This action cannot be undone.</p>
        <div class="flex justify-end space-x-3">
            <button id="cancelDelete" class="btn-secondary">Cancel</button>
            <button id="confirmDelete" class="btn-danger">Delete</button>
        </div>
    </div>
</div>

<!-- Warning Modal (Gates Attached) -->
<div id="warningModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-6 rounded-lg shadow-lg max-w-md mx-auto">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Cannot Delete Device</h3>
        <p class="mb-4 text-gray-800">
            Gate attached to Device — unable to delete.
        </p>
        <p class="mb-6 text-gray-600 text-sm">
            Tip: Move the gate to another device to delete this device, then move it back; or delete the gate first.
        </p>
        <div class="flex justify-end">
            <button id="warningOk" class="btn-primary">OK</button>
        </div>
    </div>
    </div>

<!-- JavaScript for Device Management -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete Device Modal + Gates-Attached Warning
    const deleteModal = document.getElementById('deleteModal');
    const warningModal = document.getElementById('warningModal');
    const deviceNameSpan = document.getElementById('deviceName');
    const cancelDelete = document.getElementById('cancelDelete');
    const confirmDelete = document.getElementById('confirmDelete');
    const warningOk = document.getElementById('warningOk');
    let currentDeviceId = null;
    
    // Show delete modal, but first check if device has gates
    document.querySelectorAll('.delete-device').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            currentDeviceId = this.getAttribute('data-device-id');
            const deviceName = this.getAttribute('data-device-name');
            // Check via AJAX if gates exist for this device
            // Cache-bust to avoid stale responses and ensure cookies are sent
            fetch(`/ajax.php?action=get_gates_by_device&device_id=${encodeURIComponent(currentDeviceId)}&_=${Date.now()}`,
                { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(r => {
                    if (!r.ok) { throw new Error(`HTTP ${r.status}`); }
                    return r.json();
                })
                .then(data => {
                    const ok = !!(data && data.success);
                    const gates = ok && Array.isArray(data.gates) ? data.gates : [];
                    try { console.debug('get_gates_by_device', { deviceId: currentDeviceId, ok, gatesCount: gates.length, data }); } catch (e) {}
                    if (ok && gates.length > 0) {
                        // Show warning modal
                        warningModal.classList.remove('hidden');
                    } else {
                        // Proceed to show delete confirmation
                        deviceNameSpan.textContent = deviceName;
                        deleteModal.classList.remove('hidden');
                    }
                })
                .catch(() => {
                    // On fetch error, do not block deletion; show confirmation
                    deviceNameSpan.textContent = deviceName;
                    deleteModal.classList.remove('hidden');
                });
        });
    });
    
    // Hide modal
    cancelDelete.addEventListener('click', function() {
        deleteModal.classList.add('hidden');
        currentDeviceId = null;
    });
    
    // Confirm delete (submit POST as required by route)
    confirmDelete.addEventListener('click', function() {
        if (currentDeviceId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/devices/delete?id=${encodeURIComponent(currentDeviceId)}`;
            document.body.appendChild(form);
            form.submit();
        }
    });
    
    // Also hide modal if clicking outside
    deleteModal.addEventListener('click', function(e) {
        if (e.target === deleteModal) {
            deleteModal.classList.add('hidden');
            currentDeviceId = null;
        }
    });

    // Warning modal OK button
    warningOk.addEventListener('click', function() {
        warningModal.classList.add('hidden');
    });
    // Hide warning if clicking outside
    warningModal.addEventListener('click', function(e) {
        if (e.target === warningModal) {
            warningModal.classList.add('hidden');
        }
    });
});
</script>
