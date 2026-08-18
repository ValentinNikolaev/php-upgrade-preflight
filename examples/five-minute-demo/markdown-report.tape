Output markdown-report.gif

Require bash
Require composer
Require php
Require bat

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
Type '  --framework=laravel --format=markdown --output=/tmp/report.md'
Sleep 500ms
Enter
Wait
Sleep 1s

Type `grep '^## ' /tmp/report.md`
Enter
Wait
Sleep 5s

Type "clear"
Enter

Type `sed -n '/^## Risk And Effort/,/^## Test Guidance/p' /tmp/report.md | head -n -1 | bat --paging=never -p -l md --color=always`
Enter
Wait
Sleep 9s

Type "clear"
Enter

Type `sed -n '/^## Staged Plan/,/^## Risk And Effort/p' /tmp/report.md | head -n -1 | tail -n 5 | cut -c1-110 | bat --paging=never -p -l md --color=always`
Enter
Wait
Sleep 8s
