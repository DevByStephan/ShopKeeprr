# Contributing

This repository is for public documentation and example templates. The ShopKeeprr application source is not maintained here.

## Useful contributions

- Corrections to installation, update, theme or module documentation.
- Improvements to the example module or theme.
- Clear, reproducible bug reports about the documentation or examples.
- Accessibility fixes in example templates.

## Before opening a pull request

1. Check the existing issue or open one describing the change.
2. Keep the change focused.
3. Confirm no credentials, customer data, release archives or license material are included.
4. Test any PHP example with `php -l` and any JSON manifest with a JSON parser.
5. Update the relevant Markdown page and `CHANGELOG.md` when behavior changes.

## Do not submit

- Application source or buyer release files.
- `.env`, API keys, license keys, signing keys or customer exports.
- ZIP, update or paid release archives.
- Unrelated planning, pricing experiments or private business content.
- Content unrelated to the ShopKeeprr public release.

## Style

- Use US English and sentence case for headings.
- Prefer short paragraphs and copyable code blocks.
- Use `php` fenced blocks for PHP and `json` for manifests.
- Link to live ShopKeeprr pages instead of duplicating legal terms or prices.
- Keep examples self-contained and free of unrelated dependencies.

## Security

Do not open a public issue for a vulnerability. Follow [SECURITY.md](SECURITY.md).

## Review

A documentation or example change is reviewed for accuracy, security and scope. Examples must be safe to copy into a staging store; they must not assume access to customer data or private services.
