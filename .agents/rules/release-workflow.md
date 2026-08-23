---
description: Pull Request, Semantic Tagging, and Packagist.org Release Standards for Anima
always_on: true
---

# Release, Pull Request & Tagging Standards (Packagist.org)

This rule defines the standardized release, pull request, and semantic versioning workflow for `scry/anima`.

---

## 1. Strict Formatting & Style Rules

- **Zero Emojis Policy:** NEVER use emojis anywhere in commit messages, PR titles, PR descriptions, release titles, release notes, code comments, documentation, UI components, or chat responses.
- **Conventional Commits:** All commit messages must follow the Conventional Commits specification (e.g., `feat: ...`, `fix: ...`, `docs: ...`, `refactor: ...`, `test: ...`, `chore: ...`).

---

## 2. Branching & Pull Request Workflow

1. **Feature Branching:**
   - Always create a dedicated branch off `main` for all changes (e.g., `feat/feature-name`, `fix/issue-description`).
   - Never commit or push directly to `main` during feature development.
2. **Pull Request Creation & Merging:**
   - Push the feature branch to `origin`: `git push -u origin <branch-name>`.
   - Open a PR targeting `main` using `gh pr create` with a clear, concise, emoji-free summary.
   - Merge the PR into `main` using `gh pr merge --merge`.
   - Switch local repository to `main` and pull latest changes: `git checkout main && git pull origin main`.
3. **Branch Cleanup:**
   - Delete the local feature branch: `git branch -D <branch-name>`.
   - Delete the remote feature branch: `git push origin --delete <branch-name>`.

---

## 3. Semantic Tagging & Packagist Release

1. **Tag Version Resolution:**
   - Inspect existing tags using `git tag -l -n --sort=-v:refname`.
   - Increment following Semantic Versioning (`vMAJOR.MINOR.PATCH`).
2. **Tag Creation & Push:**
   - Create annotated tag on `main`: `git tag -a vX.Y.Z -m "Release vX.Y.Z"`.
   - Push tag to remote: `git push origin vX.Y.Z` (or `git push origin --tags`).
3. **GitHub Release & Webhook Synchronization:**
   - Publish GitHub Release: `gh release create vX.Y.Z --title "vX.Y.Z" --notes "<Release notes without emojis>"`.
   - The GitHub release triggers the Packagist webhook to synchronize and publish the new package release on Packagist.org.

---

## 4. Package Metadata & Documentation

- **Composer Metadata:** Maintain `composer.json` metadata (`name`, `description`, `keywords`, `license`, `authors`, `homepage`) accurately for Packagist search indexing.
- **Documentation:** Keep `README.md` updated with installation, configuration, middleware, and driver usage, maintaining strict compatibility with GitHub and Packagist.org rendering.
- **License:** Preserve the MIT License across all project files.
