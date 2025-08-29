<?php
// src/views/auth/profile.php
// Expects $currentUser from index router context
$baseUrl = $config['app']['base_url'] ?? '';
?>
<div class="max-w-xl mx-auto bg-white shadow rounded p-6">
  <h2 class="text-xl font-semibold text-gray-800 mb-4">Your Profile</h2>

  <div class="mb-6">
    <div class="text-sm text-gray-600">Username</div>
    <div class="text-gray-900 font-medium"><?= htmlspecialchars($currentUser['username']) ?></div>
  </div>

  <h3 class="text-lg font-semibold text-gray-800 mb-2">Change Password</h3>
  <form method="post" action="<?= $baseUrl ?>/profile" class="space-y-4">
    <div>
      <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
      <input type="password" id="current_password" name="current_password" required
             class="mt-1 block w-full rounded bg-gray-100 border border-gray-500 focus:border-blue-500 focus:ring-blue-500" />
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
        <input type="password" id="new_password" name="new_password" required
               minlength="<?= htmlspecialchars($config['security']['password_min_length'] ?? 8) ?>"
               class="mt-1 block w-full rounded bg-gray-100 border border-gray-500 focus:border-blue-500 focus:ring-blue-500" />
      </div>
      <div>
        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required
               class="mt-1 block w-full rounded bg-gray-100 border border-gray-500 focus:border-blue-500 focus:ring-blue-500" />
      </div>
    </div>
    <div class="flex items-center justify-end space-x-2 pt-4">
      <a href="<?= $baseUrl ?>/" class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300 text-gray-800">Back</a>
      <button type="submit" class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white">Update Password</button>
    </div>
  </form>
</div>
