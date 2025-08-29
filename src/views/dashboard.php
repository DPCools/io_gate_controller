<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Total Gates -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex items-center">
            <div class="rounded-full bg-blue-100 p-3">
                <i class="fas fa-door-open text-blue-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <h3 class="text-gray-500 text-sm">Total Gates</h3>
                <p class="text-2xl font-bold"><?= $stats['gateCount'] ?? 0 ?></p>
            </div>
        </div>
    </div>
    
    <!-- Total Devices -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex items-center">
            <div class="rounded-full bg-green-100 p-3">
                <i class="fas fa-server text-green-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <h3 class="text-gray-500 text-sm">Total Devices</h3>
                <p class="text-2xl font-bold"><?= $stats['deviceCount'] ?? 0 ?></p>
            </div>
        </div>
    </div>
    
    <!-- Triggers Today -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex items-center">
            <div class="rounded-full bg-yellow-100 p-3">
                <i class="fas fa-bolt text-yellow-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <h3 class="text-gray-500 text-sm">Triggers Today</h3>
                <p class="text-2xl font-bold"><?= $stats['triggerCount'] ?? 0 ?></p>
            </div>
        </div>
    </div>
    
    <!-- System Status -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex items-center">
            <div class="rounded-full bg-purple-100 p-3">
                <i class="fas fa-heartbeat text-purple-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <h3 class="text-gray-500 text-sm">System Status</h3>
                <p class="text-lg font-semibold text-green-600">
                    <i class="fas fa-circle text-xs mr-1"></i> 
                    Operational
                </p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Recent Gates Activity -->
    <div class="bg-white p-6 rounded-lg shadow-md lg:col-span-2">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold">Recent Gate Activity</h2>
            <a href="/index.php?route=logs&type=GATE_TRIGGER" class="text-blue-600 hover:underline text-sm">View All</a>
        </div>
        
        <?php if (empty($recentActivity)): ?>
        <div class="text-center py-6 text-gray-500">
            <i class="fas fa-info-circle text-2xl mb-2"></i>
            <p>No recent gate activity found.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gate</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Triggered By</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($recentActivity as $activity): ?>
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            <?php 
                                $createdAt = $activity['created_at'] ?? null;
                                $timeStr = '-';
                                if (!empty($createdAt)) {
                                    $ts = strtotime($createdAt);
                                    if ($ts !== false) { $timeStr = date('H:i:s', $ts); }
                                }
                                echo $timeStr;
                            ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            <?= htmlspecialchars($activity['gate_name']) ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            <?php if ($activity['user_id']): ?>
                                <span title="User">👤 <?= htmlspecialchars($activity['username']) ?></span>
                            <?php elseif ($activity['api_key_id']): ?>
                                <span title="API Key">🔑 <?= htmlspecialchars($activity['api_name']) ?></span>
                            <?php else: ?>
                                <span title="System">⚙️ System</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            <?php if ($activity['success']): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <i class="fas fa-check-circle mr-1"></i> Success
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <i class="fas fa-exclamation-circle mr-1"></i> Failed
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-lg font-bold mb-4">Quick Actions</h2>
        
        <div class="space-y-3">
            <a href="/gates/create" class="block bg-blue-50 hover:bg-blue-100 p-4 rounded-lg transition-colors">
                <div class="flex items-center">
                    <div class="rounded-full bg-blue-100 p-2 mr-3">
                        <i class="fas fa-plus text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="font-medium">Add New Gate</h3>
                        <p class="text-sm text-gray-600">Configure a new gate connection</p>
                    </div>
                </div>
            </a>
            
            <a href="/devices/create" class="block bg-green-50 hover:bg-green-100 p-4 rounded-lg transition-colors">
                <div class="flex items-center">
                    <div class="rounded-full bg-green-100 p-2 mr-3">
                        <i class="fas fa-server text-green-600"></i>
                    </div>
                    <div>
                        <h3 class="font-medium">Add New Device</h3>
                        <p class="text-sm text-gray-600">Configure a new Axis device</p>
                    </div>
                </div>
            </a>
            
            <a href="/index.php?route=logs" class="block bg-purple-50 hover:bg-purple-100 p-4 rounded-lg transition-colors">
                <div class="flex items-center">
                    <div class="rounded-full bg-purple-100 p-2 mr-3">
                        <i class="fas fa-history text-purple-600"></i>
                    </div>
                    <div>
                        <h3 class="font-medium">View Logs</h3>
                        <p class="text-sm text-gray-600">See audit and trigger logs</p>
                    </div>
                </div>
            </a>
            
            <?php if ($isAdmin): ?>
            <a href="/api-keys" class="block bg-yellow-50 hover:bg-yellow-100 p-4 rounded-lg transition-colors">
                <div class="flex items-center">
                    <div class="rounded-full bg-yellow-100 p-2 mr-3">
                        <i class="fas fa-key text-yellow-600"></i>
                    </div>
                    <div>
                        <h3 class="font-medium">Manage API Keys</h3>
                        <p class="text-sm text-gray-600">For CRM integration</p>
                    </div>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>
