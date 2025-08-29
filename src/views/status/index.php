<?php
// src/views/status/index.php
// Assumes $devices array and $currentPage set by index.php
?>
<div class="bg-white rounded shadow p-4">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-xl font-semibold">Live Device Status</h2>
    <div class="text-sm text-gray-600">Auto-refreshes every 30s</div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full border border-gray-200 divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Host</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Online</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Today (Web/API)</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month (Web/API)</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year (Web/API)</th>
        </tr>
      </thead>
      <tbody id="status-tbody" class="bg-white divide-y divide-gray-200">
        <?php foreach ($devices as $d): ?>
        <tr data-device-id="<?= htmlspecialchars($d['id']) ?>">
          <td class="px-4 py-2">
            <div class="font-medium text-gray-900"><?= htmlspecialchars($d['name']) ?></div>
          </td>
          <td class="px-4 py-2 text-gray-700">
            <?= htmlspecialchars($d['host']) ?>
          </td>
          <td class="px-4 py-2">
            <span class="inline-flex items-center space-x-2">
              <span class="w-2.5 h-2.5 rounded-full bg-gray-300" data-role="status-dot"></span>
              <span class="text-sm text-gray-600" data-role="status-text">Checking...</span>
            </span>
          </td>
          <td class="px-4 py-2 text-sm text-gray-800">
            <div>
              <span class="font-medium">Total:</span>
              <span data-role="day-total">0</span>
              <span class="text-gray-500">(</span>
              <span data-role="day-total-web">0</span>
              <span class="text-gray-500">/</span>
              <span data-role="day-total-api">0</span>
              <span class="text-gray-500">)</span>
            </div>
            <div>
              <span class="font-medium">Success:</span>
              <span data-role="day-success" class="text-green-600 font-semibold">0</span>
              <span class="text-gray-500">(</span>
              <span data-role="day-success-web" class="text-green-600">0</span>
              <span class="text-gray-500">/</span>
              <span data-role="day-success-api" class="text-green-600">0</span>
              <span class="text-gray-500">)</span>
            </div>
            <div>
              <span class="font-medium">Failed:</span>
              <span data-role="day-fail" class="text-red-600 font-semibold">0</span>
              <span class="text-gray-500">(</span>
              <span data-role="day-fail-web" class="text-red-600">0</span>
              <span class="text-gray-500">/</span>
              <span data-role="day-fail-api" class="text-red-600">0</span>
              <span class="text-gray-500">)</span>
            </div>
          </td>
          <td class="px-4 py-2 text-sm text-gray-800">
            <div>
              <span class="font-medium">Total:</span>
              <span data-role="month-total">0</span>
              <span class="text-gray-500">(</span>
              <span data-role="month-total-web">0</span>
              <span class="text-gray-500">/</span>
              <span data-role="month-total-api">0</span>
              <span class="text-gray-500">)</span>
            </div>
            <div>
              <span class="font-medium">Success:</span>
              <span data-role="month-success" class="text-green-600 font-semibold">0</span>
              <span class="text-gray-500">(</span>
              <span data-role="month-success-web" class="text-green-600">0</span>
              <span class="text-gray-500">/</span>
              <span data-role="month-success-api" class="text-green-600">0</span>
              <span class="text-gray-500">)</span>
            </div>
            <div>
              <span class="font-medium">Failed:</span>
              <span data-role="month-fail" class="text-red-600 font-semibold">0</span>
              <span class="text-gray-500">(</span>
              <span data-role="month-fail-web" class="text-red-600">0</span>
              <span class="text-gray-500">/</span>
              <span data-role="month-fail-api" class="text-red-600">0</span>
              <span class="text-gray-500">)</span>
            </div>
          </td>
          <td class="px-4 py-2 text-sm text-gray-800">
            <div>
              <span class="font-medium">Total:</span>
              <span data-role="year-total">0</span>
              <span class="text-gray-500">(</span>
              <span data-role="year-total-web">0</span>
              <span class="text-gray-500">/</span>
              <span data-role="year-total-api">0</span>
              <span class="text-gray-500">)</span>
            </div>
            <div>
              <span class="font-medium">Success:</span>
              <span data-role="year-success" class="text-green-600 font-semibold">0</span>
              <span class="text-gray-500">(</span>
              <span data-role="year-success-web" class="text-green-600">0</span>
              <span class="text-gray-500">/</span>
              <span data-role="year-success-api" class="text-green-600">0</span>
              <span class="text-gray-500">)</span>
            </div>
            <div>
              <span class="font-medium">Failed:</span>
              <span data-role="year-fail" class="text-red-600 font-semibold">0</span>
              <span class="text-gray-500">(</span>
              <span data-role="year-fail-web" class="text-red-600">0</span>
              <span class="text-gray-500">/</span>
              <span data-role="year-fail-api" class="text-red-600">0</span>
              <span class="text-gray-500">)</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function(){
  const tbody = document.getElementById('status-tbody');

  async function pingDevice(deviceId, row){
    // Hard timeout per device so one slow/offline device doesn't block others
    const controller = new AbortController();
    const TIMEOUT_MS = 4000; // 4s cap
    const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
    try {
      const params = new URLSearchParams({ action: 'test_device_connection', device_id: deviceId, _: Date.now() });
      const res = await fetch('/ajax.php?' + params.toString(), { credentials: 'same-origin', signal: controller.signal });
      clearTimeout(timer);
      const data = await res.json();
      const ok = data && data.success;
      updateStatusRow(row, ok, data?.data?.message || (ok ? 'Online' : 'Offline'));
    } catch(e){
      clearTimeout(timer);
      const timedOut = e && e.name === 'AbortError';
      updateStatusRow(row, false, timedOut ? 'Timed out' : 'Offline');
    }
  }

  function updateStatusRow(row, isOnline, text){
    const dot = row.querySelector('[data-role="status-dot"]');
    const txt = row.querySelector('[data-role="status-text"]');
    if(!dot || !txt) return;
    dot.classList.remove('bg-gray-300','bg-red-500','bg-green-500');
    dot.classList.add(isOnline ? 'bg-green-500' : 'bg-red-500');
    txt.textContent = text || (isOnline ? 'Online' : 'Offline');
  }

  async function fetchStats(){
    try {
      const params = new URLSearchParams({ action: 'get_trigger_stats' });
      const res = await fetch('/ajax.php?' + params.toString(), { credentials: 'same-origin' });
      const payload = await res.json();
      if (!payload || !payload.success || !payload.stats) return;
      const stats = payload.stats || {};
      [...tbody.querySelectorAll('tr[data-device-id]')].forEach(row => {
        const id = row.getAttribute('data-device-id');
        const s = stats[id];
        if (!s) return;
        setRowCounts(row, 'day', s.day);
        setRowCounts(row, 'month', s.month);
        setRowCounts(row, 'year', s.year);
      });
    } catch(e){ /* ignore */ }
  }

  function setRowCounts(row, period, values){
    if(!values) return;
    // ensure structure exists; rebuild cell if not present
    let totalEl = row.querySelector(`[data-role="${period}-total"]`);
    let successEl = row.querySelector(`[data-role="${period}-success"]`);
    let failEl = row.querySelector(`[data-role="${period}-fail"]`);
    if(!successEl || !failEl){
      const colIndex = { day: 3, month: 4, year: 5 }[period] ?? 3;
      const cell = row.children[colIndex];
      if(cell){
        cell.innerHTML = `
          <div>
            <span class="font-medium">Total:</span>
            <span data-role="${period}-total">0</span>
            <span class="text-gray-500">(</span>
            <span data-role="${period}-total-web">0</span>
            <span class="text-gray-500">/</span>
            <span data-role="${period}-total-api">0</span>
            <span class="text-gray-500">)</span>
          </div>
          <div>
            <span class="font-medium">Success:</span>
            <span data-role="${period}-success" class="text-green-600 font-semibold">0</span>
            <span class="text-gray-500">(</span>
            <span data-role="${period}-success-web" class="text-green-600">0</span>
            <span class="text-gray-500">/</span>
            <span data-role="${period}-success-api" class="text-green-600">0</span>
            <span class="text-gray-500">)</span>
          </div>
          <div>
            <span class="font-medium">Failed:</span>
            <span data-role="${period}-fail" class="text-red-600 font-semibold">0</span>
            <span class="text-gray-500">(</span>
            <span data-role="${period}-fail-web" class="text-red-600">0</span>
            <span class="text-gray-500">/</span>
            <span data-role="${period}-fail-api" class="text-red-600">0</span>
            <span class="text-gray-500">)</span>
          </div>`;
      }
      totalEl = row.querySelector(`[data-role="${period}-total"]`);
      successEl = row.querySelector(`[data-role="${period}-success"]`);
      failEl = row.querySelector(`[data-role="${period}-fail"]`);
    }

    const totalVal = values.total ?? 0;
    const failVal = values.fail ?? 0;
    const successVal = values.success ?? Math.max(0, totalVal - failVal);

    if(totalEl) totalEl.textContent = totalVal;
    const totalWeb = row.querySelector(`[data-role="${period}-total-web"]`);
    const totalApi = row.querySelector(`[data-role="${period}-total-api"]`);
    if(totalWeb) totalWeb.textContent = values.web ?? 0;
    if(totalApi) totalApi.textContent = values.api ?? 0;

    if(successEl) successEl.textContent = successVal;
    const successWeb = row.querySelector(`[data-role="${period}-success-web"]`);
    const successApi = row.querySelector(`[data-role="${period}-success-api"]`);
    if(successWeb) successWeb.textContent = values.success_web ?? Math.max(0, (values.web ?? 0) - (values.fail_web ?? 0));
    if(successApi) successApi.textContent = values.success_api ?? Math.max(0, (values.api ?? 0) - (values.fail_api ?? 0));

    if(failEl) failEl.textContent = failVal;
    const failWeb = row.querySelector(`[data-role="${period}-fail-web"]`);
    const failApi = row.querySelector(`[data-role="${period}-fail-api"]`);
    if(failWeb) failWeb.textContent = values.fail_web ?? 0;
    if(failApi) failApi.textContent = values.fail_api ?? 0;
  }

  async function refreshDevices(){
    try {
      const params = new URLSearchParams({ action: 'get_devices' });
      const res = await fetch('/ajax.php?' + params.toString(), { credentials: 'same-origin' });
      const payload = await res.json();
      if(!payload || !payload.success) return;
      const list = payload.data?.devices || [];
      const existing = new Set([...tbody.querySelectorAll('tr[data-device-id]')].map(r => r.getAttribute('data-device-id')));
      // Add new devices
      list.forEach(d => {
        if (!existing.has(String(d.id))) {
          const tr = document.createElement('tr');
          tr.setAttribute('data-device-id', String(d.id));
          tr.innerHTML = `
            <td class="px-4 py-2"><div class="font-medium text-gray-900">${escapeHtml(d.name)}</div></td>
            <td class="px-4 py-2 text-gray-700">${escapeHtml(d.host)}</td>
            <td class="px-4 py-2">
              <span class="inline-flex items-center space-x-2">
                <span class="w-2.5 h-2.5 rounded-full bg-gray-300" data-role="status-dot"></span>
                <span class="text-sm text-gray-600" data-role="status-text">Checking...</span>
              </span>
            </td>
            <td class="px-4 py-2 text-sm text-gray-800">
              <div>
                <span class="font-medium">Total:</span>
                <span data-role="day-total">0</span>
                <span class="text-gray-500">(</span>
                <span data-role="day-total-web">0</span>
                <span class="text-gray-500">/</span>
                <span data-role="day-total-api">0</span>
                <span class="text-gray-500">)</span>
              </div>
              <div>
                <span class="font-medium">Success:</span>
                <span data-role="day-success" class="text-green-600 font-semibold">0</span>
                <span class="text-gray-500">(</span>
                <span data-role="day-success-web" class="text-green-600">0</span>
                <span class="text-gray-500">/</span>
                <span data-role="day-success-api" class="text-green-600">0</span>
                <span class="text-gray-500">)</span>
              </div>
              <div>
                <span class="font-medium">Failed:</span>
                <span data-role="day-fail" class="text-red-600 font-semibold">0</span>
                <span class="text-gray-500">(</span>
                <span data-role="day-fail-web" class="text-red-600">0</span>
                <span class="text-gray-500">/</span>
                <span data-role="day-fail-api" class="text-red-600">0</span>
                <span class="text-gray-500">)</span>
              </div>
            </td>
            <td class="px-4 py-2 text-sm text-gray-800">
              <div>
                <span class="font-medium">Total:</span>
                <span data-role="month-total">0</span>
                <span class="text-gray-500">(</span>
                <span data-role="month-total-web">0</span>
                <span class="text-gray-500">/</span>
                <span data-role="month-total-api">0</span>
                <span class="text-gray-500">)</span>
              </div>
              <div>
                <span class="font-medium">Success:</span>
                <span data-role="month-success" class="text-green-600 font-semibold">0</span>
                <span class="text-gray-500">(</span>
                <span data-role="month-success-web" class="text-green-600">0</span>
                <span class="text-gray-500">/</span>
                <span data-role="month-success-api" class="text-green-600">0</span>
                <span class="text-gray-500">)</span>
              </div>
              <div>
                <span class="font-medium">Failed:</span>
                <span data-role="month-fail" class="text-red-600 font-semibold">0</span>
                <span class="text-gray-500">(</span>
                <span data-role="month-fail-web" class="text-red-600">0</span>
                <span class="text-gray-500">/</span>
                <span data-role="month-fail-api" class="text-red-600">0</span>
                <span class="text-gray-500">)</span>
              </div>
            </td>
            <td class="px-4 py-2 text-sm text-gray-800">
              <div>
                <span class="font-medium">Total:</span>
                <span data-role="year-total">0</span>
                <span class="text-gray-500">(</span>
                <span data-role="year-total-web">0</span>
                <span class="text-gray-500">/</span>
                <span data-role="year-total-api">0</span>
                <span class="text-gray-500">)</span>
              </div>
              <div>
                <span class="font-medium">Success:</span>
                <span data-role="year-success" class="text-green-600 font-semibold">0</span>
                <span class="text-gray-500">(</span>
                <span data-role="year-success-web" class="text-green-600">0</span>
                <span class="text-gray-500">/</span>
                <span data-role="year-success-api" class="text-green-600">0</span>
                <span class="text-gray-500">)</span>
              </div>
              <div>
                <span class="font-medium">Failed:</span>
                <span data-role="year-fail" class="text-red-600 font-semibold">0</span>
                <span class="text-gray-500">(</span>
                <span data-role="year-fail-web" class="text-red-600">0</span>
                <span class="text-gray-500">/</span>
                <span data-role="year-fail-api" class="text-red-600">0</span>
                <span class="text-gray-500">)</span>
              </div>
            </td>`;
          tbody.appendChild(tr);
          pingDevice(String(d.id), tr);
        }
      });
    } catch(e){ /* ignore */ }
  }

  function escapeHtml(text){
    const span = document.createElement('span');
    span.textContent = String(text ?? '');
    return span.innerHTML;
  }

  function pingAll(){
    // Concurrency pool so one slow device doesn't starve the rest
    const rows = [...tbody.querySelectorAll('tr[data-device-id]')];
    const maxConcurrent = 6;
    let index = 0;
    function worker(){
      if (index >= rows.length) return Promise.resolve();
      const row = rows[index++];
      const id = row.getAttribute('data-device-id');
      return pingDevice(id, row).finally(worker);
    }
    // Kick off up to maxConcurrent workers
    const starters = Array.from({length: Math.min(maxConcurrent, rows.length)}, () => worker());
    Promise.allSettled(starters);
  }

  // Initial actions
  pingAll();
  fetchStats();

  // Periodic refresh
  setInterval(pingAll, 30000); // 30s ping
  setInterval(fetchStats, 30000); // 30s stats
  setInterval(refreshDevices, 30000); // check for new devices every 30s
})();
</script>
