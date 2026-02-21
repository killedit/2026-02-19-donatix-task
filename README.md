# Consuming an existing REST API

Steps to init the project, connect to the db, ect. are the same as in my other repos.</br>
Should run on any machine 🐋

`http://localhost:8010/`

```bash
docker compose exec laravel php artisan app:sync-bookings
```

![Parser](/resources/images/2026-02-19-donatix-task-parser.png)
![Parser](/resources/images/2026-02-19-donatix-task-db.png)

## Unit tests

```bash
php artisan test tests/Unit/SyncBookingJobTest.php
```
