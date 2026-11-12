# Marketplace

The ShopKeeprr core is free. Approved sellers can list modules, themes and storefront components for ShopKeeprr stores. Listings, releases and payouts are managed through the ShopKeeprr Store developer workspace.

## Seller application

A seller account requires:

- A verified ShopKeeprr account email.
- A valid, verified payout email.
- Acceptance of the developer agreement and marketplace terms.
- A completed application, reviewed by the marketplace team.

The optional application fee is non-refundable. When it is paid, the PayPal payer email becomes the locked payout email for that seller account. Use the help desk if a locked email must be changed.

## Listing a product

1. Create the listing in **My listings**.
2. Add the name, tagline, description, features, cover image and pricing.
3. Upload the first release ZIP.
4. Confirm the compatibility range and release notes.
5. Submit the listing for review.

A listing is not public until the marketplace team publishes it. The current release becomes the default download for customers.

## Licensing

The core application is free to download. For paid extensions, the marketplace handles license keys, activation limits and private downloads. Your package does not need its own license server or entitlement database.

- Use the product slug and version in package metadata.
- Do not hard-code customer credentials.
- Direct customers to their ShopKeeprr account for licenses and downloads.
- Do not redistribute a customer's license or signed download URL.

Internal license-service administration is not part of this public repository.

## Installing from the platform

1. In Admin → Marketplace, choose **Connect Store**.
2. Sign in to the Store and approve the one-time device code.
3. Browse the catalog, choose an extension and select **Install**.

The platform downloads the package server-side, verifies its SHA-256 checksum,
validates the manifest slug and installs the module disabled. Enable it from
Plugins when you are ready. Manual ZIP upload remains available when the store
cannot reach the Store.

## Commission and payouts

The marketplace keeps a percentage of each completed sale. The default rate is published in the seller dashboard; a seller may have an individual rate. The rate is snapshotted on the sale, so later changes do not rewrite history.

- **Gross** is the product line total.
- **Commission** is gross × rate.
- **Seller share** is the remainder.

Refunded orders are marked reversed and removed from the payable balance. A payout records the seller, amount, currency, destination, sales settled and provider reference.

Sellers must have a verified payout email. The marketplace team sends the pending balance and records the transfer in the seller's payout history. A displayed schedule is a target, not a guarantee; refunds, verification issues or provider delays can move a payout.

## Review and support

The review team checks the listing, package contents, compatibility and security scan. Scanner 1.1 blocks critical execution findings. Warnings are visible to the seller and the review team; console and command shell calls are warnings so legitimate developer tooling is not rejected. The marketplace is open at launch and can be tightened later without changing the install contract.

Questions about a listing, release or payout: <info@shopkeeprr.com>. Include the listing slug and version. Never send a private signing key, full license key or customer data.
