# Packaging and releases

ShopKeeprr core releases and marketplace extension packages are versioned ZIP files. The core is published free on download.shopkeeprr.com. Extension packages remain private to the Store and are never published in this documentation repository.

## Core releases

The free core package is downloaded directly from
<https://download.shopkeeprr.com/>. The release notes and
SHA-256 checksum are published beside the download. The core package is allowed
on the public core GitHub release; extension packages and signing material are
not.

## Versioning

Use semantic versions: `MAJOR.MINOR.PATCH`.

- Increment `MAJOR` for incompatible changes.
- Increment `MINOR` for backwards-compatible features.
- Increment `PATCH` for backwards-compatible fixes.

Never reuse a version number for different bytes. If a release is broken, upload a corrected version with a new number and explain the change in the release notes.

## Package contents

The following package layout applies to marketplace extension ZIPs. A core
release is a complete application package instead.

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

Uploads are inspected without being executed or fully extracted. Scanner 1.1 blocks critical execution findings:

- `eval()` and equivalent dynamic execution.
- Critical shell and process-control findings. Console and command shell calls
  are warnings in 1.1 so legitimate developer tooling is not rejected.
- Remote includes.
- Request-data `extract()`.
- PHP hidden inside images or non-PHP files.
- Executable `.htaccess` rules.

It warns about risky APIs, encoded payloads, nested archives, bundled dependencies and truncated scans. Per-file scans are capped at 2 MB and the total read is capped at 32 MB. A blocked release cannot become current, be published or be downloaded by customers. A warning release can be approved with warnings, but the report should be reviewed before the next version.

The scan is a safety net, not a guarantee. A module is trusted code and can still contain logic the scanner cannot detect.

## Upload and review

1. Open the listing in **My listings**.
2. Upload the ZIP under **Releases**.
3. Review the scan report and correct any critical findings.
4. Submit the release and listing for review.
5. The marketplace team publishes it when the listing and package are ready.

The Store records a SHA-256 checksum for each extension package and serves licensed downloads privately. The free core ZIP may be attached to the public core release and downloaded from shopkeeprr.com; never attach an extension package or its signing material to a public release.

## Compatibility

Declare the supported ShopKeeprr and PHP ranges in the package manifest. Test against a clean installation and a representative catalog before submitting. A package that excludes the current release is flagged with a compatibility warning.
