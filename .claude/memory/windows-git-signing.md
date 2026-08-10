---
memory_contract: 1
name: windows-git-signing
description: Records the Windows OpenSSH override required when creating agent-backed signed Git commits in this repository.
type: project
related: []
provenance:
  - kind: repository
    locator: .
    retrieved_at: 2026-08-10
    revision: 1dd2fe857a3399c8981057d392355aa3fdf523bb
  - kind: conversation
    locator: 2026-08-10-signed-commit-workaround
    retrieved_at: 2026-08-10
last_updated: 2026-08-10
last_reviewed: 2026-08-10
---

# Windows Git SSH Signing

## Constraint

Git for Windows may invoke its bundled `ssh-keygen` for SSH commit signing. That executable does not use the unlocked keys in the Windows OpenSSH agent in this environment and falls back to a non-interactive private-key passphrase prompt.

After confirming the signing key is loaded with `ssh-add -l`, create signed commits with a one-command override to Windows OpenSSH:

```powershell
git -c gpg.ssh.program=C:/Windows/System32/OpenSSH/ssh-keygen.exe commit -m "<message>"
```

Keep signing enabled; do not work around this integration mismatch with `--no-gpg-sign`. The override is command-scoped and does not modify Git configuration. Verify the resulting signature with `git verify-commit HEAD`.
