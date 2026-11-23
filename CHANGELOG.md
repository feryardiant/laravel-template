# Changelog

All notable changes to this project will be documented in this file. See [commit-and-tag-version](https://github.com/absolute-version/commit-and-tag-version) for commit guidelines.

## 0.1.0-pre.0 (2026-11-23)

### Features

- add backlog quick wins for appearance, members, and root tenant ([#22](https://github.com/feryardiant/laravel-template/issues/22)) ([ad11ae9](https://github.com/feryardiant/laravel-template/commit/ad11ae96d77d260241f77ddb0a3c118ff5aebb76))
- add basic user management ([#3](https://github.com/feryardiant/laravel-template/issues/3)) ([3f31ea0](https://github.com/feryardiant/laravel-template/commit/3f31ea044d871ff47ad91df67a4e925795d3838f))
- add contact email and phone records for people and organizations ([#38](https://github.com/feryardiant/laravel-template/issues/38)) ([e7e4f73](https://github.com/feryardiant/laravel-template/commit/e7e4f73316e2118f6604b887b2ad65e341453158))
- add shared address form and live dashboard data ([#31](https://github.com/feryardiant/laravel-template/issues/31)) ([50b3e8b](https://github.com/feryardiant/laravel-template/commit/50b3e8b293fd0f149de4a73c199930b9c2598367))
- **audit:** add organization audit log and queued event recording ([#30](https://github.com/feryardiant/laravel-template/issues/30)) ([13a78da](https://github.com/feryardiant/laravel-template/commit/13a78da720850ff61ff155c29f6cca23e18ef000))
- **ci:** add repo template initialization workflow ([#10](https://github.com/feryardiant/laravel-template/issues/10)) ([2b839a7](https://github.com/feryardiant/laravel-template/commit/2b839a784a56418c6188428135ddfd5f67e098e8))
- **ci:** publish container images, prune old ones, and release on tags ([#44](https://github.com/feryardiant/laravel-template/issues/44)) ([d561b56](https://github.com/feryardiant/laravel-template/commit/d561b56236a5e3e45739e1eabb9b32e74c8fe926))
- **dev:** isolate worktrees with their own ports and databases ([#52](https://github.com/feryardiant/laravel-template/issues/52)) ([8b6428b](https://github.com/feryardiant/laravel-template/commit/8b6428b91820a5fd93f5b1cff1fbb591c2228567))
- initial setup :fire: ([#1](https://github.com/feryardiant/laravel-template/issues/1)) ([f66271b](https://github.com/feryardiant/laravel-template/commit/f66271be9a6d40dadbdc60a79bb411b3a923f958))
- **locale:** add Indonesian translations and localization conventions ([#4](https://github.com/feryardiant/laravel-template/issues/4)) ([4ad3334](https://github.com/feryardiant/laravel-template/commit/4ad3334e3f437d86fac14e9c08340ec1a8476ff3))
- **members:** add member removal and self-leave flows ([#24](https://github.com/feryardiant/laravel-template/issues/24)) ([633c876](https://github.com/feryardiant/laravel-template/commit/633c8763ecd002661bd56d68a7571a825f7664a2))
- **notifications:** add database-backed notification inbox ([#5](https://github.com/feryardiant/laravel-template/issues/5)) ([27743b9](https://github.com/feryardiant/laravel-template/commit/27743b9572bfce7369d0f3104d8ce503be02a947))
- organization management, approvals, and relationship history ([#25](https://github.com/feryardiant/laravel-template/issues/25)) ([58a1da6](https://github.com/feryardiant/laravel-template/commit/58a1da67dd04a911d07c693845deff40904d7c45))
- **tenancy:** add multi-tenant organizations and member management ([#13](https://github.com/feryardiant/laravel-template/issues/13)) ([f35bdc8](https://github.com/feryardiant/laravel-template/commit/f35bdc87e9fb4767ceeda1c6b14b5625b8ea0750))
- **tenancy:** add tenant contact management ([#15](https://github.com/feryardiant/laravel-template/issues/15)) ([f20859d](https://github.com/feryardiant/laravel-template/commit/f20859d8bab48bae018e7839c079b5b55d96b3e0))
- **tenancy:** adds the minimum entity domain for organizations and people ([#11](https://github.com/feryardiant/laravel-template/issues/11)) ([a665e42](https://github.com/feryardiant/laravel-template/commit/a665e42d25a0ea7cac69ebbab8939c8a9b3666f0))
- **tenancy:** tenant registration, workspace access, and feature flags ([#21](https://github.com/feryardiant/laravel-template/issues/21)) ([db19ce3](https://github.com/feryardiant/laravel-template/commit/db19ce3f3e3e6d847a1f898c5281768ddb4f8ad1))
- **ui:** add custom ui components inspired by Flux UI Pro ([#12](https://github.com/feryardiant/laravel-template/issues/12)) ([a3db174](https://github.com/feryardiant/laravel-template/commit/a3db17471545934c31d2f464d1718b4e05c944fe))
- **ui:** expand the phase 2 component kit ([#53](https://github.com/feryardiant/laravel-template/issues/53)) ([58a0adf](https://github.com/feryardiant/laravel-template/commit/58a0adf8c6afda2c0e65bf5d49069dbae04c97f9))
- use a briefcase as the app logo and adaptive favicons ([#9](https://github.com/feryardiant/laravel-template/issues/9)) ([4211ff8](https://github.com/feryardiant/laravel-template/commit/4211ff8d2e92e7ed75e1004b79edfae2b03e1ec5))
- **users:** add batch actions and edit flyout ([#6](https://github.com/feryardiant/laravel-template/issues/6)) ([4562f33](https://github.com/feryardiant/laravel-template/commit/4562f3351f9b84a111b2a8830d73a00dd4ff7aec))
- **users:** add handles and preference settings ([#8](https://github.com/feryardiant/laravel-template/issues/8)) ([8ebcce8](https://github.com/feryardiant/laravel-template/commit/8ebcce8893db3505c34df526543ed1835e1c4d03))

### Bug Fixes

- **ci:** keep `boost:*` commands available during init ([dbd6ceb](https://github.com/feryardiant/laravel-template/commit/dbd6ceb2053f1d2254a6140539b18636d27f43c4))
- **ci:** previous `run-name` was a mess ([#47](https://github.com/feryardiant/laravel-template/issues/47)) ([0e37f66](https://github.com/feryardiant/laravel-template/commit/0e37f6659051dece153b014565be78e50071b300))
- **ci:** try to fix previous fails on `dist.yml` workflow ([39b9154](https://github.com/feryardiant/laravel-template/commit/39b91548a0ed15f6f18ed74e5056d978c273b1f8))
- keep stored image paths when deletion fails ([#27](https://github.com/feryardiant/laravel-template/issues/27)) ([a4c9312](https://github.com/feryardiant/laravel-template/commit/a4c9312b1b0c2d5fc45135c159cf45ec772c942d)), closes [#14](https://github.com/feryardiant/laravel-template/issues/14)
- stop stale forms from overwriting contact records, roles and uploads ([#41](https://github.com/feryardiant/laravel-template/issues/41)) ([db74afa](https://github.com/feryardiant/laravel-template/commit/db74afa081808630dbfd360d7a7c7bd8f5977506)), closes [#39](https://github.com/feryardiant/laravel-template/issues/39) [#40](https://github.com/feryardiant/laravel-template/issues/40) [#40](https://github.com/feryardiant/laravel-template/issues/40) [#39](https://github.com/feryardiant/laravel-template/issues/39), references [#39](https://github.com/feryardiant/laravel-template/issues/39) [#39](https://github.com/feryardiant/laravel-template/issues/39) [#40](https://github.com/feryardiant/laravel-template/issues/40) [#39](https://github.com/feryardiant/laravel-template/issues/39) [#42](https://github.com/feryardiant/laravel-template/issues/42)
- **ui:** honor custom control classes and page header slots ([#50](https://github.com/feryardiant/laravel-template/issues/50)) ([f254e53](https://github.com/feryardiant/laravel-template/commit/f254e538f58428cac41516692482834ffe113c0b)), closes [#49](https://github.com/feryardiant/laravel-template/issues/49)
