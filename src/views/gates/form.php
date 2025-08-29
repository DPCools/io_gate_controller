<div class="bg-white p-6 rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold"><?= isset($gate) ? 'Edit Gate' : 'Add New Gate' ?></h2>
        <a href="/gates" class="btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i> Back to Gates
        </a>
    </div>
    
    <form method="post" action="<?= isset($gate) ? '/gates/edit?id=' . urlencode((string)$gate['id']) : '/gates/create' ?>" id="gateForm">
        <?php if (isset($gate)): ?>
            <input type="hidden" name="id" value="<?= $gate['id'] ?>">
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Basic Information -->
            <div class="col-span-2 bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4">Gate Information</h3>
                
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 font-medium mb-2">Name *</label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        class="form-input w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        placeholder="Enter gate name"
                        required
                        value="<?= htmlspecialchars($gate['name'] ?? '') ?>"
                    >
                    <p class="text-sm text-gray-500 mt-1">A unique name to identify this gate</p>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="block text-gray-700 font-medium mb-2">Description</label>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-textarea w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        placeholder="Enter gate description"
                        rows="2"
                    ><?= htmlspecialchars($gate['description'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- Device Selection -->
            <div class="col-span-2 md:col-span-1 bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4">Device Connection</h3>
                
                <div class="mb-4">
                    <label for="device_id" class="block text-gray-700 font-medium mb-2">Device *</label>
                    <select 
                        id="device_id" 
                        name="device_id" 
                        class="form-select w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    >
                        <option value="">-- Select Device --</option>
                        <?php foreach ($devices as $device): ?>
                            <option 
                                value="<?= $device['id'] ?>" 
                                <?= isset($gate) && $gate['device_id'] == $device['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($device['name']) ?> (<?= htmlspecialchars($device['host']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Select the device this gate is connected to</p>
                    
                    <?php if (empty($devices)): ?>
                        <div class="bg-yellow-100 text-yellow-800 p-2 rounded mt-2">
                            <p><i class="fas fa-exclamation-triangle mr-2"></i> No devices found. <a href="/devices/create" class="underline">Add a device</a> first.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-4">
                    <label for="io_port" class="block text-gray-700 font-medium mb-2">IO Port *</label>
                    <input 
                        type="number" 
                        id="io_port" 
                        name="io_port" 
                        class="form-input w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        placeholder="IO port number (e.g. 1)"
                        required
                        min="1"
                        value="<?= htmlspecialchars($gate['io_port'] ?? '1') ?>"
                    >
                    <p class="text-sm text-gray-500 mt-1">The IO port number on the device</p>
                </div>
                
                <div class="mb-4">
                    <label for="pulse_seconds" class="block text-gray-700 font-medium mb-2">Pulse Time (seconds) *</label>
                    <input 
                        type="number" 
                        id="pulse_seconds" 
                        name="pulse_seconds" 
                        class="form-input w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        placeholder="Pulse duration in seconds"
                        required
                        min="0.1"
                        step="0.1"
                        value="<?= htmlspecialchars($gate['pulse_seconds'] ?? '0.5') ?>"
                    >
                    <p class="text-sm text-gray-500 mt-1">Duration of the pulse in seconds (e.g. 0.5 for 500ms)</p>
                </div>

                <div class="mb-4 border-t pt-4">
                    <h4 class="text-md font-semibold mb-2">Auto-close</h4>
                    <div class="flex items-center mb-3">
                        <input type="checkbox" id="close_enabled" name="close_enabled" class="mr-2" <?= !empty($gate['close_enabled']) ? 'checked' : '' ?>>
                        <label for="close_enabled" class="text-gray-700">Enable auto-close pulse after delay</label>
                    </div>
                    <div id="closeOptions" class="grid grid-cols-1 md:grid-cols-2 gap-4 <?= empty($gate['close_enabled']) ? 'opacity-60' : '' ?>">
                        <div>
                            <label for="close_io_port" class="block text-gray-700 font-medium mb-2">Close IO Port</label>
                            <input
                                type="number"
                                id="close_io_port"
                                name="close_io_port"
                                class="form-input w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Defaults to open port"
                                min="1"
                                value="<?= isset($gate['close_io_port']) ? htmlspecialchars((string)$gate['close_io_port']) : '' ?>"
                                <?= empty($gate['close_enabled']) ? 'disabled' : '' ?>
                            >
                            <p class="text-sm text-gray-500 mt-1">Leave blank to use the same IO port as open.</p>
                        </div>
                        <div>
                            <label for="close_delay_seconds" class="block text-gray-700 font-medium mb-2">Close Delay (seconds)</label>
                            <input
                                type="number"
                                id="close_delay_seconds"
                                name="close_delay_seconds"
                                class="form-input w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="20"
                                min="1"
                                step="1"
                                value="<?= htmlspecialchars(isset($gate['close_delay_seconds']) ? (string)$gate['close_delay_seconds'] : '20') ?>"
                                <?= empty($gate['close_enabled']) ? 'disabled' : '' ?>
                            >
                            <p class="text-sm text-gray-500 mt-1">Time to wait before sending the close pulse.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Test Section -->
            <div class="col-span-2 md:col-span-1 bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4">Testing</h3>
                
                <?php if (isset($gate)): ?>
                <div class="mb-4">
                    <p class="text-gray-700 mb-2">You can test this gate configuration to verify it works correctly.</p>
                    
                    <button 
                        type="button" 
                        id="testGateBtn" 
                        class="btn-info w-full"
                        data-gate-id="<?= $gate['id'] ?>"
                    >
                        <i class="fas fa-vial mr-2"></i> Test Gate Configuration
                    </button>
                    
                    <div id="testResult" class="mt-3 p-3 rounded hidden">
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-gray-200 p-4 rounded-lg text-gray-600">
                    <p>Testing will be available after you save the gate.</p>
                </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <h4 class="font-medium mb-2">How testing works:</h4>
                    <ul class="list-disc pl-5 text-sm text-gray-600 space-y-1">
                        <li>A test pulse will be sent to the configured device and IO port</li>
                        <li>The pulse will use the configured pulse time</li>
                        <li>This is a real pulse and will activate the connected device</li>
                        <li>The test will be logged in the audit log</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end space-x-3">
            <a href="/gates" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">
                <?= isset($gate) ? 'Update Gate' : 'Add Gate' ?>
            </button>
        </div>
    </form>
</div>

<!-- Action Result Modal -->
<div id="actionResultModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-6 rounded-lg shadow-lg max-w-md mx-auto">
        <h3 class="text-lg font-bold text-gray-900 mb-4" id="actionTitle">Gate Test Result</h3>
        <div id="actionResult" class="mb-6 p-3 rounded"></div>
        <div class="flex justify-end">
            <button id="closeActionResult" class="btn-primary">Close</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Test Gate Button
    const testGateBtn = document.getElementById('testGateBtn');
    if (testGateBtn) {
        const actionResultModal = document.getElementById('actionResultModal');
        const actionTitle = document.getElementById('actionTitle');
        const actionResult = document.getElementById('actionResult');
        const closeActionResult = document.getElementById('closeActionResult');
        
        testGateBtn.addEventListener('click', function() {
            const gateId = this.getAttribute('data-gate-id');
            
            actionTitle.textContent = 'Testing Gate Configuration';
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
    }
    
    // Form validation
    const gateForm = document.getElementById('gateForm');
    gateForm.addEventListener('submit', function(e) {
        const deviceId = document.getElementById('device_id').value;
        if (!deviceId) {
            e.preventDefault();
            alert('Please select a device for this gate.');
            return false;
        }
        
        return true;
    });

    // Auto-close toggle
    const closeEnabled = document.getElementById('close_enabled');
    const closeOptions = document.getElementById('closeOptions');
    const closePort = document.getElementById('close_io_port');
    const closeDelay = document.getElementById('close_delay_seconds');
    if (closeEnabled) {
        const applyState = () => {
            const on = closeEnabled.checked;
            closeOptions.classList.toggle('opacity-60', !on);
            closePort.disabled = !on;
            closeDelay.disabled = !on;
        };
        closeEnabled.addEventListener('change', applyState);
        applyState();
    }
});
</script>
