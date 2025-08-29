<?php
/**
 * Help / Documentation
 */
?>
<div class="max-w-5xl mx-auto">
  <h1 class="text-2xl font-bold mb-6">Help & Documentation</h1>

  <div class="space-y-8">
    <section>
      <h2 class="text-xl font-semibold mb-2">Tabs Overview</h2>
      <div class="space-y-3 text-gray-800">
        <div>
          <span class="font-semibold">Dashboard</span> – Quick stats and recent gate activity. Shortcuts to Logs and management pages.
        </div>
        <div>
          <span class="font-semibold">Devices</span> – List, create, edit, delete devices. Test live connectivity with auth-aware checks. Online status auto-refreshes.
        </div>
        <div>
          <span class="font-semibold">Gates</span> – Create and manage gates and their device mappings. Trigger or test an individual gate.
        </div>
        <div>
          <span class="font-semibold">Status</span> – All devices status view: name, daily/monthly/yearly trigger counts, CRM vs web source, and live ping on load.
        </div>
        <div>
          <span class="font-semibold">Logs</span> – Audit log with filters (type, user, date range, search, IP). Pagination with up to 100 per page.
        </div>
        <div>
          <span class="font-semibold">API Keys</span> – Create/manage keys for external access. Copy the key on creation; revoke keys when no longer needed.
        </div>
      </div>
    </section>

    <section>
      <h2 class="text-xl font-semibold mb-2">API Usage</h2>
      <p class="text-gray-800 mb-4">Base path: <code class="bg-gray-100 px-1 py-0.5 rounded">/api.php</code> (or <code class="bg-gray-100 px-1 py-0.5 rounded">/api</code> if your server rewrites to api.php). Authenticate with one of:</p>
      <ul class="list-disc ml-5 text-gray-800 mb-4">
        <li><span class="font-semibold">Authorization</span> header: <code>Authorization: Bearer YOUR_API_KEY</code></li>
        <li><span class="font-semibold">X-API-Key</span> header: <code>X-API-Key: YOUR_API_KEY</code></li>
        <li><span class="font-semibold">Query param</span>: <code>?api_key=YOUR_API_KEY</code></li>
      </ul>

      <div class="space-y-6">
        <div>
          <h3 class="font-semibold">Health</h3>
          <div class="text-sm text-gray-700">GET <code class="bg-gray-100 px-1 py-0.5 rounded">/api.php/health</code></div>
          <pre class="bg-gray-900 text-gray-100 p-3 rounded mt-2 text-sm overflow-auto"><code># Add -L to follow redirects if your server issues 302s
curl -sL http://localhost:82/api.php/health | jq

# Fallback form without path segment
curl -sL "http://localhost:82/api.php?endpoint=health" | jq</code></pre>
        </div>

        <div>
          <h3 class="font-semibold">List Gates</h3>
          <div class="text-sm text-gray-700">GET <code class="bg-gray-100 px-1 py-0.5 rounded">/api.php/gates</code></div>
          <pre class="bg-gray-900 text-gray-100 p-3 rounded mt-2 text-sm overflow-auto"><code># Using Bearer token
curl -sL -H "Authorization: Bearer YOUR_API_KEY" \
  http://localhost:82/api.php/gates | jq

# Using X-API-Key header
curl -sL -H "X-API-Key: YOUR_API_KEY" \
  http://localhost:82/api.php/gates | jq

# Using query parameter
curl -sL "http://localhost:82/api.php/gates?api_key=YOUR_API_KEY" | jq</code></pre>
        </div>

        <div>
          <h3 class="font-semibold">Trigger Gate</h3>
          <div class="text-sm text-gray-700">POST <code class="bg-gray-100 px-1 py-0.5 rounded">/api.php/trigger/{gateName}</code> or POST <code class="bg-gray-100 px-1 py-0.5 rounded">/api.php/trigger</code> with JSON body</div>
          <pre class="bg-gray-900 text-gray-100 p-3 rounded mt-2 text-sm overflow-auto"><code># Path parameter style
curl -sL -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  http://localhost:82/api.php/trigger/Front%20Gate | jq

# JSON body style
curl -sL -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"gate":"Front Gate"}' \
  http://localhost:82/api.php/trigger | jq

# Query parameter fallback
curl -sL -X POST \
  "http://localhost:82/api.php/trigger?api_key=YOUR_API_KEY&gate=Front%20Gate" | jq</code></pre>
          <p class="text-gray-700 text-sm mt-2">Responses include <code>success</code> and details. Failures are logged with context.</p>
        </div>
      </div>
    </section>

    <section>
      <h2 class="text-xl font-semibold mb-2">Tips</h2>
      <ul class="list-disc ml-5 text-gray-800">
        <li><span class="font-semibold">Least privilege:</span> create separate API keys per integration and revoke when done.</li>
        <li><span class="font-semibold">IP allowlisting:</span> restrict keys to known IPs where possible.</li>
        <li><span class="font-semibold">Audit:</span> use the Logs tab to review API requests and gate triggers.</li>
      </ul>
    </section>
  </div>
</div>
