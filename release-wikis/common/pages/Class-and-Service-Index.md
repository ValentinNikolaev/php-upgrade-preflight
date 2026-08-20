# Class and Service Index

This index covers every class and interface declared under `packages/*/src`.

It excludes test classes under `packages/*/tests` and the executable script in `packages/cli/bin`.

Use it to find the package boundary and the deeper Wiki page for a symbol. “Value model” means an immutable or validation-focused data object, not a service that performs analysis.

## CLI package

| Namespace and class/interface | Purpose | Deeper page |
| --- | --- | --- |
| `PhpUpgradePreflight\Cli\AdapterManifestReader` | Reads and validates one installed package's adapter declaration | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\Application` | Dispatches the executable to analysis or the interactive wizard | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\AnalyzeCommand` | Runs the generic `upgrade-intel analyze` command | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\AnalyzerFactory` | Factory interface for constructing an analyzer from integrations | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\CommandLineOption` | Value definition for one documented CLI option | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\CommandLineOptions` | Canonical CLI option vocabulary, defaults, modes, and help | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\CommandLineParser` | Validates arguments and creates normalized option data | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\CommandRunner` | Shared executable-command interface | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\ComposerLookupPackageTargetValidator` | Adapts Core package metadata lookup results for wizard choices | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\DefaultAnalyzerFactory` | Creates `DefaultUpgradeAnalyzer` with discovered integrations | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\FrameworkIntegrationRegistry` | Discovers, instantiates, sorts, and selects framework integrations | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\LocalPackageTargetValidator` | Validates package targets against root Composer requirements without a process | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\PackageTargetCandidateProvider` | Optional contract for discovered package-target choices | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\PackageTargetValidation` | Package target found/not-found/no-match/unverified/invalid value | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\PackageTargetValidator` | Package-target validation contract used by the wizard | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\TerminalAnalysisProgressReporter` | Renders Core progress events to terminal-attached stderr | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\WizardCommand` | Collects, reviews, and delegates an interactive analysis request | [[CLI Package Internals|CLI-Package-Internals]] |
| `PhpUpgradePreflight\Cli\WizardInputException` | Typed wizard cancellation or input termination | [[CLI Package Internals|CLI-Package-Internals]] |

## Core analysis namespace

| Namespace and class | Purpose | Deeper page |
| --- | --- | --- |
| `PhpUpgradePreflight\Core\Analysis\AbandonedPackageDetector` | Converts abandoned lock-package metadata into blockers | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Analysis\BlockerGrouper` | Groups solver evidence into structured blockers | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Analysis\ComposerBlockerParser` | Parses Composer conflict transcripts into relations | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer` | Orchestrates the complete analysis pipeline | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Analysis\FrameworkRuleEngine` | Activates adapters and evaluates contained framework rules | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Analysis\LockDiffBuilder` | Compares baseline and candidate Composer locks | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Analysis\ReportAssembler` | Constructs the canonical `UpgradeReport` | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Analysis\ReportSectionBuilder` | Builds derived plan, test, constraint, and uncertainty sections | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Analysis\RiskAndEffortEstimator` | Produces deterministic risk and effort assessments | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Analysis\ScenarioSelector` | Selects and deduplicates direct Composer scenarios | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Analysis\SourceImpactAccumulator` | Merges equivalent source-impact conclusions | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Analysis\SourceImpactBuilder` | Correlates source, ownership, rules, and lock changes | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Analysis\SourceImpactReasonWriter` | Generates stable source-impact reason vocabulary | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Analysis\StageAssessmentBuilder` | Adds source, risk, effort, test, and action data to stages | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Analysis\StageAttemptPlanner` | Creates the bounded attempt sequence for one stage | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Analysis\StageBlockerRegistry` | Tracks blocker identity and lifecycle across attempts | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Analysis\StagedAnalysisPolicy` | Analysis-layer aliases for staged-analysis budget constants | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Analysis\StagedUpgradeOrchestrator` | Carries selected state through an adjacent-stage chain | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Analysis\StageExecutor` | Executes and selects outcomes for one planned stage | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Analysis\StageOutcome` | Internal result containing stage analysis and selected state | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Analysis\StagePlanResolution` | Internal resolved-or-skipped stage-plan value | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Analysis\StagePlanResolver` | Selects and validates one adapter-provided stage plan | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Analysis\TargetNormalizer` | Normalizes requested package and PHP targets | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Analysis\TestGuidanceCatalog` | Supplies deterministic test guidance for report plans | [[Core Service Reference|Core-Service-Reference]] |

## Core Composer namespace

| Namespace and class | Purpose | Deeper page |
| --- | --- | --- |
| `PhpUpgradePreflight\Core\Composer\CandidateLockFileReader` | Fingerprints candidate lock bytes and creates evidence | [[Determinism and Evidence|Determinism-and-Evidence]] |
| `PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner` | Owns Composer process execution, diagnostics, candidate locks, and cleanup | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Composer\ComposerPackageMetadataLookup` | Performs explicit-mode bounded Composer package discovery | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Composer\InvalidJsonException` | Typed invalid Composer JSON failure | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Composer\JsonFileException` | Base exception carrying a Composer JSON path | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Composer\JsonFileReader` | Strictly reads Composer JSON objects | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Composer\MissingJsonFileException` | Typed missing Composer JSON file failure | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Composer\PackageMetadataLookupMode` | Cache-only and project-repository lookup vocabulary | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Composer\PackageMetadataLookupResult` | Invalid/found/not-found/unverified package metadata result | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Composer\ProjectStateBuilder` | Loads manifest and lock data into project state | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Composer\ProjectStateLoadResult` | Value carrying project state plus optional load failure | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Composer\ScenarioOutcome` | Internal classified scenario failure/outcome pair | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Composer\ScenarioOutcomeClassifier` | Classifies Composer execution as success, solver, or operational | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Composer\ScenarioWorkspacePreparer` | Writes temporary manifest and restricted environment data | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Composer\TargetPlatformProfileFileReader` | Reads and validates a target-platform profile file | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Composer\UnreadableJsonFileException` | Typed unreadable Composer JSON file failure | [[Core Package Guide|Core-Package-Guide]] |

## Core progress namespace

| Namespace and class/interface | Purpose | Deeper page |
| --- | --- | --- |
| `PhpUpgradePreflight\Core\Progress\AnalysisPhase` | Stable analysis phase vocabulary | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Progress\AnalysisProgressEvent` | Validated analysis, phase, and scenario lifecycle event | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Progress\AnalysisProgressReporter` | Optional observational progress sink contract | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Progress\NoOpAnalysisProgressReporter` | Silent default progress sink | [[Core Service Reference|Core-Service-Reference]] |

## Core contracts, filesystem, and framework namespaces

| Namespace and class/interface | Purpose | Deeper page |
| --- | --- | --- |
| `PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer` | Public request-to-report analyzer contract | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Filesystem\NativeWorkspaceFilesystem` | Native implementation of workspace filesystem operations | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Filesystem\TemporaryWorkspaceManager` | Creates, retains, and cleans analyzer workspaces | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Filesystem\WorkspaceCleanupException` | Reports a failed workspace cleanup | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Filesystem\WorkspaceFilesystem` | Filesystem operation interface for workspaces | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Filesystem\WorkspaceManager` | Scenario workspace lifecycle interface | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Framework\CompatibilityRule` | Base adapter compatibility-rule contract | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Framework\FrameworkDetection` | Value describing adapter detection and optional version | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Framework\FrameworkIntegration` | Base framework adapter contract | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Framework\FrameworkStageTargetProvider` | Optional contract for staged target plans | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Framework\FrameworkTransitionProvider` | Optional contract for transition guidance | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Framework\HopAwareCompatibilityRule` | Optional rule contract for a specific framework hop | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Framework\PackageFamilyClassifier` | Optional package-to-family classification contract | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Framework\SourceUsageVisitorProvider` | Optional adapter AST-collector provider contract | [[Core Package Guide|Core-Package-Guide]] |

## Core model namespace

| Namespace and class/interface | Purpose | Deeper page |
| --- | --- | --- |
| `PhpUpgradePreflight\Core\Model\AnalysisBudget` | Serialized staged-analysis limits and advisory budgets | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\Blocker` | Structured dependency or platform blocker | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\BlockerAttribution` | Relationship between a conflict and the request | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\BlockerType` | Valid blocker type vocabulary | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\CandidateLockEvidence` | Candidate lock digest, content hash, and package count | [[Determinism and Evidence|Determinism-and-Evidence]] |
| `PhpUpgradePreflight\Core\Model\CompatibilityFinding` | Framework compatibility conclusion with evidence | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\ComposerDiagnostic` | Structured result of a Composer diagnostic command | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration` | Composer executable, version, timeout, and policy configuration | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\ComposerJson` | Validated Composer manifest value | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\ComposerLock` | Validated Composer lock and package index | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\Confidence` | Valid confidence vocabulary | [[Determinism and Evidence|Determinism-and-Evidence]] |
| `PhpUpgradePreflight\Core\Model\EffortEstimate` | Effort range, confidence, components, and assumptions | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\Evidence` | One sanitized evidence record | [[Determinism and Evidence|Determinism-and-Evidence]] |
| `PhpUpgradePreflight\Core\Model\EvidenceLedger` | Registers, deduplicates, and validates evidence | [[Determinism and Evidence|Determinism-and-Evidence]] |
| `PhpUpgradePreflight\Core\Model\EvidenceRecorder` | Evidence creation interface passed to collaborators | [[Determinism and Evidence|Determinism-and-Evidence]] |
| `PhpUpgradePreflight\Core\Model\ExtensionAssumption` | One explicit extension presence or absence decision | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\ExtensionAssumptionSet` | Normalized non-contradictory extension assumptions | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\FrameworkGuidance` | Aggregate adapter guidance for a transition | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\FrameworkHop` | One supported, partial, or unsupported framework hop | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\FrameworkStagePlan` | Ordered stage targets or an unavailable reason | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\FrameworkStageTarget` | Exact package/PHP targets for one stage | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\HostExtension` | Observed host extension name and optional version | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\LockDiff` | Collection of candidate package changes | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\PackageChange` | One added, removed, upgraded, or downgraded package | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\PackageRef` | Normalized locked package metadata | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\PlanStage` | Human-oriented remediation plan stage | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\PlatformProvenance` | Report projection of target and Composer platform evidence | [[Determinism and Evidence|Determinism-and-Evidence]] |
| `PhpUpgradePreflight\Core\Model\ProjectState` | Project path, manifest, and lock snapshot | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\ProjectStateFingerprint` | Portable staged-state component and aggregate digests | [[Determinism and Evidence|Determinism-and-Evidence]] |
| `PhpUpgradePreflight\Core\Model\ReportFormat` | Valid report format vocabulary and normalization | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\ReportMetadata` | Tool and report schema versions | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\ReportSections` | Internal derived report-sections bundle | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Model\RiskSummary` | Risk level and deterministic reasons | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\RootConstraintChange` | Requested temporary root requirement change | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\Scenario` | Definition of one Composer scenario | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Model\ScenarioResult` | Complete evidence and outcome of one scenario | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Model\Severity` | Valid severity vocabulary | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\SolverRelation` | Parsed package/constraint solver relationship | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\SourceImpactFinding` | Actionable source correlation with occurrences | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\SourceUsage` | One AST-derived source inventory observation | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\StageAnalysis` | Reported result and assessments for one stage | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\StageAttempt` | One staged Composer attempt and its remediation data | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\StageBlockerEntry` | Blocker lifecycle entry across stage attempts | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\StagedResolution` | Aggregate staged-analysis result, budgets, and stages | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\StageExecutionContext` | Internal stage identifiers, targets, and fingerprints | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\StageTestGuidance` | Stage-scoped test recommendation | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\TargetPlatform` | Effective target PHP and platform-package model | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\TargetPlatformPackage` | One platform package value and provenance decision | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\TargetPlatformProfile` | Validated complete or partial target-platform profile | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\TestGuidance` | Aggregate test recommendation | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\UpgradeReport` | Canonical report model and invariant validation | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\UpgradeRequest` | Validated analysis request | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Model\UpgradeTarget` | One Composer package and constraint target | [[Key Concepts|Key-Concepts]] |
| `PhpUpgradePreflight\Core\Model\UpgradeTargetSet` | Normalized package targets plus optional exact PHP | [[Key Concepts|Key-Concepts]] |

## Core reporting, source, and support namespaces

| Namespace and class/interface | Purpose | Deeper page |
| --- | --- | --- |
| `PhpUpgradePreflight\Core\Reporting\JsonReportWriter` | Renders canonical pretty-printed JSON | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter` | Renders a human-readable report projection | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Reporting\ReportDestinationFilesystem` | Destination filesystem contract for report writes | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Reporting\ReportFileWriter` | Validates and writes a rendered report destination | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Reporting\ReportWriter` | Report renderer interface | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Reporting\ReportWriterResolver` | Resolves normalized format to a writer | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Reporting\SymfonyReportDestinationFilesystem` | Symfony Filesystem-backed destination implementation | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Source\AutoloadOwnershipIndexBuilder` | Builds package ownership from Composer autoload metadata | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Source\ExplicitFullyQualifiedNameVisitor` | Collects explicit fully qualified names from PHP ASTs | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Source\SourceUsageCollector` | Common contract for AST source-usage collectors | [[Core Package Guide|Core-Package-Guide]] |
| `PhpUpgradePreflight\Core\Source\SourceUsageScanner` | Finds, parses, and inventories project PHP source | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Source\SourceUsageVisitor` | Collects common PHP symbol usages | [[Core Service Reference|Core-Service-Reference]] |
| `PhpUpgradePreflight\Core\Source\SymbolDeclarationVisitor` | Collects and orders PHP symbol declarations | [[Determinism and Evidence|Determinism-and-Evidence]] |
| `PhpUpgradePreflight\Core\Source\SymbolOwnershipIndex` | Resolves symbol owners and mapping evidence | [[Core Analysis Pipeline|Core-Analysis-Pipeline]] |
| `PhpUpgradePreflight\Core\Support\OutputExcerpt` | Bounds external text while preserving valid UTF-8 | [[Determinism and Evidence|Determinism-and-Evidence]] |
| `PhpUpgradePreflight\Core\Support\PathExposurePolicy` | Replaces sensitive absolute paths with stable markers | [[Determinism and Evidence|Determinism-and-Evidence]] |
| `PhpUpgradePreflight\Core\Support\SensitiveOutputRedactor` | Redacts credentials, tokens, URLs, and structured secrets | [[Determinism and Evidence|Determinism-and-Evidence]] |

## Laravel catalog namespace

| Namespace and class/interface | Purpose | Deeper page |
| --- | --- | --- |
| `PhpUpgradePreflight\Laravel\Catalog\BuiltinRuleDefinition` | Catalog definition for a built-in rule kind | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog` | Maintained Laravel targets, transitions, rules, and sources | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalogValidator` | Validates catalog keys, coverage, applicability, and sources | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Catalog\PackageAdvisoryDefinition` | Catalog definition for package action guidance | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Catalog\PackageConstraintDefinition` | Package-compatible constraint for an applicable transition | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Catalog\PackageRuleDefinition` | Catalog rule containing package constraint guidance | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Catalog\RuleApplicability` | Source/target major applicability value | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Catalog\RuleDefinition` | Common catalog-definition interface | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Catalog\SkeletonPattern` | Source path and usage pattern for legacy skeleton checks | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Catalog\TargetDefinition` | Laravel-major PHP and Symfony target metadata | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Catalog\TransitionDefinition` | Adjacent or direct transition rule-pack metadata | [[Laravel Package Internals|Laravel-Package-Internals]] |

## Laravel integration and command namespaces

| Namespace and class | Purpose | Deeper page |
| --- | --- | --- |
| `PhpUpgradePreflight\Laravel\Commands\AnalyzeUpgradeCommand` | Laravel Artisan analysis command | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Console\ArtisanAnalysisProgressReporter` | Renders Core progress events through terminal-attached Artisan stderr | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\LaravelFrameworkDetector` | Detects Laravel or Illuminate projects from Composer metadata | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration` | Facade implementing all Laravel adapter capabilities | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\LaravelPackageFamilyClassifier` | Classifies Laravel, Illuminate, and Symfony package families | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\LaravelRequestTargets` | Extracts Laravel-family targets from a request | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\LaravelRuleFactory` | Maps catalog definitions to executable compatibility rules | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\LaravelStagePlanner` | Builds contiguous evidence-backed Laravel stage targets | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\LaravelTransitionAssessor` | Assesses maintained transition-guidance coverage | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\UpgradePreflightServiceProvider` | Binds the analyzer and registers the Artisan command | [[Laravel Package Internals|Laravel-Package-Internals]] |

## Laravel rules and source namespaces

| Namespace and class | Purpose | Deeper page |
| --- | --- | --- |
| `PhpUpgradePreflight\Laravel\Rules\LaravelComposerVersionRule` | Checks Composer version guidance for applicable hops | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Rules\LaravelCurlExtensionRule` | Checks modeled cURL extension guidance | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Rules\LaravelFrameworkConstraintRule` | Checks Laravel framework source/target constraints | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Rules\LaravelHighSignalSourceRule` | Emits findings for transition-specific source signals | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Rules\LaravelPhpConstraintRule` | Checks target Laravel PHP requirements | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Rules\LaravelSkeletonRule` | Checks maintained legacy application skeleton patterns | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Rules\LaravelSource` | Resolves source Laravel major and uncertainty from project state | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Rules\LaravelTarget` | Resolves and compares requested Laravel target majors | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Rules\OldIlluminateSupportRule` | Finds obsolete Illuminate support constraints | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Rules\PackageVersionRule` | Evaluates package versions against catalog guidance | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Rules\SymfonyComponentConstraintRule` | Checks Symfony component constraints for Laravel targets | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Rules\TargetedPackageAdvisoryRule` | Emits package-specific replace, remove, or review guidance | [[Laravel Package Internals|Laravel-Package-Internals]] |
| `PhpUpgradePreflight\Laravel\Source\LaravelSourceUsageVisitor` | Collects Laravel-specific source usage from PHP ASTs | [[Laravel Package Internals|Laravel-Package-Internals]] |

## Test adapter packages

| Namespace and class | Purpose | Deeper page |
| --- | --- | --- |
| `PhpUpgradePreflight\TestAdapter\TestFrameworkIntegration` | Complete synthetic adapter fixture for current optional capabilities | [[Test Adapters|Test-Adapters]] |
| `PhpUpgradePreflight\TestAdapter\TestFrameworkSourceRule` | Synthetic source-aware compatibility rule fixture | [[Test Adapters|Test-Adapters]] |
| `PhpUpgradePreflight\LegacyTestAdapter\LegacyTestFrameworkIntegration` | Synthetic pre-v0.3 adapter capability fixture | [[Test Adapters|Test-Adapters]] |

## Coverage note

The inventory source is every PHP declaration under these directories:

```text
packages/core/src
packages/cli/src
packages/laravel/src
packages/test-adapter/src
packages/legacy-test-adapter/src
```

At the time this page was generated, those directories contained 166 class or interface declarations.

When adding or removing a production symbol, update this index and its deeper package page in the same change.

When the symbol change is part of a release tag, the Wiki update is mandatory under `wiki/AGENTS.md`.
