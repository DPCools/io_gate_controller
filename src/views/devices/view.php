<?php
// src/views/devices/view.php
// Expects: $device (array), optional $createdBy, $updatedBy, $pageTitle
?>
<div class="max-w-3xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold text-gray-800"><?= htmlspecialchars($pageTitle ?? 'View Device') ?></h1>
    <div class="space-x-2">
      <a href="/devices" class="inline-flex items-center px-3 py-1.5 rounded bg-gray-200 hover:bg-gray-300 text-gray-800">Back to Devices</a>
      <a href="/devices/edit?id=<?= urlencode($device['id']) ?>" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700 text-white">Edit</a>
    </div>
  </div>

  <div class="bg-white shadow rounded p-6">
    <dl class="divide-y divide-gray-200">
      <div class="py-3 grid grid-cols-3 gap-4">
        <dt class="text-sm font-medium text-gray-500">ID</dt>
        <dd class="mt-1 text-sm text-gray-900 col-span-2"><?= htmlspecialchars($device['id']) ?></dd>
      </div>
      <div class="py-3 grid grid-cols-3 gap-4">
        <dt class="text-sm font-medium text-gray-500">Name</dt>
        <dd class="mt-1 text-sm text-gray-900 col-span-2"><?= htmlspecialchars($device['name']) ?></dd>
      </div>
      <div class="py-3 grid grid-cols-3 gap-4">
        <dt class="text-sm font-medium text-gray-500">Host</dt>
        <dd class="mt-1 text-sm text-gray-900 col-span-2"><?= htmlspecialchars($device['host']) ?></dd>
      </div>
      <div class="py-3 grid grid-cols-3 gap-4">
        <dt class="text-sm font-medium text-gray-500">Port</dt>
        <dd class="mt-1 text-sm text-gray-900 col-span-2"><?= htmlspecialchars($device['port']) ?></dd>
      </div>
      <div class="py-3 grid grid-cols-3 gap-4">
        <dt class="text-sm font-medium text-gray-500">Scheme</dt>
        <dd class="mt-1 text-sm text-gray-900 col-span-2"><?= htmlspecialchars($device['scheme']) ?></dd>
      </div>
      <div class="py-3 grid grid-cols-3 gap-4">
        <dt class="text-sm font-medium text-gray-500">Username</dt>
        <dd class="mt-1 text-sm text-gray-900 col-span-2"><?= htmlspecialchars($device['username']) ?></dd>
      </div>
      <div class="py-3 grid grid-cols-3 gap-4">
        <dt class="text-sm font-medium text-gray-500">Auth</dt>
        <dd class="mt-1 text-sm text-gray-900 col-span-2"><?= htmlspecialchars($device['auth']) ?></dd>
      </div>
      <div class="py-3 grid grid-cols-3 gap-4">
        <dt class="text-sm font-medium text-gray-500">Base Path</dt>
        <dd class="mt-1 text-sm text-gray-900 col-span-2"><?= htmlspecialchars($device['base_path']) ?></dd>
      </div>
      <div class="py-3 grid grid-cols-3 gap-4">
        <dt class="text-sm font-medium text-gray-500">Insecure SSL</dt>
        <dd class="mt-1 text-sm text-gray-900 col-span-2"><?= !empty($device['insecure']) ? 'Yes' : 'No' ?></dd>
      </div>
      <div class="py-3 grid grid-cols-3 gap-4">
        <dt class="text-sm font-medium text-gray-500">TLS v1.2</dt>
        <dd class="mt-1 text-sm text-gray-900 col-span-2"><?= !empty($device['tlsv1_2']) ? 'Yes' : 'No' ?></dd>
      </div>
      <?php if (!empty($device['description'])): ?>
      <div class="py-3 grid grid-cols-3 gap-4">
        <dt class="text-sm font-medium text-gray-500">Description</dt>
        <dd class="mt-1 text-sm text-gray-900 col-span-2"><?= nl2br(htmlspecialchars($device['description'])) ?></dd>
      </div>
      <?php endif; ?>
      <div class="py-3 grid grid-cols-3 gap-4">
        <dt class="text-sm font-medium text-gray-500">Created</dt>
        <dd class="mt-1 text-sm text-gray-900 col-span-2">
          <?= htmlspecialchars($device['created_at'] ?? '') ?>
          <?php if (!empty($createdBy)): ?>
            by <?= htmlspecialchars($createdBy['username']) ?>
          <?php endif; ?>
        </dd>
      </div>
      <div class="py-3 grid grid-cols-3 gap-4">
        <dt class="text-sm font-medium text-gray-500">Updated</dt>
        <dd class="mt-1 text-sm text-gray-900 col-span-2">
          <?= htmlspecialchars($device['updated_at'] ?? '') ?>
          <?php if (!empty($updatedBy)): ?>
            by <?= htmlspecialchars($updatedBy['username']) ?>
          <?php endif; ?>
        </dd>
      </div>
    </dl>
  </div>
</div>
