# Check-in photos

All 13 pose options remain available, and every photo is optional. Clients can
submit blank or partial check-ins. Only an active, eligible bodybuilding coach
assigned to the client can view private photos or choose which copies to keep.
Administrator access alone does not grant photo access.

## Temporary review and coach choices

Every upload begins in private temporary storage, with a seven-day deadline from
upload. Review photos and their JSON manifests are files, not database photo
records. Client views do not start the coach-view clock. The first authenticated
coach image request records the first-view timestamp. Keeping a photo before an
image request records that coach action as its first view.

Coaches can select any four photos per check-in, across all 13 poses. This limit
is shared by coaches assigned to that client. Each selected copy has one
`progress_photos` record and a private image file. Its expiry is exactly seven
days after its first coach view, rather than seven days after selection. Repeated
views, repeated saves, or removing and selecting the same photo again cannot
extend the deadline. A coach must remove a saved copy to free a slot.

The original temporary review copy retains its upload deadline. It can remain
available after a coach removes a selected copy, until that original deadline.
A selected copy can outlast the temporary review copy if the first coach view
happened later. Selected copies are never counted twice in the dashboard.

Replacing a photo removes its selected copy and resets its viewing state. A new
selection is needed for the replacement. Uploading, replacing or removing photos
still invalidates submission and review status. Coach selection and scheduled
expiry do not invalidate measurements, submission or historical feedback.

Every available photo offers **Download photo** to coaches. The server sends an
authenticated image attachment; phone browsers may offer Save to Files or Save
Image. Copies downloaded to a coach's own device are outside Stage-Ready's
automatic deletion process.

## Cleanup and deployment

Run these commands after updating:

```sh
php artisan migrate --force
php artisan photos:expire
```

The migration backfills legacy upload deadlines from the existing timestamps.
The command moves unexpired, named legacy pose photos into temporary storage,
preserving their original deadline, so coaches can choose which copies to keep.
It removes expired database records and files. Legacy unclassified records are
removed when their backfilled deadlines expire. The command supports a read-only
`--dry-run` option.

`photos:expire` runs every five minutes without overlapping copies. Run Laravel's
scheduler every minute and supervise a queue worker, as described in
[billing setup](BILLING.md#retention-and-handovers). Expired images are excluded
from listings and blocked immediately, even if the scheduler is late. Physical
cleanup follows on the next run; failed file deletion is retried through the
queue. Check-in deletion and unpaid-coach cleanup also remove temporary files.

Saved files use `checkin_photos`; review files use `temporary_checkin_photos`.
Both disks are private, with public serving disabled. Optional
`CHECKIN_PHOTOS_ROOT` and `TEMPORARY_CHECKIN_PHOTOS_ROOT` configure their roots.
All application workers must access the same files and persistent database or
Redis cache, which provides the per-check-in locks. Database locks enforce the
four-copy limit, while rollback compensation preserves the old photo after a
failed upload or save.

Backups, replicas and storage snapshots need their own retention controls.
Restores must not revive expired files. Queue failures should be monitored.
