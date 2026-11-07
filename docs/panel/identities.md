# Identities

Part of the [atp-appview-filament docs](../introduction.md#documentation). The list of repos the index knows, and each repo's page and actions.

## The list

Every repo the index knows, with its avatar, handle, PDS, account status, record count and what the reconciler last found, errors grouped by kind. Filter by account status, by whether the last check passed, or for repos never reconciled. Reconcile one repo or a selection now.

## Identity pages

Each repo is laid out as a social profile, with its banner, avatar, display name, handle, pronouns, bio and website from atp-appview's Profiles, its PDS, record and collection counts, and when it last wrote. Badges show its account status, blocks, what moderation is in force, its account labels colored by what they do, and whether the operator hosts it. Below, its records across collections, newest first, narrowed to one by clicking a collection.

From its page:

- **Reconcile** shows a preview of what reconciling with its PDS would change before anything is written.
- **Moderate** blocks or unblocks the repo, and holds the moderation actions described in [Moderating accounts](../moderation/accounts.md).
- **Retry failures** retries or reindexes the repo's [failed deliveries](failed-deliveries.md), when it has any.
- **More** refreshes its handle, PDS and profile, resyncs it in Tap, shows Tap's status for it and its profile record, and links to its records in the panel, its repo, its Bluesky profile, its PDS and its PLC audit log.

Everything destructive asks first. Resyncing needs a Tap that can resync a repo, such as Tab.
