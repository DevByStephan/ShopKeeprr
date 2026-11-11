# Store models

Choose a store model in **Admin → Settings**. The setting can be changed later without a migration.

| Model | What changes |
| --- | --- |
| Retail | Standard catalog, cart and checkout |
| B2B wholesale | Trade applications, tier pricing, hidden guest prices and minimum order quantities |
| Subscription box | Recurring plans, customer subscriptions and renewal state |
| Multi-vendor marketplace | Approved sellers, seller portal, per-seller commission and payout records |

## Retail

The default catalog experience. Products use variants, inventory, shipping rules, coupons and the normal checkout. Guest checkout can be enabled or disabled independently.

## B2B wholesale

Wholesale customers can apply for a trade account. Approved accounts receive tier prices and can order against minimum quantities. Guest prices can be hidden until the account is approved.

## Subscription box

Products can carry recurring plans. Customers manage their own subscription state, and the admin can review renewals and grace periods. Payment readiness and renewal handling should be tested before opening subscriptions.

## Multi-vendor marketplace

The marketplace adds seller accounts, per-seller product ownership and commission. Create sellers in **Admin → Vendors**, link each seller to a customer account and configure the payout destination.

Sellers sign in to the seller workspace to manage their own catalog and view their orders. A seller's products disappear from the storefront when the seller is suspended or not approved.

Commission can be a percentage, a fixed amount per seller order or a combination. Seller-level settings can override the platform rule. Commission is calculated from the discounted item subtotal; shipping and tax remain with the platform.

### Seller payouts

Every sale records the gross amount, commission and seller share. A payout records the seller, amount, currency, destination and the sales it settled. Sellers can review their payout history in the seller workspace.

A seller must have a valid, verified payout destination before an administrator pays the balance. The payment provider and its approval requirements are configured on the Store, not in this repository.

## Storefront behavior

The active store model changes catalog behavior but keeps one shared storefront, cart and account system. The four themes and the storefront builder apply across models.

## Before launch

- Test the checkout and account flow in the selected model.
- Verify seller permissions and data isolation for multi-vendor stores.
- Test commission and payout records with test orders.
- Confirm tax, shipping and refund behavior.
- Review the relevant legal pages for the model you operate.
