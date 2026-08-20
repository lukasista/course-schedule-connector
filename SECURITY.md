# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 0.x | Yes — pre-release, fixes land on `main` |

## Reporting a vulnerability

**Please do not open a public issue for a security problem.**

Report it privately through [GitHub Security Advisories](https://github.com/lukasista/wpdissi/security/advisories/new), or by e-mail to the address in `composer.json`.

Please include:

- a description of the issue and its impact,
- the plugin version and the WordPress and PHP versions,
- steps to reproduce, or a proof of concept.

You can expect an acknowledgement within 72 hours and an assessment within seven days. If the report is confirmed, a fix will be released and you will be credited in the changelog unless you ask otherwise.

## Scope

In scope: this plugin's code, its REST routes, its admin screens, and its handling of data retrieved from the configured iSport System endpoint.

Out of scope: vulnerabilities in WordPress core, in Divi, or in the remote iSport System installation itself. Please report those to their respective vendors.

## Security model

- The plugin makes **outbound** requests only, to a host explicitly configured by an administrator, over HTTPS with certificate verification enabled.
- No visitor data is transmitted to the remote service.
- All data received from the remote service is treated as untrusted input: it is type-normalised on ingest and escaped on output.
- Administrative actions require both a capability check and a nonce.
- The plugin evaluates no remote code and writes no executable file.

See [`docs/SECURITY-CHECKLIST.md`](docs/SECURITY-CHECKLIST.md) for the control-by-control detail.
