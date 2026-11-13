<p align="center">
  <a href="https://escalated.dev"><img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/logo.png" width="56" height="56" alt="Escalated"></a>
</p>

<h1 align="center">Intercom import for Escalated</h1>

<p align="center">Move your Intercom conversations into Escalated, history included.</p>

<p align="center">
  <a href="https://escalated.dev"><b>Website</b></a> ·
  <a href="https://escalated.dev/docs"><b>Docs</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="https://github.com/escalated-dev/escalated-plugin-import-intercom/releases"><b>Releases</b></a>
</p>

This plugin imports conversations, contacts, admins, teams and tags from Intercom into Escalated for Laravel, pacing its requests to stay inside Intercom's rate limit. It's for teams switching from Intercom who want to keep their conversation history.

## Features

<table>
<tr>
<td width="33%" valign="top">

**What it imports**
- Admins, teams, tags and contacts
- Conversations with state and priority mapping
- Conversation parts: replies and internal notes
- Untitled conversations titled from the first message

</td>
<td width="33%" valign="top">

**Rate limits**
- Requests spread across 10-second windows
- Backs off on 429, honours Retry-After

</td>
<td width="33%" valign="top">

**Pagination and mapping**
- Contacts via API v2 search with cursors
- Resumable imports with cursor pagination
- States mapped: open, closed, snoozed
- Priority levels mapped

</td>
</tr>
</table>

<p align="center"><sub><a href="docs/features.md">Every feature in detail</a></sub></p>

<p align="center">
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel"></a>
  <a href="https://getcomposer.org"><img src="https://img.shields.io/badge/Composer-885630?style=for-the-badge&logo=composer&logoColor=white" alt="Composer"></a>
</p>

## Learn more

- [Every feature](docs/features.md)
- [Configuration, hooks and import order](docs/reference.md)
- [Plugin SDK](https://github.com/escalated-dev/escalated-plugin-sdk): how Escalated plugins are built
- [Escalated docs](https://escalated.dev/docs)
- [Escalated](https://github.com/escalated-dev/escalated), the main repo

## License

MIT. Copyright (c) 2026 Escalated.dev. See [LICENSE](LICENSE).
