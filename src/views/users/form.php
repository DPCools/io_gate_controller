<?php
// src/views/users/form.php
?>
<?php $baseUrl = $config['app']['base_url'] ?? ''; ?>
<div class="max-w-xl mx-auto bg-white shadow rounded p-6">
  <form method="post" action="<?= $baseUrl ?>/users/create" class="space-y-4">
    <div>
      <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
      <input type="text" id="username" name="username" required
             value="<?= htmlspecialchars($user['username'] ?? '') ?>"
             class="mt-1 block w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
    </div>
    <div>
      <label for="email" class="block text-sm font-medium text-gray-700">Email (optional)</label>
      <input type="email" id="email" name="email"
             value="<?= htmlspecialchars($user['email'] ?? '') ?>"
             class="mt-1 block w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
        <input type="password" id="password" name="password" required minlength="<?= htmlspecialchars($config['security']['password_min_length'] ?? 8) ?>"
               class="mt-1 block w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
      </div>
      <div>
        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required
               class="mt-1 block w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
      </div>
    </div>
    <div class="flex items-center">
      <input type="checkbox" id="is_admin" name="is_admin" class="h-4 w-4 text-blue-600 border-gray-300 rounded"
             <?= !empty($user['is_admin']) ? 'checked' : '' ?> />
      <label for="is_admin" class="ml-2 block text-sm text-gray-700">Administrator</label>
    </div>

    <div class="flex items-center justify-end space-x-2 pt-4">
      <a href="<?= $baseUrl ?>/users" class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300 text-gray-800">Cancel</a>
      <button type="submit" class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white">Create User</button>
    </div>
  </form>
</div>
