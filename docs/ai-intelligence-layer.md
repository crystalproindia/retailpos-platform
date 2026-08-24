# Friendly AI Intelligence Layer

## Architecture

The AI Assistant is a read-only explanation layer over existing RetailPOS services. It does not calculate sales, profitability, GST, stock, purchases, or outstanding balances independently. `BusinessIntelligenceContextService` delegates to `RetailReportingService`, `ExecutiveReportingService`, and `InventoryIntelligenceService`, plus bounded tenant-scoped CRM and customer reads.

`AiIntentRouter` accepts only approved business intent families. It never creates SQL, receives database schema, or invokes mutation services. `AiDateRangeResolver` converts today, yesterday, this week, last week, this month, last month, and last 30 days in the company timezone before any data provider runs.

`AiAssistantService` produces a structured response containing a title, summary, facts, recommendations, coverage, sources, and follow-up questions. Facts remain server-controlled. An optional `AiProviderInterface` implementation may improve wording but cannot replace facts, source links, scope, or date boundaries. Provider output is stripped of HTML and length bounded.

## Provider Configuration

`OpenAiProvider` reads `OPENAI_API_KEY` only through environment-backed configuration. `AI_PROVIDER_ENABLED` defaults to false. Without an enabled provider and key, the full deterministic assistant remains available and no external request is attempted. Requests use a bounded timeout and output limit. Automated tests replace the interface with local doubles and never call a real provider.

## Scope and Safety

- Existing permissions control access to `/ai` and the Owner Command Center brief.
- Outlet selections are checked through `OutletAccessService`; report providers repeat their normal server-side scope validation.
- Follow-up context stores only the immediate approved intent and is session-keyed by company and user.
- `ai_assistant_interactions` stores provider/status metadata, scope, usage fields, and a SHA-256 prompt digest. It does not store full prompts, API keys, or responses.
- Questions requesting create, update, delete, transfer, payment, refund, messaging, or SQL actions receive an advisory-only response and call no mutation service.
- Provider or source failures return a safe user message. Provider failures fall back to the deterministic answer when source facts are available.

## Current Intents

The controlled intents are business summary, sales summary/comparison, profitability, inventory, reorder, slow stock, outlet comparison, product performance, customer insight, and CRM follow-up. Source transparency links each answer back to the relevant authorized report or workspace.

## Cost Controls and Limits

Questions are limited to 500 characters, requests are rate limited per tenant and user, provider timeouts default to 12 seconds, provider responses are bounded, and conversation context is limited to the immediate prior intent. Deterministic calculations are preferred to model calls. The optional provider receives only the already-approved structured draft.

## Limitations

The first release is English-only and read/advice-only. It does not execute business actions, generate arbitrary queries, infer causal explanations, or provide cross-session conversation transcripts. Data quality and profitability coverage remain limited by the authoritative source records, and the UI labels unavailable evidence rather than treating it as zero.
