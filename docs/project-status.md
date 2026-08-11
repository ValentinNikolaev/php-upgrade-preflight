# Project status and licensing

## Public beta

PHP Upgrade Preflight is a public beta. The v0.2.x packages are released and their documented contracts are tested, but the public PHP API, CLI and Artisan surfaces, adapter extension points, package boundaries, and report semantics are still being proven before `1.0`.

Public beta is not a production-readiness claim. The analyzer produces decision-support evidence. It does not modify the target project, perform the upgrade, boot or execute the analyzed application, prove runtime compatibility, or guarantee a successful deployment. Users must review every report and validate any resulting upgrade with the application's own tests, runtime checks, security review, and deployment process.

## v0.2.x compatibility commitment

Within the released v0.2.x line, patch releases preserve:

- the public PHP operation;
- CLI and Artisan behavior;
- adapter discovery metadata;
- the documented exit policy;
- report schema `0.7` compatibility;
- supported framework-transition claims.

Bug fixes, security fixes, dependency maintenance, evidence corrections, and documentation changes may change individual findings or diagnostics while preserving those contracts. Published schemas, signed artifacts, and archived compatibility evidence remain immutable.

## v0.3 change boundary

The planned v0.3 line is a new `0.MINOR` release during SemVer's initial-development phase. It may introduce documented breaking changes to request inputs, report shape, package constraints, and adapter extension points. Planned v0.3 work includes schema `0.8`, explicit target-platform profiles, and stage-scoped Composer evidence; none of those plans describes current v0.2.x behavior.

Every intentional v0.3 compatibility break must be identified in the changelog and migration documentation. Historical v0.2 schemas and signed compatibility artifacts remain available as immutable evidence rather than being rewritten for v0.3.

## Source-available licensing

PHP Upgrade Preflight is source-available software. It is not distributed or described as Open Source.

The [PolyForm Noncommercial License 1.0.0](../LICENSE) permits the uses defined there as noncommercial without a commercial license fee. Commercial use is not permitted under that license and requires a separate license from the copyright holder.

This page describes the project's product and licensing position; it does not replace or modify the license. The license text controls if this summary and the license differ.
