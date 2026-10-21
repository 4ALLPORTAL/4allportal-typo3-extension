# Security Policy

## Supported versions

| Version | Supported          |
|---------|--------------------|
| 2.x     | :white_check_mark: |
| 1.x     | :x:                |

## Reporting a vulnerability

Please do **not** open a public issue for security problems.

Report vulnerabilities privately via
[GitHub Security Advisories](https://github.com/4ALLPORTAL/4allportal-typo3-extension/security/advisories/new).
We acknowledge reports within a few working days and keep you informed until a fix is released.

## Verifying releases

Every GitHub release ships the TER archive together with a CycloneDX SBOM (`*.cdx.json`).

The SBOM lists the runtime dependencies in the exact versions from `composer.lock` that the
release was built and tested against. The archive itself contains no third-party code: Composer
installs TYPO3 and its dependencies on your side within the constraints of `composer.json`, so
the versions in your installation may differ. Generate an SBOM from your own project's
`composer.lock` for an accurate inventory of what you run.

The archive carries signed build provenance and SBOM attestations, which can be verified with the
GitHub CLI after downloading it from the release:

```bash
gh release download v<version> --repo 4ALLPORTAL/4allportal-typo3-extension --pattern '*.zip'
gh attestation verify fourallportal_typo3_extension_<version>.zip \
  --repo 4ALLPORTAL/4allportal-typo3-extension
```

The Sigstore bundles of both attestations are attached to the release as well
(`*.provenance.sigstore.json`, `*.sbom.sigstore.json`), so verification also works without
access to the GitHub attestations API by passing `--bundle <file>` to the command above.

Releases up to and including 2.0.1 have no attestations.
