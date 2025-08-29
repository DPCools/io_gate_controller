<?php
// src/views/users/password.php
// Expects $targetUserData = ['id'=>..., 'username'=>...]
$baseUrl = $config['app']['base_url'] ?? '';
?>
<div class="max-w-xl mx-auto bg-white shadow rounded p-6">
  <h2 class="text-xl font-semibold text-gray-800 mb-4">Reset Password</h2>
  <p class="text-sm text-gray-700 mb-4">User: <span class="font-medium"><?= htmlspecialchars($targetUserData['username']) ?></span></p>
  <form method="post" action="<?= $baseUrl ?>/users/password?id=<?= urlencode($targetUserData['id']) ?>" class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
        <input type="password" id="new_password" name="new_password" required
               minlength="<?= htmlspecialchars($config['security']['password_min_length'] ?? 8) ?>"
               class="mt-1 block w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
      </div>
      <div>
        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required
               class="mt-1 block w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
      </div>
    </div>
    <div class="flex items-center justify-end space-x-2 pt-4">
      <a href="<?= $baseUrl ?>/users" class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300 text-gray-800">Cancel</a>
      <button type="submit" class="px-4 py-2 rounded bg-indigo-600 hover:bg-indigo-700 text-white">Reset Password</button>
    </div>
  </form>
</div>
