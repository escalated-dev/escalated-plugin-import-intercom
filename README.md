# Intercom Import

Import conversations, contacts, admins (agents), teams (departments), and tags from Intercom into Escalated. The adapter uses Intercom's cursor-based pagination and search API, and includes proactive rate-limit management to stay within Intercom's 1,000 req/min limit.

## Installation

```bash
# Install via Composer
composer require escalated/escalated-plugin-import-intercom
```

## Configuration

Credentials are entered through the Escalated import wizard UI. The following field is required:

| Field | Description |
|---|---|
| `token` | Access token — generate in **Intercom Developer Hub > Your Apps > Authentication** |

## Features

- Imports admins (mapped to agents), teams (mapped to departments), tags, and contacts
- Imports conversations (mapped to tickets) with full state and priority mapping
- Imports conversation parts (replies and internal notes) — only content-bearing parts (`comment`, `note`) are imported
- Contacts use Intercom's unified API v2 (leads and users in a single resource)
- Contacts are fetched via POST search with cursor-based pagination
- Proactive rate-limit management: distributes requests across 10-second windows (≤166 req/window) before hitting Intercom's 429 threshold
- Automatic back-off on 429 responses with `Retry-After` header support
- Cursor-based pagination throughout allows resumable imports
- Maps Intercom states (`open`, `closed`, `snoozed`) to Escalated statuses
- Maps Intercom priority (`not_priority`, `priority`) to Escalated priority levels
- Conversation title falls back to a plain-text excerpt of the first message body when absent
- Attachment metadata collected during reply extraction (signed URLs; framework handles download)

## Hooks

### Filters

- `import.adapters` — Registers the `IntercomImportAdapter` with the Escalated import system

## Entity Types Imported

`agents` → `tags` → `departments` → `contacts` → `tickets` → `replies` → `attachments`

## Requirements

- Escalated >= 0.6.0
- Intercom account with a Developer Hub app and access token
