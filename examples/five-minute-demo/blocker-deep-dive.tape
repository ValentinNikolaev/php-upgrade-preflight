Output blocker-deep-dive.gif

Require bash
Require composer
Require php
Require jq

Set Shell bash
Set FontSize 19
Set Width 1200
Set Height 720
Set TypingSpeed 20ms
Set Theme "Catppuccin Mocha"
Set Padding 24
Set Margin 24
Set MarginFill "#11111b"
Set WindowBar ColorfulRight
Set BorderRadius 12
Set Framerate 20
Set PlaybackSpeed 1.25
Set CursorBlink false
Set WaitTimeout 300s

Hide
Type "cd /app && clear"
Enter
Sleep 1s
Show

Type 'COMPOSER_DISABLE_NETWORK=1 COMPOSER_ROOT_VERSION=1.0.0 \'
Enter
Type '  php packages/cli/bin/upgrade-intel analyze --path=/app/examples/five-minute-demo/target \'
Enter
Type '  --from-php=8.1 --target=laravel/framework:^13.0 --target-php=8.3 \'
Enter
Type '  --without-extension=ext-preflight-stage --composer-mode=restricted \'
Enter
Type '  --framework=laravel --format=json --output=/tmp/report.json'
Sleep 500ms
Enter
Wait
Sleep 1s

Type `jq -r '.resolution.status, .staged_resolution.status' /tmp/report.json`
Enter
Wait
Sleep 3s

Type "clear"
Enter

Type `jq '.staged_resolution.blocker_registry[0] | {blocking_package, constraint, dependency_path, lifecycle, options, evidence}' /tmp/report.json`
Enter
Wait
Sleep 7s

Type "clear"
Enter

Type `jq '.evidence[] | select(.id == "solver-5") | {summary, scenario: .context.scenario, exit_code: .context.exit_code, confidence}' /tmp/report.json`
Enter
Wait
Sleep 5s

Type `jq -r '.staged_resolution.stages[0].attempts[0].scenario.command | join(" ")' /tmp/report.json`
Enter
Wait
Sleep 4s

Type "clear"
Enter

Type `jq -r '.evidence[] | select(.id == "solver-5") | .context.output_excerpt' /tmp/report.json`
Enter
Wait
Sleep 10s
