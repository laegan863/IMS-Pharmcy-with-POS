---
id: T-0002
title: fix the reviewed recomendations in the site full inspection
status: done
created: 2026-11-21
updated: 2026-11-21
parent: T-0001
origin: follow-up
topics: [reviewed, recomendations]
area: unknown
links:
  depends-on: [T-0001]
  related: []
  supersedes: []
sources: []
files: []
---

## Why this task exists
fix the reviewed recomendations in the site full inspection

## Decisions
- Read .ai/task-tree/INDEX.md, the T-0001 node and linked inspection artifacts first; map each selected finding to evidence, affected paths, fix and verification. Preserve relevant existing changes. (user, project) (judgment)
- Before editing, read .ai/rules/index.md, matching rule files and keyword matches; activate applicable project skills, including laravel-advanced, and testing-best-practices before writing tests. Use the task-link-tree skill if available and update the task tree at completion. (project)
- Confirm installed package versions and use Laravel Boost search-docs before version-dependent changes. Inspect existing routes, roles, policies, validation and clients before choosing implementation details. (project)
- Enforce each affected action and record permission on the server. Where evidence cannot establish permission, deny access pending owner review; do not invent a role policy. (user)
- Preserve request and response contracts for valid authorized clients wherever possible. Use server-authoritative prices, stock checks, transactions and locking only when an identified finding and workflow require them. (user, judgment) (judgment)
- Assume no migration or dependency change. If evidence shows a migration is necessary, establish compatibility, data integrity and rollback steps before implementation; obtain approval for dependency changes. (project, judgment) (judgment)

## Outcome
1 files changed. QA passed.

## Open threads
- [ ] Reconcile the QA report’s PASS label with its statement that T-0002 is incomplete and unverified.
- [ ] Check: Reviewer (Rex) still reported problems after 2 fix round(s) - see 08-review.md
