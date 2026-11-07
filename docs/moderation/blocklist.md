# Blocklist

Part of the [atp-appview-filament docs](../introduction.md#documentation). Repos and hosts kept out of the index.

Block a repo by DID, or every repo on a PDS host or a host pattern such as `*.spam.example`, for every collection or for one NSID or a prefix ending in `.*`. A block can delete what is already indexed, or drop only new records. Each rule shows how many records it has dropped, and can be purged or unblocked from the page. Unblocking indexes records again as they arrive and asks Tap to backfill blocked repos.

A single repo can also be blocked and unblocked from its [identity page](../panel/identities.md).
