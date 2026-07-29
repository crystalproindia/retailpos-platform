# Trials

Trials use plan trial and grace-day settings. The lifecycle job sends idempotent reminders at 7, 3, and 1 day, then moves an ended trial to grace or expired. Platform administrators can extend eligible trials for 1–365 days with a required reason. Repeated scheduler runs do not duplicate reminders or transitions.
