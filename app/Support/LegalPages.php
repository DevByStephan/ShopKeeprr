<?php

namespace App\Support;

/**
 * Plain-text starter policies for a new store.
 *
 * These are deliberately generic templates. They are not legal advice and
 * should be reviewed by a qualified professional before a store goes live.
 */
class LegalPages
{
    public static function terms(string $store, string $email, string $address = ''): string
    {
        $address = $address !== '' ? $address : '[Business address]';

        return <<<TEXT
## 1. About these terms

These Terms of Service ("Terms") govern your access to and use of the {$store} website and the products and services offered through it. By placing an order or creating an account, you agree to these Terms.

If you do not agree with these Terms, please do not use the store.

## 2. Who we are

{$store} operates this store. You can contact us at {$email} or at {$address}.

## 3. Orders and acceptance

An order is an offer to buy. We may accept or decline an order at our discretion, including where a product is unavailable, a price was listed in error, or we suspect fraud. If we decline an order, we will refund any payment already taken.

Order confirmations are sent by email. Please check your spam folder and contact us if you do not receive one.

## 4. Pricing and payment

Prices are shown in the store currency and may change without notice. Taxes, shipping and other charges are shown at checkout where applicable.

Payment must be completed before an order is dispatched unless we expressly agree otherwise. Payments are processed by third-party payment providers. We do not store full payment card numbers on our servers.

## 5. Shipping and delivery

Delivery estimates are provided in good faith but are not guaranteed. Risk in the goods passes to you on delivery. If an order is delayed or lost in transit, contact us and we will help resolve it.

## 6. Returns and refunds

Our returns policy is set out on the Shipping & Returns page. Where consumer law gives you additional rights, those rights apply in addition to this policy.

To start a return, contact {$email} with your order number. Products should be returned in the condition required by our returns policy.

## 7. Accounts

You are responsible for keeping your account credentials secure and for activity that occurs under your account. Tell us immediately if you believe your account has been compromised.

We may suspend or close an account that breaches these Terms, engages in fraud, or creates a risk for other customers or the store.

## 8. Acceptable use

You must not:

- use the store for unlawful purposes;
- attempt to gain unauthorised access to the store or another customer's account;
- scrape, copy or resell store content without permission;
- upload malicious code or interfere with the operation of the store; or
- harass, threaten or impersonate another person.

## 9. Intellectual property

The store design, text, images, logos and other content are owned by or licensed to {$store}. You may use the store for normal shopping purposes, but you may not reproduce or redistribute our content without written permission.

## 10. Third-party services

The store may link to or use third-party services, including payment processors, email providers and delivery companies. Those services have their own terms and privacy policies. We are not responsible for their independent acts or omissions.

## 11. Limitation of liability

To the maximum extent permitted by law, {$store} is not liable for indirect, incidental or consequential losses arising from your use of the store. Nothing in these Terms excludes liability that cannot lawfully be excluded, including liability for death or personal injury caused by negligence.

## 12. Governing law

These Terms are governed by the laws of [Governing jurisdiction]. Any dispute will be handled by the courts of that jurisdiction, unless mandatory consumer law requires otherwise.

## 13. Changes

We may update these Terms from time to time. The version published on this page applies from the date it is posted. Material changes will be communicated where reasonably possible.

## 14. Contact

Questions about these Terms can be sent to {$email}.
TEXT;
    }

    public static function privacy(string $store, string $email, string $address = ''): string
    {
        $address = $address !== '' ? $address : '[Business address]';

        return <<<TEXT
## 1. Who we are

{$store} ("we", "us") operates this store. This Privacy Policy explains what personal data we collect, why we collect it, how we use it and the choices you have.

Contact us about privacy at {$email} or {$address}.

## 2. Data we collect

We may collect:

- Account data: name, email address, phone number, password hash, company name, tax ID and account preferences.
- Order data: products purchased, order value, delivery and billing addresses, order notes and communication about an order.
- Payment data: payment method, transaction reference and status. Card numbers are handled by our payment processors and are not stored on our servers.
- Technical data: IP address, browser and device information, pages visited, referring URL and security logs.
- Marketing data: email preferences and consent records.
- Support data: messages you send to us and the information you choose to include.

If the store owner enables installation tracking, our store software sends the software vendor a separate installation record containing a pseudonymous install ID, the store URL, administrator email, software version, store model, theme, PHP version, database type and module count. It does not include customer records, orders or payment details. The install ID is not an anonymity guarantee because the administrator email is included. The vendor uses it to support the software and understand version adoption. Installation tracking is disabled by default; the store owner can leave it disabled or change the endpoint when configuring the software.

## 3. How we use personal data

We use personal data to:

- create and manage accounts;
- process, pack, deliver and support orders;
- process payments and prevent fraud;
- handle returns, refunds and warranty requests;
- send transactional messages such as order confirmations and shipping updates;
- send marketing messages where you have consented or where the law permits;
- improve the store, diagnose problems and measure performance; and
- comply with legal, tax and accounting obligations.

## 4. Legal bases

Where data protection law requires a legal basis, we rely on one or more of the following:

- performance of a contract (for example, fulfilling an order);
- legitimate interests (for example, securing the store and improving our services);
- consent (for example, optional marketing emails); and
- legal obligation (for example, tax records).

## 5. Sharing personal data

We may share data with:

- payment processors such as Stripe or PayPal;
- delivery and fulfilment providers;
- email and hosting providers;
- fraud prevention and security providers;
- professional advisers; and
- authorities where required by law.

We require service providers to protect personal data and to use it only for the services they provide to us. We do not sell personal data.

## 6. International transfers

Some service providers may process data outside your country. Where required, we use appropriate safeguards such as standard contractual clauses or equivalent protections.

## 7. Retention

We keep personal data only as long as needed for the purposes described above. Order and tax records may be kept for the period required by law. Security logs and analytics data are kept for a shorter period unless needed to investigate an incident.

## 8. Your rights

Depending on your location, you may have the right to:

- access the personal data we hold about you;
- correct inaccurate data;
- request deletion of your data;
- restrict or object to certain processing;
- receive a portable copy of your data; and
- withdraw consent for marketing at any time.

To exercise a right, contact {$email}. We may need to verify your identity before responding. You also have the right to complain to your local data protection authority.

## 9. Cookies and similar technologies

We use cookies and similar technologies to keep you signed in, remember your cart, secure forms, measure store performance and, where permitted, support marketing. You can control cookies through your browser settings. Blocking essential cookies may prevent parts of the store from working.

## 10. Marketing

You can unsubscribe from marketing emails using the link in any marketing message or by contacting us. Transactional messages about an order are not marketing and may still be sent.

## 11. Security

We use administrative, technical and physical safeguards designed to protect personal data, including encrypted connections, access controls, password hashing and security logging. No method of transmission or storage is completely secure.

## 12. Children

The store is not directed at children under 16, and we do not knowingly collect their personal data. Contact us if you believe a child has provided data and we will take appropriate steps.

## 13. Changes

We may update this Privacy Policy from time to time. The version published on this page applies from the date it is posted. Material changes will be communicated where reasonably possible.

## 14. Contact

For privacy questions or requests, contact {$email}.
TEXT;
    }
}
