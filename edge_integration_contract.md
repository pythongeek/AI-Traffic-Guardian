# Bot Shield Pro — edge integration contract (Phase 0)

This document is the single source of truth for the Cloudflare Worker companion feature. Every phase (WP REST endpoints, the Worker, the sync job, the setup wizard) must match this exactly. If any phase needs to deviate, update this file first, then the code.

---

## 1. Policy snapshot schema

This is the object the WP plugin generates and pushes to Cloudflare KV. The Worker reads this to make decisions without calling WordPress.

```json
{
  "schema_version": "1.0",
  "site_id": "atg_8f3a1c2b9d4e",
  "generated_at": "2026-07-19T10:32:00Z",
  "default_action": "observe",
  "shadow_mode": true,
  "vendors": [
    {
      "vendor": "openai",
      "purposes": {
        "search": "allow",
        "training": "block",
        "unverified": "throttle"
      },
      "ip_ranges": ["20.15.240.64/28"],
      "verified_agents": ["OAI-SearchBot", "GPTBot"]
    }
  ],
  "human_proxies": ["ChatGPT-User", "Claude-User", "Perplexity-User"],
  "exempt_paths": ["/wc-api/", "/wp-json/wc/", "/wp-cron.php", "/wp-json/wp/v2/"],
  "throttle": {
    "requests_per_minute": 20,
    "action_on_exceed": "throttle"
  }
}
```

Field notes:
- `schema_version` — major.minor as a string. The Worker checks the major version and falls back to `default_action: "observe"` for anything it doesn't recognize, rather than failing closed or open unpredictably.
- `site_id` — matches the WordPress site's stored identifier. Used as the KV key prefix and included in every signed request.
- `shadow_mode` — when `true`, the Worker logs its decision but never actually blocks; matches the plugin's existing 7-day observation behavior, now enforced at the edge too.
- `vendors[].purposes` — value is one of `"allow" | "block" | "throttle"`.
- `exempt_paths` — matched as a prefix. Anything under these paths bypasses classification entirely, mirroring the plugin's existing business-critical bypass list.

Size note: keep a single snapshot under 20 KB. KV's free-tier value limit is generous (25 MB) but a bloated snapshot slows every Worker cold start — if the vendor list grows large, split verified IP ranges into a second KV key (`ranges:{site_id}`) fetched less frequently.

---

## 2. KV key naming

| Key pattern | Value | Written by | Read by |
|---|---|---|---|
| `policy:{site_id}` | Full snapshot JSON (schema above) | WP plugin (on change + weekly refresh) | Worker (cached in memory, refreshed every few minutes) |
| `meta:{site_id}` | `{"version":"1.0","hash":"sha256:...","pushed_at":"..."}` | WP plugin | WP plugin (to skip redundant pushes if the hash hasn't changed) |

Only two key patterns for v1. Don't add more without updating this doc — the Worker's read logic assumes exactly these two.

---

## 3. REST endpoints (WordPress side)

Both endpoints live under the plugin's existing REST namespace: `/wp-json/atg/v1/`.

### 3.1 `POST /wp-json/atg/v1/verify`

Called by the Worker only for traffic that doesn't resolve from the cached snapshot (unrecognized IP, ambiguous user-agent).

**Request headers**
```
X-ATG-Site-Id: atg_8f3a1c2b9d4e
X-ATG-Timestamp: 1753000320
X-ATG-Signature: <hex hmac, see section 4>
Content-Type: application/json
```

**Request body**
```json
{
  "ip": "203.0.113.42",
  "user_agent": "Mozilla/5.0 (compatible; SomeBot/1.0)",
  "path": "/product/example",
  "headers_subset": {
    "accept": "text/html",
    "accept-language": "en-US"
  }
}
```

**Response — 200**
```json
{
  "decision": "allow",
  "reason": "unrecognized_agent_treated_as_human",
  "ttl_seconds": 300
}
```
`decision` is one of `"allow" | "block" | "throttle"`. `ttl_seconds` tells the Worker how long it may cache this specific IP+UA decision before asking again — keeps repeat traffic from the same borderline source off the verify path.

### 3.2 `GET /wp-json/atg/v1/snapshot`

Called by the sync job (Phase 3) to read the current policy before pushing it to KV — this is the read side; the plugin itself generates the write.

**Request headers**
```
X-ATG-Site-Id: atg_8f3a1c2b9d4e
X-ATG-Timestamp: 1753000320
X-ATG-Signature: <hex hmac over site_id + timestamp, no body>
```

**Response — 200**: the full snapshot object from section 1.

### 3.3 Error shape (both endpoints)

```json
{
  "error": {
    "code": "invalid_signature",
    "message": "Signature verification failed."
  }
}
```

Codes: `invalid_signature`, `timestamp_expired`, `rate_limited`, `unknown_site`. Every one of these returns the matching HTTP status (401, 401, 429, 404) — don't invent new codes without adding them here.

---

## 4. HMAC signing scheme

Applies to every request between the Worker and WordPress, both directions.

**Secret**: a 32-byte random value, generated once during setup (Phase 4). Stored as a WordPress option (encrypted at rest if the site has an encryption-at-rest mechanism available; otherwise standard option storage, since it never leaves the server except as a Worker environment secret) and as a Cloudflare Worker secret (`env.ATG_SHARED_SECRET`, set via the Cloudflare API during deploy — never hardcoded in the Worker script itself).

**Signature computation**
```
message = "{timestamp}.{method}.{path}.{body}"
signature = hex(HMAC_SHA256(secret, message))
```
- `body` is the raw JSON string for POST requests, or an empty string for GET.
- `path` is the request path only (`/wp-json/atg/v1/verify`), no query string, no domain.

**Verification rules**
- Reject if `X-ATG-Timestamp` is more than 120 seconds from the server's current time (replay protection).
- Reject if the computed signature doesn't match `X-ATG-Signature` exactly (constant-time comparison — use `hash_equals()` in PHP, not `===`).
- Reject if `X-ATG-Site-Id` doesn't match the site's stored ID.

---

## 5. Versioning and compatibility

- Any breaking change to the snapshot schema bumps the major version (`"2.0"`). The Worker must keep supporting the previous major version for at least one release cycle, falling back to `default_action: "observe"` for anything newer than it understands.
- Any breaking change to the REST contract (new required field, changed response shape) gets a new endpoint path (`/wp-json/atg/v2/verify`) rather than mutating `v1` — the Worker and plugin can then be updated independently without a hard cutover.

---

## 6. What each phase must NOT do

- The Worker must never call `/wp-json/atg/v1/verify` for a request already resolved by the cached snapshot — that defeats the entire point of the edge layer.
- The WP plugin must never write to KV on every request — only on policy change or the scheduled refresh (section 2 write cadence).
- Neither side may skip signature verification "temporarily for testing" in a code path that could ship — use a separate `ATG_DEBUG_MODE` flag with its own explicit warning in the setup wizard instead.

