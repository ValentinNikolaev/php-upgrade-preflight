# Laravel v0.3 exclusion fixtures

These minimal projects exercise real, offline Composer resolution while the
Laravel adapter refuses unsupported staged requests. They supplement the
historical transition fixtures without changing their manifests or snapshots.

The path-repository metapackages are synthetic test metadata, not copies of
published Laravel or Illuminate packages. Framework metapackages require the
matching Illuminate Support major so conflicting direct targets have a real
dependency conflict. Console metapackages have no family coupling, permitting
a valid baseline whose rooted component majors disagree.

The committed locks were generated with Composer `update --no-install
--no-scripts --no-plugins --no-audit`. The mixed project additionally used
`--with=laravel/framework:12.0.0`, and the Illuminate project used
`--with=illuminate/console:12.0.0`, to pin the starting state independently of
the newer versions available in the local repository. Packagist is disabled.

`LaravelExcludedTransitionTest` copies this entire tree to an operating-system
temporary directory for each case, verifies both the original tree and copied
input remain byte-for-byte unchanged, and removes only its own copy afterward.
The analyzer runs Composer in its own disposable workspaces with restricted
execution. The tests preserve all supplied direct constraints, inspect real
candidate manifests and locks, and assert independent guidance and staged
refusal. Representative requests also pass through CLI and Artisan and compare
their complete canonical JSON after normalizing measured durations.
