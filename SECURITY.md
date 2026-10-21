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

Every GitHub release ships the TER archive together with a CycloneDX SBOM (`*.cdx.json`). Both are
covered by signed build provenance and SBOM attestations, which can
be verified with the GitHub CLI:

```bash
gh attestation verify fourallportal_typo3_extension_<version>.zip \
  --repo 4ALLPORTAL/4allportal-typo3-extension
```
