# Week 5, Day 5: Assessment Engine Study + UI Bug Fix + PR

## Executive Summary & Lesson Overview

- **Internship Track**: Full Stack Development Intern (Cynaris / Week 5 Day 5)
- **Topic**: Systematic Debugging, Moodle Assessment Engine (`mod/quiz`), UI/UX Refactoring, Accessibility (a11y), and Enterprise Git Pull Request Delivery
- **Engineer**: Uday Reddy (`udayreddy5643`)
- **Git Branch**: `feat/cynaris-w5d5-uday-b`
- **Status**: Complete, Verified & PR Prepared

---

## 1. Deep Dive: Moodle Assessment Engine (`mod/quiz`)

Moodle's assessment architecture is engineered to separate activity orchestration (`mod/quiz`) from question rendering and evaluation (`question/engine/`).

### Key Subsystems:
1. **`mod/quiz/view.php`**: Entry controller verifying prerequisite conditions (enrolment, open/close dates, password protection, attempt limits).
2. **`mod/quiz/locallib.php`**: Domain service orchestrating `quiz_start_new_attempt()` and transaction-wrapped state updates.
3. **`mod/quiz/attempt.php`**: Active examination interface managing multi-page question layout, autosave timer, and navigation drawer.
4. **`mod/quiz/processattempt.php`**: Transactional handler processing POST data, saving step responses in `mdl_question_attempt_steps`, and executing autosave triggers.
5. **`mod/quiz/review.php`**: Post-submission evaluation showing question-level marks, teacher feedback, and automated recalculations.

---

## 2. Bug Reports Summary

| Bug ID | Component | Summary | Root Cause | Fix Applied | Commit ID |
| :---: | :---: | :--- | :--- | :--- | :---: |
| **#101** | `mod_quiz` | Navigation buttons truncate on mobile | Rigid `min-width` and missing `flex-wrap` in `.qn_buttons` | Flexbox wrapping and responsive media queries scaling padding and font | `402d200a2` |
| **#102** | `mod_quiz` | Attempt timer hidden behind sticky navbar | `#quiz-timer` has `z-index: 100` vs navbar `z-index: 1030` | Elevated timer to `z-index: 1040`, offset `top: 68px` with backdrop blur | `0b04a37a9` |
| **#103** | `block_cynaris_stats` | Chart canvas blowout & missing a11y | Unbounded canvas in flex container; missing ARIA live region | Container `position: relative; max-width: 100%` + `aria-live="polite"` | `5c2eed89b` |

---

## 3. AI Tasks (CIA — Cynaris Intelligence Assistant) Logs

### Session 1: QA Engineer Mode (Root Cause Hypotheses)
- **Prompt**:
  > *"During timed quiz attempts in Moodle's Boost theme, when users scroll down, the floating countdown timer disappears behind the navigation bar. What are the likely root causes?"*
- **CIA Feedback**:
  > "1. **CSS Stacking Context & Z-Index Mismatch**: The top navigation bar in Bootstrap/Boost (`.fixed-top`) uses `z-index: 1030`. If `#quiz-timer` has a default lower z-index (e.g. `100`), it will slide behind the navbar when scrolled into its vertical position.
  > 2. **Sticky Offset**: Setting `top: 0` on `#quiz-timer` makes it stick to the browser viewport top, which is already occupied by the 60px fixed header. It must have `top: 68px` to anchor beneath the bar."
- **CIA vs Actual Fix Comparison**: CIA's diagnosis was 100% accurate regarding stacking context and top offset. I implemented both `z-index: 1040` and `top: 68px`, while adding a subtle backdrop blur and box shadow for enhanced visual separation.

### Session 2: Code Mode (Buggy Code Analysis)
- **Prompt**:
  > *"When resizing or collapsing sidebar drawers in Moodle, our Chart.js canvas expands horizontally beyond the block boundaries and breaks the page layout. What is wrong and how do we fix it?"*
- **CIA Feedback**:
  > "By default, Chart.js sets `responsive: true`, which makes the canvas resize according to its parent container. However, if the canvas parent is a flex child without an explicit width constraint, Chart.js reads its own canvas width as the parent's width, causing an infinite expansion loop. Fix this by wrapping the canvas in an element with `position: relative; width: 100%; max-width: 100%; overflow: hidden;`."
- **CIA vs Actual Fix Comparison**: CIA identified the exact Chart.js flex parent expansion trap. I implemented the bounded wrapper styling and additionally added ARIA accessibility attributes (`role="status"`, `aria-live="polite"`).

---

## 4. Completed Self-Review Checklist

| Checklist Item | Status | Verification Notes |
| :--- | :---: | :--- |
| **All tasks completed as specified — no shortcuts taken** | [x] Passed | 3 UI issues reproduced, documented, fixed across 3 separate commits with issue references. |
| **Code follows existing project conventions and patterns** | [x] Passed | Adheres to Moodle CSS/BEM rules, Boost theme variables, and semantic commit formats. |
| **At least 2 meaningful commits with descriptive messages** | [x] Passed | 4 separate commits completed (`fix(quiz): ...`, `fix(quiz): ...`, `fix(dashboard): ...`, `docs(...)`). |
| **No hardcoded secrets, API keys, or passwords in code** | [x] Passed | Pure CSS and UI layout updates; zero sensitive credentials. |
| **CIA used minimum twice — interactions logged** | [x] Passed | QA Engineer Mode and Code Mode sessions documented in section 3. |

---

## 5. Viva Voce Questions & In-Depth Expert Answers

### Q1: Explain the root cause of one bug you fixed. Why did the original developer write it that way?
**Answer**:
In Issue #102, the quiz floating countdown timer (`#quiz-timer`) slid behind the top header during scrolling. The root cause was a CSS stacking context mismatch: Boost's `.fixed-top` navbar has `z-index: 1030`, whereas `#quiz-timer` was configured with `z-index: 100` and `top: 0`.

The original developer wrote it that way because in older Moodle classic themes (like *Clean* or *More*), the navigation bar was static in the page document flow and scrolled out of view immediately, meaning a `top: 0` sticky timer would dock cleanly at the very top of the screen. However, when Moodle transitioned to the modern Boost theme with a persistent sticky header (`position: fixed; top: 0`), the timer's coordinate space conflicted with the header. I resolved this by updating the timer's `z-index` to `1040` and adding `top: 68px` so it floats neatly beneath the sticky navbar.

### Q2: How did you verify your fix did not break anything else?
**Answer**:
I performed a multi-tier verification process:
1. **Cross-Viewport Testing**: Verified the fix on Chrome DevTools across desktop (1440px), tablet (768px), and mobile (375px) breakpoints to ensure question buttons wrapped without regression on wide monitors.
2. **Theme Parity Check**: Verified the timer behavior when toggling between standard mode, full-screen attempt mode, and dark/high-contrast accessibility modes.
3. **Sidebar Drawer Transition**: Toggled Moodle Boost's left and right navigation drawers; verified that the Chart.js canvas in `block_cynaris_stats` smoothly adapted its width without triggering horizontal scrollbars.

### Q3: What is a regression test and why is it important after a bug fix?
**Answer**:
A **regression test** is a quality assurance verification process that re-tests existing application features to guarantee that a newly applied bug fix or code modification has not inadvertently broken or degraded pre-existing, unrelated functionality.

It is critical after a bug fix because in large-scale modular codebases like Moodle, global stylesheet rules (such as `.qnbutton` or z-index changes) or common API hooks can cascade across dozens of unrelated modules. Running regression tests (both automated PHPUnit/Behat suites and systematic manual viewport sweeps) ensures that fixing a UI issue on mobile doesn't inadvertently truncate desktop buttons or conflict with third-party themes.
