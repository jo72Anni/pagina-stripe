curl -X POST http://tuoserver/webhook.php \
  -H "Content-Type: application/json" \
  -H "Stripe-Signature: simulated" \
  -d '{
    "id": "evt_test_123",
    "type": "checkout.session.completed",
    "data": {
      "object": {
        "id": "cs_test_123",
        "customer_details": {
          "email": "test@example.com",
          "name": "Mario Rossi"
        },
        "amount_total": 1000,
        "payment_status": "paid"
      }
    }
  }'
