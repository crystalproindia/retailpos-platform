# Razorpay Test Mode

Razorpay is implemented behind the `PaymentGateway` contract. Platform administrators configure only encrypted test credentials through the SaaS Billing Gateway screen. Saved key secret and webhook secret are never displayed. Live mode is rejected by this release, and the configuration validation action does not create an order or charge money.
