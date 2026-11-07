# Failed deliveries

Part of the [atp-appview-filament docs](../introduction.md#documentation). Events ingest parked, and what ingest hooks rejected and repairs rewrote.

Events ingest parked because no handler could take them, with the error and the event in the [JSON viewer](records.md#the-json-viewer). Retry once the cause in the app is fixed, reindex from the record's PDS once the record is, or forget them, one at a time or a selection. The navigation shows how many are parked.

Below them, a table of what ingest hooks rejected and ingest repairs rewrote, by class and collection, with when each last happened.
