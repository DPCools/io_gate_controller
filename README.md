# Gate Controller System

A PHP-based controller for triggering gates via networked devices (e.g., Axis devices using VAPIX).

## Key Requirements

- PHP with cURL extension enabled (ext-curl)
- Web server: Apache with mod_rewrite enabled
- Site config allowing .htaccess overrides (AllowOverride All)

The root `.htaccess` contains rewrite rules to route requests to `index.php`.

## Axis VAPIX Notes and Recent Improvements

This app triggers Axis I/O using `axis-cgi/io/port.cgi` with the `action` parameter:

- Action format: `<port>:/<milliseconds>\` (Axis expects a trailing backslash)
- URL is built by `buildVapixUrl()` in `src/models/Gate.php`
- The action is URL-encoded using `rawurlencode()` so the trailing `\` is encoded as `%5C`

In `GateController\Models\Gate.executeVapix()` (`src/models/Gate.php`) we harden cURL behavior for Axis devices:

- Force HTTP/1.1 when available
- Add `Expect:` empty header to avoid 100-continue delays
- Optional TLS 1.2 enforce if device entry has `tlsv1_2` truthy
- Respect `insecure` device flag to disable SSL verification for self-signed certs
- Treat certain device quirks as success:
  - If the device times out after connect (cURL 28), assume success for Axis `io/port.cgi`
  - If the device closes TLS early (cURL 56) after sending a 2xx or we reached Axis endpoint, treat as success

These changes address cases where Axis flips the relay but stalls or closes the response early.

## Authentication

- Supports Basic and Digest.
- Preferred mode comes from the device configuration (`auth`: `basic` or `digest`).
- The code will try the preferred mode first and may try the other if 401.
- If you consistently get `401 Unauthorized`:
  - Verify username/password and case sensitivity
  - Ensure the Axis user has VAPIX/IO permissions
  - Confirm the configured auth mode matches the device

## SSL and Certificates

- For devices with self-signed certificates, set the device `insecure` flag to disable peer verification.
- If your device requires TLS 1.2, set `tlsv1_2` truthy in the device configuration.

## Troubleshooting

- If you receive `400 Bad Request`, the device may not accept the encoded trailing backslash. We can add a fallback to try literal `\` (unencoded) on failure. Open an issue or adjust `buildVapixUrl()` accordingly.
- If you receive `401 Unauthorized`, confirm credentials via the device web UI and permissions.
- If you receive timeouts or SSL EOF errors but the relay still flips, this is expected and now treated as success for Axis endpoints.

## Apache Quick Setup (reference)

- Enable rewrite: `a2enmod rewrite`
- In your site conf for the document root, set: `AllowOverride All`
- Reload Apache

## File References

- Model and HTTP logic: `src/models/Gate.php`
  - URL building: `buildVapixUrl()`
  - Request execution: `executeVapix()`
- Entry point and routing handled by `index.php` + `.htaccess`.

## Notes

- Keep device records accurate in the database (scheme, host, port, base_path, auth, username, password, insecure, tlsv1_2).
- The app logs gate trigger attempts in the audit log with success status and messages.
