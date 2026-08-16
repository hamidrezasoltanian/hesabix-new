# Click ↔ Hesabix sync (branch `cursor/click-hesabix-sync`)

HMAC JSON API used by Click CRM. Does **not** move warehouse stock.

## Endpoints (`POST /api/v1/click/sync/...`)

- `sell/draft`, `sell/issue`, `sell/return`, `sell/cancel`, `sell/receive`
- `sell/docs-status`, `sell/return-draft/cancel`
- `catalog/persons`, `catalog/banks`
- `reports/expenses`, `purchases`, `cheques`, `banks`, `gl-entries`, `ap-open`

Auth: `HmacAuthenticator` (`X-API-Token`, `X-Signature`, `X-Timestamp`, `X-Nonce`).

## Schema

Doctrine entities (create tables via `doctrine:schema:update` on Pilot, not Production):

- `hesabix_idempotency_log`
- `hesabix_sync_audit_log`

Reports are incremental via `after_id` / numeric `updated_after` + `limit`. AP `remaining` is **null** until a real open-balance query exists. Payloads include `official: false`.
