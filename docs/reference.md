# Reference

Imports conversations, contacts, admins (agents), teams (departments), and tags from Intercom into Escalated. Includes proactive rate-limit management to stay within Intercom's 1,000 requests per minute limit.

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
