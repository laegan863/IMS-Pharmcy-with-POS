---
id: T-0004
title: ADD RATE LIMITTING IN THE LOGIN PAGE
status: done
created: 2026-11-21
updated: 2026-11-21
parent: root
origin: user-request
topics: [rate, limitting]
area: unknown
links:
  depends-on: []
  related: []
  supersedes: []
sources: []
files: []
---

## Why this task exists
ADD RATE LIMITTING IN THE LOGIN PAGE

## Decisions
- Use five failed authentication attempts per normalized identifier and IP in a 60-second window; the next request is blocked until expiry. These are proposed defaults from the analysis, not a confirmed business policy. (judgment)
- Count only validly formed attempts that fail authentication; clear the key after a successful login. Preserve existing authentication, session regeneration, redirects, and account-access checks. (judgment)
- Normalize the identifier consistently with the existing authentication lookup. Use a keyed deterministic digest for the combined identifier and trusted IP; do not expose raw identifiers in cache keys or logs. (judgment)
- Require a shared cache backend that supports atomic locks and expiry across all application instances. A per-process or node-local store is insufficient for a multi-instance deployment. Verify the actual store and its failure behavior before rollout. (judgment)
- Fail closed with a generic temporary-unavailability response if limiter operations fail or a per-key lock cannot be acquired within a bounded interval. Do not attempt authentication in that case. (judgment)
- Preserve the existing browser/Inertia error flow; return HTTP 429 and Retry-After for clients expecting a direct response. Confirm the appropriate response negotiation against the installed Inertia and Laravel versions. (judgment)

## Outcome
1 files changed. QA not confirmed, security audit passed.

## Open threads
- [ ] Verify the shared cache, atomic locking, trusted proxies, and expiry in the target environment before rollout. (project)
- [ ] Complete the security audit and dependency assessment; its reported PASS was conditional and did not verify any controls. (project)
- [ ] Check: QA (Quinn) still reported problems after 2 fix round(s) - see 06-qa-report.md
- [ ] Check: Reviewer (Rex) still reported problems after 2 fix round(s) - see 08-review.md
