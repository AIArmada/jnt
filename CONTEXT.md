---
title: J&T Context
package: jnt
status: current
surface: gateway
family: checkout-flow
---

# J&T Context

## Snapshot
- Composer: `aiarmada/jnt`
- Role: J&T Express Malaysia carrier adapter, orders, tracking, commands, and webhooks.
- Search first: `src/Models`, `src/Actions`, `src/Services`, `src/Events`, `src/Jobs`, `config`, `docs`
- Related: `filament-jnt`, `shipping`, `checkout`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-jnt/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns provider-specific API clients, webhooks, commands, and carrier data mapping.
- Audit `filament-jnt`, `shipping`, and `checkout` when request, webhook, or tracking behavior changes.
- Update `docs/*.md` in the same pass when public behavior or config changes.
