<div class="bg-white p-6 rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Audit Logs</h2>
    </div>
    
    <!-- Filter Form -->
    <?php
    // Helper to build pagination/filter URLs using non-pretty route
    if (!function_exists('gc_build_url')) {
        function gc_build_url($baseUrl, $filters, $overrides = []) {
            $params = array_merge($filters, $overrides);
            // Ensure per_page persists (default 100)
            if (!isset($params['per_page']) || (int)$params['per_page'] <= 0) {
                $params['per_page'] = 100;
            }
            // Always include the route
            $query = array_merge(['route' => 'logs'], $params);
            return rtrim($baseUrl, '/').'/index.php?'.http_build_query($query);
        }
    }
    ?>
    <div class="mb-6 bg-gray-50 p-4 rounded-lg" x-data="{ showFilters: false }">
        <div class="flex justify-between items-center cursor-pointer" @click="showFilters = !showFilters">
            <h3 class="font-semibold text-lg">
                <i class="fas fa-filter mr-2"></i> Filters
                <?php if (!empty($activeFilters)): ?>
                <span class="bg-blue-100 text-blue-800 text-xs font-semibold ml-2 px-2.5 py-0.5 rounded">
                    <?= count($activeFilters) ?> active
                </span>
                <?php endif; ?>
            </h3>
            <button>
                <i class="fas" :class="showFilters ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
            </button>
        </div>
        
        <div x-show="showFilters" class="mt-4">
            <form method="get" action="<?= rtrim($baseUrl, '/') ?>/index.php" class="space-y-4" id="logsFilterForm">
                <input type="hidden" name="route" value="logs">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="action_type" class="block text-gray-700 text-sm font-medium mb-1">Action Type</label>
                        <select id="action_type" name="action_type" class="form-select w-full">
                            <option value="">All Actions</option>
                            <?php foreach ($actionTypes as $value => $label): ?>
                                <option value="<?= $value ?>" <?= isset($filters['action_type']) && $filters['action_type'] === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="from_date" class="block text-gray-700 text-sm font-medium mb-1">From Date</label>
                        <input type="date" id="from_date" name="from_date" class="form-input w-full" 
                               value="<?= htmlspecialchars($filters['from_date'] ?? '') ?>">
                    </div>
                    
                    <div>
                        <label for="to_date" class="block text-gray-700 text-sm font-medium mb-1">To Date</label>
                        <input type="date" id="to_date" name="to_date" class="form-input w-full" 
                               value="<?= htmlspecialchars($filters['to_date'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="search" class="block text-gray-700 text-sm font-medium mb-1">Search</label>
                        <input type="text" id="search" name="search" class="form-input w-full" 
                               placeholder="Search in details or user agent" 
                               value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                    </div>
                    
                    <div>
                        <label for="ip_address" class="block text-gray-700 text-sm font-medium mb-1">IP Address</label>
                        <input type="text" id="ip_address" name="ip_address" class="form-input w-full" 
                               placeholder="Filter by IP address" 
                               value="<?= htmlspecialchars($filters['ip_address'] ?? '') ?>">
                    </div>
                    
                    <?php if ($isAdmin): ?>
                    <div>
                        <label for="user_id" class="block text-gray-700 text-sm font-medium mb-1">User</label>
                        <select id="user_id" name="user_id" class="form-select w-full">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= isset($filters['user_id']) && (int)$filters['user_id'] === (int)$user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <input type="hidden" name="per_page" value="<?= (int)($filters['per_page'] ?? 100) ?>">
                    <a href="<?= rtrim($baseUrl, '/') ?>/index.php?route=logs" class="btn-secondary">Clear Filters</a>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search mr-1"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Logs Table -->
    <?php if (empty($logs)): ?>
        <div class="bg-gray-100 p-6 text-center rounded">
            <i class="fas fa-info-circle text-gray-500 text-3xl mb-2"></i>
            <p class="text-gray-500">No logs found matching your criteria.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($logs as $log): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php 
                                    $actionClass = 'bg-blue-100 text-blue-800';
                                    
                                    if (strpos($log['action_type'], 'CREATE') !== false) {
                                        $actionClass = 'bg-green-100 text-green-800';
                                    } elseif (strpos($log['action_type'], 'DELETE') !== false) {
                                        $actionClass = 'bg-red-100 text-red-800';
                                    } elseif (strpos($log['action_type'], 'UPDATE') !== false) {
                                        $actionClass = 'bg-yellow-100 text-yellow-800';
                                    } elseif (strpos($log['action_type'], 'TRIGGER') !== false) {
                                        $actionClass = 'bg-purple-100 text-purple-800';
                                    } elseif (strpos($log['action_type'], 'LOGIN') !== false) {
                                        $actionClass = 'bg-indigo-100 text-indigo-800';
                                    }
                                ?>
                                
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $actionClass ?>">
                                    <?= htmlspecialchars($log['action_type']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if ($log['user_id']): ?>
                                    <span title="User">
                                        <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($log['username'] ?? 'Unknown') ?>
                                    </span>
                                <?php elseif ($log['api_key_id']): ?>
                                    <span title="API Key">
                                        <i class="fas fa-key mr-1"></i> <?= htmlspecialchars($log['api_key_name'] ?? 'Unknown') ?>
                                    </span>
                                <?php else: ?>
                                    <span title="System">
                                        <i class="fas fa-cog mr-1"></i> System
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($log['ip_address']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php if (!empty($log['details'])): ?>
                                <div x-data="{ open: false }">
                                    <button @click="open = !open" class="flex items-center text-blue-600 hover:text-blue-800">
                                        <span>View Details</span>
                                        <i class="fas ml-1" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    </button>
                                    
                                    <div x-show="open" class="mt-2 bg-gray-50 p-3 rounded text-xs overflow-auto max-h-32">
                                        <pre class="whitespace-pre-wrap"><?= json_encode($log['details'], JSON_PRETTY_PRINT) ?></pre>
                                    </div>
                                </div>
                                <?php else: ?>
                                <span class="text-gray-400">No details</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($pagination['last_page'] > 1): ?>
        <div class="flex justify-between items-center mt-6">
            <div class="text-sm text-gray-500">
                Showing <?= ($pagination['current_page'] - 1) * $pagination['per_page'] + 1 ?> 
                to <?= min($pagination['current_page'] * $pagination['per_page'], $pagination['total']) ?> 
                of <?= $pagination['total'] ?> results
            </div>
            
            <div class="flex space-x-1">
                <?php if ($pagination['current_page'] > 1): ?>
                    <a href="<?= gc_build_url($baseUrl, $filters, ['page' => $pagination['current_page'] - 1]) ?>" class="btn-sm btn-secondary">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php 
                    $startPage = max(1, $pagination['current_page'] - 2);
                    $endPage = min($pagination['last_page'], $startPage + 4);
                    
                    if ($endPage - $startPage < 4) {
                        $startPage = max(1, $endPage - 4);
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++): 
                ?>
                    <a href="<?= gc_build_url($baseUrl, $filters, ['page' => $i]) ?>" 
                       class="btn-sm <?= $i === $pagination['current_page'] ? 'btn-primary' : 'btn-secondary' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($pagination['current_page'] < $pagination['last_page']): ?>
                    <a href="<?= gc_build_url($baseUrl, $filters, ['page' => $pagination['current_page'] + 1]) ?>" class="btn-sm btn-secondary">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Alpine.js is loaded in the main layout, no need to initialize it here
    
    // Date filter auto-adjustment
    const fromDateInput = document.getElementById('from_date');
    const toDateInput = document.getElementById('to_date');
    
    if (fromDateInput && toDateInput) {
        fromDateInput.addEventListener('change', function() {
            if (toDateInput.value && this.value > toDateInput.value) {
                toDateInput.value = this.value;
            }
        });
        
        toDateInput.addEventListener('change', function() {
            if (fromDateInput.value && this.value < fromDateInput.value) {
                fromDateInput.value = this.value;
            }
        });
    }
});
</script>
