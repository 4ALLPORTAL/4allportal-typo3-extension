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

## Threat model

Threats, trust boundaries, mitigations and known open findings are documented in the
[threat model](docs/security/threat-model.md).

## Verifying releases

Every GitHub release ships the TER archive together with a CycloneDX SBOM (`*.cdx.json`).

The SBOM lists the runtime dependencies in the exact versions from `composer.lock` that the
release was built and tested against. The archive itself contains no third-party code: Composer
installs TYPO3 and its dependencies on your side within the constraints of `composer.json`, so
the versions in your installation may differ. Generate an SBOM from your own project's
`composer.lock` for an accurate inventory of what you run.

The archive is covered by two independent provenance documents and an SBOM attestation:

- a [SLSA Build Level 3](https://slsa.dev/spec/v1.0/levels#build-l3) provenance
  (`*.intoto.jsonl`), generated in an isolated job the build cannot influence. Verify it with
  [slsa-verifier](https://github.com/slsa-framework/slsa-verifier):

  ```bash
  gh release download v<version> --repo 4ALLPORTAL/4allportal-typo3-extension \
    --pattern '*.zip' --pattern '*.intoto.jsonl'
  slsa-verifier verify-artifact fourallportal_typo3_extension_<version>.zip \
    --provenance-path fourallportal_typo3_extension_<version>.zip.intoto.jsonl \
    --source-uri github.com/4ALLPORTAL/4allportal-typo3-extension \
    --source-tag v<version>
  ```

- GitHub artifact attestations for build provenance and the SBOM, verifiable with the GitHub
  CLI:

  ```bash
  gh attestation verify fourallportal_typo3_extension_<version>.zip \
    --repo 4ALLPORTAL/4allportal-typo3-extension
  ```

  Their Sigstore bundles are attached to the release as well (`*.provenance.sigstore.json`,
  `*.sbom.sigstore.json`), so verification also works without access to the GitHub
  attestations API by passing `--bundle <file>` to the command above.

The release pipeline uploads exactly this file to the TER. To apply the provenance to a TER
download, compare its SHA-256 with the archive on the GitHub release.

Releases up to and including 2.0.1 have no attestations.
