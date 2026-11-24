# AI Provider Management

`/admin/llm-management` lists Runcrate, OpenAI and NVIDIA without returning keys. Staff with `manage_ai` can view/test; changing credentials additionally requires `manage_settings`. Keys saved through this page use Laravel encrypted casts and are excluded from API output and audit values. Back up the application encryption key securely before rotating it.

The active provider is resolved at runtime by listing extraction, grounded chat, price summaries, low-confidence market estimates and social text generation. Environment credentials remain the fallback for providers without a database override. Disabling the selected connection disables outbound AI calls instead of silently selecting another billable provider. Clearing the replacement-key field preserves the stored key.

Supported Runcrate models come from `config/services.php`. Provider URLs are server-controlled, not supplied by browser requests. Connection tests are throttled and store sanitized HTTP status/results. A configured key does not prove that inference is available: Runcrate returned HTTP 529 (overloaded) during initial checks. Do not describe an error response as successful inference.

AI listing drafts require authentication and explicit review before save. They do not publish or choose an owner. Photos are uploaded together with the reviewed listing and are not sent to the text-only model. Unknown facts stay unset; production listings require human validation. The existing price-analysis jobs remain queued; interactive draft extraction is a bounded synchronous API request.

After deployment, run migrations, rebuild config/route caches and restart queue workers. Test `/api/v1/admin/llm/providers`, `/api/v1/admin/llm/test`, and seller/admin draft routes. Test secrets must never be committed. The release builder packages committed source and production Composer dependencies, preserving production `.env`, user uploads and database records.

## Deployment Verification: 2026-11-24

- Application release `a61a997` deployed to aiprice.app after database/file backups. Additional release-tooling fixes preserve the app root's web-access permissions and normalize Windows archive modes.
- Runcrate activated using an encrypted database credential. Existing OpenAI and NVIDIA environment credentials were preserved, not copied to the frontend or Git.
- Production authenticated API checks for provider management, AI status and admin listings returned HTTP 200. Temporary smoke-test tokens were revoked after use.
- Live Runcrate inference and listing extraction returned HTTP 200 with explicit draft review required. The earlier HTTP 529 was transient; it is not the latest production result.
- Public home, admin SPA, public listing list/detail and a gallery image returned HTTP 200.
- All 13 production configuration checks passed. This checks configuration, not actual email delivery or a paid payment transaction.
- Backend suite: 128 tests, 750 assertions. Focused provider/draft/pricing suite: 16 tests, 86 assertions after final service adjustments. Production frontend build and TypeScript checks passed.
- Desktop/mobile browser checks verified API management and the AI composer with mocked extraction; live extraction was verified separately through the production API. No synthetic listing was saved or published.
