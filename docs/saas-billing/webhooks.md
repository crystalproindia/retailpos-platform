# Webhooks

`POST /api/saas-billing/razorpay/webhook` receives raw Razorpay payloads. The HMAC signature is verified before queueing; raw payload is encrypted at rest. Provider event IDs are unique, replay-safe, and processed asynchronously. Invalid signatures never create a payment or renew a subscription.
