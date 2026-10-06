KIRBY SHOP AUDIT — TOP 5 ISSUES TO FIX
=======================================

Context
-------
This project uses Kirby as the commerce backend and Stripe Checkout for payments.
The frontend may later be replaced by Astro, but this audit focuses only on the five highest-priority backend/shop issues that should be fixed first.

Goal
----
Review and refactor the current Kirby shop implementation so that checkout, stock handling, pricing, and order creation are reliable enough for production use.

Do not rewrite the entire shop architecture unnecessarily.
Preserve the current lightweight Kirby + Stripe approach where possible.

==================================================
1. STRIPE ORDER FINALIZATION MUST NOT DEPEND ON THE BROWSER
==================================================

Current problem
---------------
The current flow creates/finalizes the order after Stripe redirects the customer back to the website success page.

In practice, the flow is approximately:

Customer pays
→ Stripe confirms payment
→ Stripe redirects to /success
→ /success verifies the Stripe session
→ Kirby creates the order
→ Kirby decreases stock
→ confirmation emails are sent

This is unsafe because the customer may:
- close the browser after paying;
- lose their network connection;
- never load the success page;
- refresh or revisit the success URL unexpectedly.

A payment may therefore succeed in Stripe while no corresponding Kirby order is created.

Required fix
------------
Move authoritative payment finalization to a Stripe webhook.

Implement a webhook endpoint, for example:

POST /api/stripe/webhook

The webhook must:

1. Read the raw Stripe webhook payload.
2. Verify the Stripe webhook signature using the configured webhook secret.
3. Handle the appropriate successful Checkout event, typically checkout.session.completed.
4. Verify that the session/payment is actually paid.
5. Find the corresponding Kirby pending order using a persistent order ID stored in Stripe metadata.
6. Finalize the order only once.
7. Update inventory.
8. Mark the Kirby order as paid.
9. Send confirmation emails.
10. Return a valid HTTP response to Stripe.

The success page must become presentation-only.

It may display:
- payment successful;
- order number;
- order details.

It must NOT be responsible for creating or finalizing the order.

Important
---------
Webhook handling must be idempotent.

Stripe can send the same event multiple times.
Receiving the same webhook twice must never:
- create duplicate orders;
- decrease stock twice;
- send duplicate fulfillment logic.

==================================================
2. FIX THE STOCK RACE CONDITION / OVERSELLING RISK
==================================================

Current problem
---------------
Stock is checked before redirecting the customer to Stripe, but stock is only decreased after payment.

Example:

Stock = 1

Customer A checks stock → available
Customer B checks stock → available

Customer A pays
Customer B pays

Both purchases were accepted even though only one unit existed.

The current use of:

max(0, $currentStock - $qty)

does not prevent overselling.
It only hides negative stock values.

Required fix
------------
Design stock updates so that two simultaneous purchases cannot silently oversell inventory.

At minimum:

1. Revalidate stock immediately before creating the Stripe Checkout session.
2. Revalidate stock again when processing the successful Stripe webhook.
3. Never silently clamp an invalid stock result to zero.
4. Detect when requested quantity is no longer available.
5. Log and explicitly handle stock conflicts.

Preferred solution
------------------
Use a locking/reservation strategy suitable for Kirby's file-based architecture.

Possible approaches include:
- an application-level file lock around stock validation + update;
- temporary stock reservations with expiration;
- another safe atomic mechanism suitable for Kirby.

The important requirement is that this operation:

read current stock
→ verify availability
→ decrement stock

must behave atomically from the perspective of concurrent checkout finalization.

Document the chosen strategy in the code.

==================================================
3. REVALIDATE CURRENT PRODUCT PRICES AT CHECKOUT
==================================================

Current problem
---------------
When a product is added to the cart, its current price is stored in the session.

Later, checkout uses the price stored in the session when building Stripe line items.

This means the session can contain an outdated price.

Example:

Monday:
Product price = €100
Customer adds it to cart.

Tuesday:
Admin changes product price to €120.

Wednesday:
Customer completes checkout.

Current behavior may charge €100 because that is the value stored in the cart session.

Although the browser is not directly controlling the price, the session is being treated as the source of truth when it should not be.

Required fix
------------
At checkout, reconstruct all authoritative commerce data directly from Kirby.

The cart should provide only the minimum client/session state required, such as:

- product UUID or product identifier;
- quantity;
- selected variant identifier, if applicable.

Before creating Stripe Checkout:

1. Retrieve each product from Kirby.
2. Verify the product still exists and is purchasable.
3. Read its current authoritative price.
4. Read its current stock.
5. Validate the requested quantity.
6. Resolve current variant information where relevant.
7. Build Stripe line items exclusively from this freshly retrieved data.

Never trust the following values from the browser or an old session:
- price;
- subtotal;
- product title for billing purposes;
- stock;
- total;
- discount amount.

Kirby must remain the source of truth.

==================================================
4. MAKE ORDER FINALIZATION RESILIENT TO PARTIAL FAILURE
==================================================

Current problem
---------------
Order finalization currently performs several independent operations, such as:

- update product A stock;
- update product B stock;
- update product C stock;
- create the order;
- send emails.

Kirby is file-based, so these operations are not wrapped in a normal database transaction.

A failure in the middle can leave the system in an inconsistent state.

Example:

Product A stock updated successfully.
Product B stock updated successfully.
Product C update fails.
Order creation never completes.

The shop is now partially modified.

Required fix
------------
Refactor finalization into a robust, explicit state-based process.

Recommended order lifecycle:

pending
→ payment_confirmed
→ inventory_processed
→ completed

Or an equivalent state model.

Requirements:

1. Create a persistent pending order BEFORE redirecting to Stripe.
2. Give it a unique immutable internal identifier.
3. Store the cart/order snapshot required to finalize it later.
4. Put that order ID into Stripe Checkout metadata.
5. When Stripe sends the successful webhook:
   - retrieve that pending order;
   - verify it has not already been finalized;
   - process inventory safely;
   - persist progress/state;
   - mark payment/finalization status;
   - send emails after the critical commerce state is safely persisted.
6. If a step fails, preserve enough state to diagnose or retry it.
7. Log errors clearly.

Do not design the flow so that failure halfway through leaves no record of what happened.

Email delivery should not determine whether an otherwise valid paid order exists.
If email sending fails, the paid order should remain recorded and the email failure should be recoverable separately.

==================================================
5. FIX ORDER NUMBER GENERATION
==================================================

Current problem
---------------
The current order number is generated approximately by counting existing orders and adding one:

existing order count + 1

This is not concurrency-safe.

Example:

There are currently 127 orders.

Two payments finalize at almost the same time.

Request A reads count = 127 → assigns #0128
Request B reads count = 127 → assigns #0128

This can produce duplicate human-facing order numbers.

Required fix
------------
Separate the internal unique identifier from the display order number.

Every order must first receive a collision-resistant internal ID, for example:
- UUID;
- ULID;
- another appropriate unique ID.

Example:

internal_id:
01J9XYZ...

A human-readable order number can then be generated separately, for example:

KS-2026-000128

If sequential order numbers are required, their generation must use a locking or otherwise concurrency-safe mechanism.

Requirements:

1. Internal order IDs must always be unique.
2. Stripe metadata must reference the immutable internal ID, not a fragile order count.
3. Human-facing order numbers must not be generated from children().count() + 1 without locking.
4. Existing order lookup must remain reliable even under simultaneous payments.

==================================================
EXPECTED RESULT
==================================================

After these five fixes, the shop should behave approximately as follows:

1. Customer submits cart.
2. Kirby receives only product identifiers, quantities, and required variant identifiers.
3. Kirby reloads current products.
4. Kirby validates current price and stock.
5. Kirby creates a persistent pending order with a unique internal ID.
6. Kirby creates a Stripe Checkout session containing that order ID in metadata.
7. Customer pays on Stripe.
8. Stripe sends a signed webhook directly to Kirby.
9. Kirby finds the pending order.
10. Kirby verifies that it has not already been processed.
11. Kirby safely revalidates and updates stock.
12. Kirby marks the order as paid/completed.
13. Kirby sends confirmation emails.
14. The browser success page only displays the resulting order state.

==================================================
IMPLEMENTATION CONSTRAINTS
==================================================

- Keep Kirby as the source of truth for products, prices, inventory, and orders.
- Keep Stripe Checkout unless there is a strong technical reason to replace it.
- Do not trust browser-provided prices or totals.
- Do not depend on a browser redirect to confirm payment.
- All webhook processing must be idempotent.
- Prefer small, understandable PHP code over unnecessary abstractions.
- Account for Kirby's file-based storage model.
- Preserve compatibility with a future Astro frontend/API architecture.
- Add clear error handling and useful logs.
- Avoid silently swallowing commerce inconsistencies.

Before changing code, inspect the current implementation and identify all affected files and functions.
Then implement the changes incrementally and explain any architectural decision that differs from the recommendations above.
