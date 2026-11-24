# AI Provider Management

`/admin/llm-management` lists Runcrate, OpenAI and NVIDIA without returning keys. Staff with `manage_ai` can view/test; changing credentials additionally requires `manage_settings`. Keys saved through this page use Laravel encrypted casts and are excluded from API output and audit values. Back up the application encryption key securely before rotating it.

The active provider is resolved at runtime by listing extraction, grounded chat, price summaries, low-confidence market estimates and social text generation. Environment credentials remain the fallback for providers without a database override. Disabling the selected connection disables outbound AI calls instead of silently selecting another billable provider. Clearing the replacement-key field preserves the stored key.

Supported Runcrate models come from `config/services.php`. Provider URLs are server-controlled, not supplied by browser requests. Connection tests are throttled and store sanitized HTTP status/results. A configured key does not prove that inference is available: Runcrate returned HTTP 529 (overloaded) during initial checks. Do not describe an error response as successful inference.

AI listing drafts require authentication and explicit review before save. They do not publish or choose an owner. Photos are uploaded together with the reviewed listing and are not sent to the text-only model. Unknown facts stay unset; production listings require human validation. The existing price-analysis jobs remain queued; interactive draft extraction is a bounded synchronous API request.

After deployment, run migrations, rebuild config/route caches and restart queue workers. Test `/api/v1/admin/llm/providers`, `/api/v1/admin/llm/test`, and seller/admin draft routes. Test secrets must never be committed. The release builder packages committed source and production Composer dependencies, preserving production `.env`, user uploads and database records.
