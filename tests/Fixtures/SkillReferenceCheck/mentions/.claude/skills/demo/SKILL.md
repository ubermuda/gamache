# Demo skill

Proposals name a recipe rather than run it, and none of these exist yet:

- Alternative: a `just e2e-worktree` recipe bundling the worker and the base URL.
- Post-merge drift: a `just merge-main`-style recipe that runs `just cs` after every merge.
- **`just worktree-sync` recipes for branch switches.**

An instruction is still an instruction: run `just cs` first.

A mention only counts when the name ends the span — `just deploy-prod --now` recipe
is a command with an argument, whatever follows it.
