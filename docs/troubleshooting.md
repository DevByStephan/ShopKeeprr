# Troubleshooting

## The installer cannot write `.env`

Check that the application root is writable by the PHP user. If the host blocks writing environment files, use the command-line install path and create `.env` manually from `.env.example`.

## The installer says `proc_open` is disabled

The browser installer can show the equivalent command-line steps instead of running them. Run `php artisan migrate --force` and `php artisan shopkeeprr:install` from the server shell. Do not enable unnecessary process functions just to satisfy the installer.

## The storefront returns a 500 after installation

1. Set `APP_DEBUG=true` temporarily on a staging copy, not production.
2. Check the PHP error log and `storage/logs/laravel.log`.
3. Confirm `APP_KEY` exists and `storage/` and `bootstrap/cache/` are writable.
4. Clear compiled views, routes and config.
5. Verify the document root points at `public/`.

Remove debug output before returning the site to production.

## An update stopped or timed out

Reload **Admin → Updates** and read the recorded state. Do not start another update while a run is active. If the state is `Rolled back`, the old version should be restored. If it is `Rollback failed`, preserve the logs and backups and contact support.

## A module will not enable

- Confirm `module.json` is at the ZIP root.
- Check the provider class exists and its namespace matches `module.json`.
- Confirm the declared ShopKeeprr and PHP ranges include your installation.
- Review the scan report for a blocking finding.
- Check that the module's routes or migrations do not collide with another package.

## A theme looks broken

- Confirm the preset key exists in `config/themes.php`.
- Check the preset folder path under `resources/views/theme/presets/`.
- Clear compiled views.
- Run the front-end build if CSS or JavaScript changed.
- Test with the default theme to separate theme markup from checkout behavior.

## Email is not arriving

- Send an admin test message and read the error.
- Verify SMTP host, port, encryption and credentials.
- Check the sending domain's SPF, DKIM and DMARC records.
- Look for rate limits or blocked recipients at the mail provider.
- Never send SMTP passwords in a support ticket.

## Checkout starts but does not finish

- Confirm the payment provider is configured in the correct mode.
- Verify the return and webhook URLs use the public HTTPS domain.
- Check the provider's webhook delivery log.
- Confirm the order amount and currency match the local order.
- Do not retry a live payment blindly; reconcile the provider and local order first.

## Support

Include the ShopKeeprr version, PHP version, hosting type, route and exact error. Redact `.env` values, license keys, customer details and payment credentials.

- Support: <info@shopkeeprr.com>
- Security: [SECURITY.md](../SECURITY.md)
- Offline guide: [Installation & FAQ PDF](../ShopKeeprr-Installation-and-FAQ.pdf)
