# Moderating accounts

Part of the [atp-appview-filament docs](../introduction.md#documentation). Bans, reviews and takedowns from an identity's page, and the Moderation log.

## The Moderate menu

On an [identity page](../panel/identities.md), the Moderate menu blocks or unblocks the repo and, with moderation on, holds:

- **Ban** and **Unban**.
- **Needs review** and **Clear needs review**.
- **Keep its records** or **Prune its records when due**, for a banned account.
- **Take down on its PDS** and **Restore on its PDS**, for an account on a PDS the operator runs.

Badges on the profile show what is in force, the account's labels colored by what they do, and whether the operator hosts it. Records tables show each record's labels the same way.

## The Moderation log

Every ban, review and takedown, by an operator or a label, with who, why, when, and when it was lifted. Filter by action, by source, or by whether it is still in force.

The ban, review and takedown actions and the log show only when atp-appview's moderation feature is on and the plugin's `moderation()` option is left on.
