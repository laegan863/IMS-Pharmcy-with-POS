---
id: T-0001
title: site full inspection
status: done
created: 2026-11-21
updated: 2026-11-21
parent: root
origin: user-request
topics: [site, full]
area: unknown
links:
  depends-on: []
  related: []
  supersedes: []
sources: []
files: []
---

## Why this task exists
I want you to reexamine the site and find vulneravelities, bugs, and security gap in the site.

## Decisions
- Inspect before specifying fixes. The actual routes, roles, dependencies, schema and deployment configuration are unverified; file-level changes and migration needs must follow evidence. (user, judgment) (judgment)
- Treat server-side authorization as authoritative. Derive the role and record-access matrix from the application and flag ambiguous business permissions for owner review; deny access where a safe decision cannot be established. (user, judgment) (judgment)
- Preserve existing behavior for authorized users. Add validation and controls at existing request and action boundaries, with explicit compatibility checks for current clients and workflows. (project, judgment) (judgment)
- Use transactions and concurrency controls only where the inspected workflow requires them. Calculate authoritative sale values and enforce stock rules on the server if those workflows exist. (user, judgment) (judgment)
- Do not assume a migration is needed. If a confirmed fix requires one, use an additive, backward-compatible migration first; defer destructive cleanup until after verification. (project, judgment) (judgment)
- Separate source findings from deployment findings. Do not claim a clean bill of health without access to the relevant environment, integrations and operational evidence. (user, judgment) (judgment)

## Outcome
1 files changed. QA passed, security audit passed.

## Open threads
- [ ] Assess deployed configuration and integrations when the relevant environment is available. Record any remaining coverage gaps and update the task tree when the inspection is complete.
- [ ] Check: Reviewer (Rex) still reported problems after 2 fix round(s) - see 08-review.md
