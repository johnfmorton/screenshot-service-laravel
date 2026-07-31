# Screenshot Service v1.0.0 — client integration guide

This document is written for an agent working in a **consuming** codebase (a site
that calls the screenshot service), not in the service itself. It describes what
changed in v1.0.0 and what the client needs to do about it.

Start by locating the existing integration in this project. Search for the
service's hostname, `X-API-Key`, `/api/screenshots`, or whatever wrapper class or
service object fronts it. Adapt the guidance below to the code that is actually
there — do not assume a particular framework, HTTP client, or caching layer.

If you want to confirm the live contract, `GET /api/` on the service returns its
own documentation as JSON, no authentication required.

---

## The one change you must make

**v1.0.0 adds a fifth status: `blocked`.** The full set is now:

```
pending | processing | completed | failed | blocked
```

`blocked` means the target site's bot protection turned the capture away — a
Cloudflare or Vercel challenge, or a WAF returning 403/429. It is a **terminal**
status. It will never become `completed` on its own.

This breaks one specific and common polling pattern. If the poll loop exits on an
explicit success-or-failure pair, like:

```
while status not in ("completed", "failed"):
    sleep(...)
    status = poll()
```

then a blocked capture never satisfies the exit condition and the loop runs until
whatever timeout or retry ceiling sits above it — or forever, if there isn't one.

Invert the condition so it keys on the states that are still in progress. That way
any future status the service adds is treated as terminal rather than as a reason
to keep waiting:

```
while status in ("pending", "processing"):
    sleep(...)
    status = poll()
```

Then branch on the result: `completed` has images, `failed` and `blocked` have an
`error` string.

**Check for this pattern first.** If the integration is fire-and-forget or
webhook-driven, this may not apply — but verify rather than assume.

---

## Response shapes

All verified against the v1.0.0 controller. Note the nested `images` object: an
older revision of the service README incorrectly documented top-level `image_url`
and `thumbnail_url` keys, which the API has never returned. If this project's code
reads those keys, it was coded against that error and is silently broken.

### `POST /api/screenshots`

Headers: `X-API-Key: <key>`, `Content-Type: application/json`.

Returns **200** with a complete payload when a cached screenshot already exists,
or **202** when the capture has been queued:

```json
{
  "id": "9e5b4a3c-...",
  "status": "pending",
  "poll_url": "https://<service>/api/screenshots/9e5b4a3c-..."
}
```

Handle both. A client that only expects 202 will miss the cache-hit fast path.

### `GET /api/screenshots/{id}`

Completed:

```json
{
  "id": "9e5b4a3c-...",
  "status": "completed",
  "url": "https://example.com",
  "images": {
    "full": "https://cdn.../full.png",
    "thumbnail": "https://cdn.../thumb.png"
  },
  "captured_at": "2026-07-31T12:00:00+00:00",
  "expires_at": "2026-08-01T12:00:00+00:00"
}
```

Blocked (and `failed`, which has the same shape):

```json
{
  "id": "9e5b4a3c-...",
  "status": "blocked",
  "url": "https://example.com",
  "error": "Blocked by bot protection: the site responded with HTTP 403."
}
```

`images`, `captured_at`, and `expires_at` are **absent** unless the status is
`completed`. `error` is present only for `failed` and `blocked`. Code that reaches
into `images.full` without checking the status will throw on a blocked response.

### `DELETE /api/screenshots/{id}`

Returns 204. Invalidates the cached screenshot.

---

## Webhooks

If this project uses `webhook_url`, the receiving endpoint needs the same
treatment. The payload mirrors the GET response:

```json
{
  "id": "9e5b4a3c-...",
  "status": "blocked",
  "url": "https://example.com",
  "error": "Blocked by bot protection: ..."
}
```

Webhooks now fire for blocked captures too, so a handler that assumes any webhook
means "images are ready" will fail. Branch on `status`.

If a `webhook_secret` was supplied, the request carries an `X-Signature-256`
header: HMAC-SHA256 of the raw JSON body, keyed with that secret. Verify it
against the **raw body bytes**, not a re-serialized copy of the parsed JSON — key
order and escaping will differ and the signature won't match.

---

## Two behaviour changes that alter what you'll see

**You will see more non-success results, and that is the point.** Captures that
previously returned a "successful" screenshot of a Cloudflare challenge or a
`403 Forbidden` page now come back as `blocked`. The image was always garbage;
the difference is the service now says so instead of storing it and serving it
from cache for the full TTL. If this project has screenshots on file that are
actually block pages, they will refresh into honest `blocked` results as their
cache entries expire.

**4xx and 5xx responses now fail instead of being photographed.** If any part of
this project deliberately screenshots error pages, that no longer works by
default; it needs `SCREENSHOT_FAIL_ON_ERROR_RESPONSE=false` set on the service.
This is unlikely but worth a grep before assuming otherwise.

---

## Recommended handling

**Treat `blocked` and `failed` differently.** They warrant different responses:

- `blocked` is about the target site, not the service, and is often persistent.
  A site behind a WAF will likely still be behind it in an hour. Retrying on a
  tight loop just burns rate limit. Back off substantially — hours, not seconds —
  and consider surfacing these for a human to look at, since the durable fix is
  to get the service's IP allowlisted on the target.
- `failed` is more likely transient (a timeout, a network error) and is a
  reasonable candidate for a normal retry.

**Blocked results are not cached by the service.** Unlike a completed screenshot,
a blocked one is never stored and never served from cache, so a later request does
re-attempt against the origin rather than replaying the old outcome. That makes
retrying meaningful — but it also means every retry is a real capture attempt, so
the backoff above matters.

**Don't render a broken image.** Wherever screenshots appear, `blocked` and
`failed` need a real fallback: a placeholder, the site's favicon, or just the
link without a preview. Check what the current code does when `images` is missing.

**Watch for two different 429s.** The service's own rate limiter returns HTTP 429
with a `Retry-After` header and `X-RateLimit-*` headers on the response — that
means *this client* is sending too many requests. A `blocked` status whose error
mentions HTTP 429 means the *target site* rate-limited the capture. They call for
opposite responses; don't collapse them into one handler.

---

## Request options

Available on `POST /api/screenshots`:

| Field | Type | Notes |
|---|---|---|
| `url` | string | Required, max 2048 chars |
| `viewport_width` | int | 320–3840, default 1280 |
| `viewport_height` | int | 240–2160, default 800 |
| `max_width` | int | 100–3840, downscales the full image |
| `thumbnail_width` | int | 50–1920, default 400 |
| `thumbnail_height` | int | 50–1920, default 300 |
| `wait_until` | string | `networkidle0`, `networkidle2` (default), `load`, `domcontentloaded` |
| `timeout` | int | 10–300 seconds, default 120 |
| `user_agent` | string | Overrides the service default |
| `force_refresh` | bool | Bypass the cache |
| `webhook_url` | string | Receives the payload above on completion |
| `webhook_secret` | string | Enables `X-Signature-256` |

Two worth knowing:

- For heavy or animated pages (WebGL, sites with polling requests), `wait_until:
  "load"` succeeds where `networkidle2` times out, because those pages never go
  quiet.
- Overriding `user_agent` per request causes the service to drop its default
  client-hint headers, since headers contradicting the requested UA look worse to
  bot detection than sending none. Only override it when a site genuinely needs a
  different UA.

---

## Front-end performance

If screenshots are rendered in a list or grid:

- Use `images.thumbnail`, not `images.full`. The full capture is viewport-width
  and can be an order of magnitude larger.
- Set explicit `width` and `height` (or an `aspect-ratio`) on the image element.
  Screenshot dimensions are known ahead of time, and omitting them causes layout
  shift as each one loads.
- Add `loading="lazy"` and `decoding="async"` to anything below the fold.
- The fallback for `blocked`/`failed` should be CSS or inline SVG, not a fetched
  placeholder image — a broken preview shouldn't cost a network round trip.

---

## Checklist

1. Find the integration and read how it polls, if it polls.
2. Fix the poll exit condition to key on `pending`/`processing`.
3. Add explicit `blocked` handling wherever `failed` is handled today.
4. Confirm the code reads `images.full` / `images.thumbnail`, not `image_url` /
   `thumbnail_url`, and that it checks the status before reaching into `images`.
5. Confirm the POST caller handles a 200 cache hit as well as a 202.
6. If webhooks are used, branch on `status` in the handler and verify the
   signature against the raw body.
7. Give `blocked` a longer backoff than `failed`.
8. Make sure the UI has a real fallback when there is no image.
9. Check that the service's 429 and a target site's 429 are handled separately.
