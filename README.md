# Escalated Plugin: Import Intercom

Imports conversations, contacts, admins (agents), teams (departments), and tags from Intercom into Escalated. Includes proactive rate-limit management to stay within Intercom's 1,000 requests per minute limit.

## Features

- Imports admins (agents), teams (departments), tags, and contacts
- Imports conversations (tickets) with full state and priority mapping
- Imports conversation parts (replies and internal notes)
- Contacts fetched via Intercom's unified API v2 with POST search and cursor-based pagination
- Proactive rate-limit management distributing requests across 10-second windows
- Automatic back-off on 429 responses with `Retry-After` header support
- Cursor-based pagination throughout for resumable imports
- Maps Intercom states (open, closed, snoozed) to Escalated statuses
- Maps Intercom priority levels to Escalated equivalents
- Conversation title falls back to a plain-text excerpt of the first message body when absent

## Configuration

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `token` | password | Yes | Access token from Intercom Developer Hub > Your Apps > Authentication. |

## Hooks

### Filters
- `import.adapters` — Registers the Intercom import adapter with the Escalated import system.

## Entity Import Order

`agents` > `tags` > `departments` > `contacts` > `tickets` > `replies` > `attachments`

## Installation

```bash
npm install @escalated-dev/plugin-import-intercom
```

## License

MIT
