# Week 5 Day 1: Moodle Local Setup & First Run Record

- **Personal Fork**: `https://github.com/udayreddy5643/moodle`
- **Upstream Repository**: `https://github.com/moodle/moodle`
- **Lesson Branch**: `feat/cynaris-w5d1-uday-b` (and `feat/cynaris-moodle-uday-b`)
- **Docker Development Environment**: Official `moodlehq/moodle-docker`
- **Local Application URL**: `http://localhost:8000`

---

## Environment Configuration

### Critical Environment Variables

```powershell
$env:MOODLE_DOCKER_WWWROOT = "C:\Users\Uday Reddy\Documents\Codex\2026-10-14\referenced-chatgpt-conversation-this-is-an\work\moodle"
$env:MOODLE_DOCKER_DB = "mariadb"
$env:MOODLE_DOCKER_PHP_VERSION = "8.2"
$env:MOODLE_DOCKER_WEB_PORT = "8000"
```

### Setup Execution Steps

1. **Fork & Clone**:
   - Fork `moodle/moodle` to `https://github.com/udayreddy5643/moodle`.
   - Clone: `git clone https://github.com/udayreddy5643/moodle.git moodle`
   - Add upstream: `git remote add upstream https://github.com/moodle/moodle.git`
   - Create branch: `git checkout -b feat/cynaris-w5d1-uday-b`

2. **Docker Orchestration**:
   - Clone: `git clone https://github.com/moodlehq/moodle-docker.git moodle-docker`
   - Copy config template: `Copy-Item ".\moodle-docker\config.docker-template.php" -Destination ".\moodle\config.php"`
   - Launch containers: `docker compose up -d` in `moodle-docker`

3. **Installer Completion**:
   - Access `http://localhost:8000`
   - Complete database configuration and admin setup (Email: `b.udayreddy5643@gmail.com`).
   - Home screen verified with zero runtime errors.
   - Screenshot captured for internship submission.

---

## References

- [Moodle Docker Developer Guide](https://github.com/moodlehq/moodle-docker)
- [Moodle Installation Quickstart](https://docs.moodle.org/en/Installing_Moodle)
