# Security

## Supported versions

Security fixes are applied to the current public release and the latest supported update path. This repository contains documentation and examples; the free core application is distributed through [shopkeeprr.com](https://shopkeeprr.com/), and extensions through [ShopKeeprr Store](https://store.shopkeeprr.com/).

## Reporting a vulnerability

Email **info@shopkeeprr.com** with:

- The affected version and component.
- Steps to reproduce.
- The impact and any suggested mitigation.
- Whether the issue is already public.

Do not open a public issue for a vulnerability. Do not include live credentials, customer data, private license keys or full payment details. We will acknowledge the report and coordinate a fix before public disclosure.

## Modules are trusted code

Modules are PHP packages, not sandboxed plugins. A module can read or change the database, files and external services with the store's privileges. Its migrations run automatically during installation.

Only install modules from authors you trust. Take a database backup before installation and review the package source. The admin requires an explicit trust confirmation for this reason.

## Update integrity

Updates are delivered as signed release metadata and a ZIP package. The application accepts a release only when:

- The manifest is fetched over HTTPS.
- The metadata signature verifies with the pinned public key.
- The package matches the signed SHA-256 hash and byte size.
- Every file matches the signed manifest.
- Paths stay inside the application and protected areas are rejected.

Do not replace the pinned public key with one you did not receive from the vendor. Before changing files, the updater enables maintenance mode and writes a verified database dump plus a ZIP of replaced files.

## Demo data

Demo customer passwords are generated at installation and stored in `storage/app/demo-credentials.txt` outside the web root. Delete demo accounts and that file before going live.

## Hardening baseline

- Serve only `public/` from the web root.
- Set `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_URL` and secure session cookies.
- Use a dedicated database user.
- Use HTTPS.
- Configure SMTP you control and test password reset.
- Put payment providers in live mode only after testing the full flow.
- Back up the database and uploads, test a restore and keep an off-server copy.
- Remove or protect `public/install.php`.
- Remove unused administrator, seller and demo accounts.

## Scope

This policy covers the ShopKeeprr application, marketplace, licensing, downloads, seller accounts, updates and documentation examples. The hosted payment provider handles payment credentials; ShopKeeprr must not store full card numbers or provider passwords.
