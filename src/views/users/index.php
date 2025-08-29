<?php
// src/views/users/index.php
// Expects: $users (array)
?>
<?php $baseUrl = $config['app']['base_url'] ?? ''; ?>
<div class="bg-white shadow rounded p-6">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-xl font-semibold text-gray-800">Users</h2>
    <a href="<?= $baseUrl ?>/users/create" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700 text-white">
      <i class="fas fa-user-plus mr-2"></i> Add User
    </a>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Active</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
          <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <?php if (empty($users)): ?>
          <tr>
            <td colspan="6" class="px-4 py-6 text-center text-gray-500">No users found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($users as $u): ?>
            <tr>
              <td class="px-4 py-2 text-sm text-gray-900"><?= htmlspecialchars($u['username']) ?></td>
              <td class="px-4 py-2 text-sm text-gray-900"><?= htmlspecialchars($u['email'] ?? '') ?></td>
              <td class="px-4 py-2 text-sm text-gray-900">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium <?= $u['is_admin'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                  <?= $u['is_admin'] ? 'Yes' : 'No' ?>
                </span>
              </td>
              <td class="px-4 py-2 text-sm text-gray-900">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium <?= $u['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                  <?= $u['active'] ? 'Active' : 'Disabled' ?>
                </span>
              </td>
              <td class="px-4 py-2 text-sm text-gray-900"><?= htmlspecialchars($u['created_at']) ?></td>
              <td class="px-4 py-2 text-sm text-gray-900"><?= htmlspecialchars($u['last_login'] ?? '') ?></td>
              <td class="px-4 py-2 text-sm text-gray-900 text-right space-x-2">
                <?php if (!empty($currentUser) && (int)$currentUser['id'] === (int)$u['id']): ?>
                  <a href="<?= $baseUrl ?>/profile" class="inline-flex items-center px-3 py-1.5 rounded bg-indigo-600 hover:bg-indigo-700 text-white">
                    <i class="fas fa-key mr-1"></i> Change Password
                  </a>
                  <span class="text-gray-400" title="You cannot delete your own account">—</span>
                <?php else: ?>
                  <a href="<?= $baseUrl ?>/users/password?id=<?= urlencode($u['id']) ?>"
                     class="inline-flex items-center px-3 py-1.5 rounded bg-indigo-600 hover:bg-indigo-700 text-white">
                    <i class="fas fa-key mr-1"></i> Password
                  </a>
                  <a href="<?= $baseUrl ?>/users/delete?id=<?= urlencode($u['id']) ?>"
                     class="inline-flex items-center px-3 py-1.5 rounded bg-red-600 hover:bg-red-700 text-white"
                     onclick="return confirm('Delete user <?= htmlspecialchars($u['username'], ENT_QUOTES) ?>? This cannot be undone.');">
                    <i class="fas fa-trash mr-1"></i> Delete
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
