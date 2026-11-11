# Packaging and releases

Marketplace releases are versioned ZIP packages. The package is private to the Store and is never published in this documentation repository.

## Versioning

Use semantic versions: `MAJOR.MINOR.PATCH`.

- Increment `MAJOR` for incompatible changes.
- Increment `MINOR` for backwards-compatible features.
- Increment `PATCH` for backwards-compatible fixes.

Never reuse a version number for different bytes. If a release is broken, upload a corrected version with a new number and explain the change in the release notes.

## Package contents

A release ZIP must have its manifest at the root:

```text
hello-shopkeeprr/
  module.json
  src/
  routes/
  resources/
  README.md
```

Include:

- Source files needed to run the extension.
- A module or theme manifest.
- A short README with installation and compatibility notes.
- A license or usage statement for the extension.
- Release notes for the current version.

Do not include:

- `.env` or credentials.
- `vendor/` or `node_modules/` when the host can resolve dependencies.
- Local SQLite databases or logs.
- `storage/` caches or backups.
- Customer data, order exports or license keys.
- Compiled assets unless they are required at runtime.
- Nested archives or executable `.htaccess` files.

## Security scan

Uploads are inspected without being executed or fully extracted. The scanner blocks:

- `eval()` and equivalent dynamic execution.
- Shell execution and process control.
- Remote includes.
- Request-data `extract()`.
- PHP hidden inside images or non-PHP files.
- Executable `.htaccess` rules.

It warns about risky APIs, encoded payloads, nested archives, bundled dependencies and truncated scans. A blocked release cannot become current, be published or be downloaded by buyers. A warning release can be approved with warnings, but the report should be reviewed before the next version.

The scan is a safety net, not a guarantee. A module is trusted code and can still contain logic the scanner cannot detect.

## Upload and review

1. Open the listing in **My listings**.
2. Upload the ZIP under **Releases**.
3. Review the scan report and correct any critical findings.
4. Submit the release and listing for review.
5. The marketplace team publishes it when the listing and package are ready.

The Store records a SHA-256 checksum for the package and serves licensed downloads privately. Do not attach the package to a public GitHub release or commit it to a repository.

## Compatibility

Declare the supported ShopKeeprr and PHP ranges in the package manifest. Test against a clean installation and a representative catalog before submitting. A package that excludes the current release is flagged with a compatibility warning.
