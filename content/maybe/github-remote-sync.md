---
title: GitHub Remote Sync
description: Experimental hosted synchronization layered on top of the local-first Git workflow.
icon: github
order: 10
keywords: [github, remote, experimental, sync]
---

# GitHub Remote Sync

GitHub is not Git. Git works entirely locally; GitHub is only one optional place to push a repository over the network. Lightdocs places this integration under **Studio → Maybe** so it is not mistaken for a runtime or deployment requirement.

## Safety policies

- **Sanitized mirror** includes private pages in a generated copy but replaces recognized secret assignments, command arguments, provider tokens, and private keys with `"<redacted>"`.
- **Public content only** excludes private and draft pages and applies the same scanner to remaining text files.
- **Full private source** preserves canonical files exactly and requires explicit acknowledgement.

Sanitization never modifies canonical Markdown. The first push requires review of a preflight containing filenames and counts without credential values. Narrowing a policy requires a new repository because later commits cannot erase broader content from earlier history.

## Experimental connection

The current experiment uses GitHub OAuth Device Flow:

1. Create a GitHub OAuth App and enable **Device Flow**.
2. Open the collapsed **Maybe: GitHub remote sync** area in Studio Settings and enter the public client ID.
3. Open **Studio → Maybe → GitHub remote sync** and approve the one-time code.
4. Create a private repository or select an existing `owner/name` repository.
5. Review preflight and approve the initial push.

The access token remains in the authenticated server-side session. It is not written to Markdown, YAML, SQLite, `.env`, Git remotes, or command arguments. SQLite stores only safe status metadata for recent attempts.

This experiment requires the optional `git` executable and outbound HTTPS, but no Node process, Git daemon, SSH key, or queue worker.
