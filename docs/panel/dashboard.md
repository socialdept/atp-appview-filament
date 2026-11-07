# Dashboard

Part of the [atp-appview-filament docs](../introduction.md#documentation). The Network, Index and Upkeep stats, the Collections table, backfill cards and the backfill notice.

## Stats

Three rows of stats on the dashboard, read from atp-appview's `AppviewStatus`. Each stat's second line adds a figure rather than restating the label.

- **Network**: whether the network is flowing into the index, through Tap or Jetstream as `atp-appview.ingest.source` says. With Tap, the repos it follows, how much is waiting on the app and how long it will take to drain, events a minute and what is held during backfill. With Jetstream, whether the stream is following or stalled, the repos followed and events a minute. When labels come from the labelers' own streams, a stat says how many are open. It refreshes every 5 seconds.
- **Index**: what the index holds. Records and how many arrived in the last 24 hours, identities, tombstones and hidden records, and the size on disk, which is shown on Postgres only.
- **Upkeep**: what the index does to stay correct. How much of what is due has been reconciled, reconcile errors by kind, followed lexicons with any held back or invalid records, and what the blocklist has dropped.

## Collections

A table of records, repos, hidden records and tombstones per collection, as `appview:stats` last counted them, with a Count now button to count again. Each record count opens that collection on the [Records](records.md) page.

## Backfills

A card per running backfill, and per one that ended in the last hour, with how many repos have delivered, records received, the rate, the time left, and why one failed. The cards hide when there are none, and keep polling so a backfill that starts shows up.

While a backfill runs, every other page of the panel shows a notice at the top, since lists and counts may be missing records until it finishes. It links back to the dashboard for progress. Turn it off with `backfillNotice(false)`.
