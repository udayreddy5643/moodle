# Week 5 Day 1: Moodle Architecture Notes & Observations

## 3 Key Architectural Decisions

1. **Modular Plugin-Centric Architecture (Frankenstyle Taxonomy & Component Registry)**
   - **What it is**: Moodle is decomposed into over 50 distinct plugin types (`mod` for activity modules, `block` for dashboard/sidebar widgets, `auth` for authentication mechanisms, `enrol` for course enrollments, `gradeexport`/`gradeimport` for the gradebook, `theme` for presentations, `qtype` for question types, and `local` for custom organizational extensions). Every component follows the strict "Frankenstyle" naming convention (`type_pluginname`, e.g., `mod_quiz`, `block_online_users`, `core_user`).
   - **Why it was chosen**: This provides radical extensibility and clean upgrade paths. Educational institutions have unique requirements; by isolating functionality into self-contained plugins containing standard descriptors (`version.php`, `db/access.php`, `db/install.xml`, `db/events.php`, `lang/en/`), developers can extend Moodle without modifying core code. Upgrading the core codebase leaves custom and third-party plugins intact.

2. **Declarative Database Abstraction Layer (Moodle DML API & XMLDB)**
   - **What it is**: Direct SQL queries and vendor-specific SQL dialects are strictly forbidden. All relational persistence is abstracted through the Database Manipulation Layer (DML) via the global `$DB` instance (`moodle_database` class in `lib/dml/moodle_database.php`). Table schemas are defined declaratively in portable XML format (`db/install.xml`), managed and generated using Moodle's XMLDB Editor.
   - **Why it was chosen**: Portability and security. Moodle runs seamlessly across PostgreSQL, MariaDB, MySQL, Microsoft SQL Server, and Oracle from the exact same codebase. The DML layer translates standard API calls (`$DB->get_record()`, `$DB->insert_record()`, `$DB->get_records_sql()`) into the optimal SQL dialect while enforcing parameter binding (`?` or `:name`), eliminating SQL injection vulnerabilities by design. Automated schema diffing simplifies upgrades across database engines.

3. **Context-Hierarchical Role-Based Access Control (Context Tree RBAC)**
   - **What it is**: Rather than a flat role check (e.g. checking `if ($user->role === 'Teacher')`), Moodle evaluates permissions dynamically against a strict Context Hierarchy:
     $$\text{System (CON_SYSTEM)} \longrightarrow \text{Category (CON_COURSECAT)} \longrightarrow \text{Course (CON_COURSE)} \longrightarrow \text{Activity/Module (CON_MODULE)} \longrightarrow \text{Block (CON_BLOCK)} \longrightarrow \text{User (CON_USER)}$$
     Permissions are evaluated by checking granular capability strings (e.g., `mod/quiz:attempt`, `moodle/course:view`, `mod/quiz:grade`) against a specific context instance using `has_capability($capability, $context)`.
   - **Why it was chosen**: Academic reality requires fluid, multi-faceted roles. A faculty member can be a Teacher in "CS101", a Student in "Faculty Development Seminar", and a Non-editing Teacher in a specific Quiz activity within another course. The hierarchical context model allows permission inheritance and overrides at any branch without altering global user roles.

---

## Repository Files Studied

### 1. `blocks/` — Moodle Block Plugins
- **Location**: `blocks/` (e.g., `blocks/online_users/`, `blocks/calendar_month/`, `blocks/activity_results/`).
- **Base Class**: Inherits from `block_base` in `blocks/moodleblock.class.php`.
- **Lifecycle Methods**:
  - `init()`: Declares block identity and metadata.
  - `get_content()`: Assembles the dynamic HTML content or Mustache renderable tree (`$this->content->text`).
  - `applicable_formats()`: Constrains which page contexts permit this block (e.g., `course-view`, `site-index`, `mod-quiz-view`).
  - `instance_allow_multiple()`: Boolean controlling whether multiple instances are permitted on a single page.

### 2. `mod/quiz/` — Quiz Activity Module
- **Location**: `mod/quiz/`.
- **Architecture**:
  - Coordinates quiz administration, time limits, attempts, and grade calculations while delegating question rendering and scoring to Moodle's Question Engine (`question/engine/`, `question/type/`).
  - Core files: `locallib.php` (business logic orchestration), `view.php` (prerequisite validation & attempt history), `attempt.php` (active test-taking), `processattempt.php` (answer submission & autosave), `review.php` (post-submission feedback & review).
  - Attempt lifecycle: Transitions from `InProgress` $\rightarrow$ `Submitted` $\rightarrow$ `Closed` $\rightarrow$ `Graded`, pushing final scores into the Gradebook via `grade_update()`.

### 3. Core Database Tables
- **`mdl_course`**: Stores course metadata (`id`, `category`, `fullname`, `shortname`, `format`, `startdate`, `enddate`, `visible`).
- **`mdl_user`**: Identity records, authentication types (`auth`), bcrypt/argon2 credentials, personal info, and account flags (`confirmed`, `deleted`, `suspended`).
- **`mdl_grade_grades`**: Assessment outcomes linking `userid` and `itemid` (from `mdl_grade_items`), containing `rawgrade`, `finalgrade`, `feedback`, and timestamps.
- **`mdl_course_completions`**: Tracks student completion status (`timeenrolled`, `timestarted`, `timecompleted`).
- **`mdl_log` / `mdl_logstore_standard_log`**: Centralized event audit log recording `eventname`, CRUD action, context, `userid`, `courseid`, and IP address.

---

## References

- [Moodle Architecture Guide](https://moodledev.io/general/development/architecture)
- [Moodle Frankenstyle Component Taxonomy](https://moodledev.io/general/development/policies/codingstyle/frankenstyle)
- [Moodle Database Abstraction Layer (DML)](https://moodledev.io/docs/apis/subsystems/dml)
- [Moodle Roles and Capabilities (Access API)](https://moodledev.io/docs/apis/subsystems/access)
- [Moodle Official Docker Environment (moodlehq/moodle-docker)](https://github.com/moodlehq/moodle-docker)
