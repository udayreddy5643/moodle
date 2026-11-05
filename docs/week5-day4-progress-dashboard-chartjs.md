# Week 5, Day 4: Progress Dashboard — Chart.js Visualisation from Grade Data

## Executive Summary & Lesson Overview

- **Internship Track**: Full Stack Development Intern (Cynaris / Week 5 Day 4)
- **Topic**: Data Visualization, Moodle AJAX Endpoints, Chart.js Visual Architecture, and Dynamic DOM Mutation
- **Plugin Name**: `block_cynaris_stats`
- **Engineer**: Uday Reddy (`udayreddy5643`)
- **Git Branch**: `feat/cynaris-w5d4-uday-b`
- **Status**: Complete & Verified

---

## 1. Feature Architecture & System Design

To provide educational institutions with immediate visibility into learner progression, `block_cynaris_stats` was extended with a dynamic client-side analytics dashboard.

### Core Architectural Layers:
1. **Relational Aggregation Layer (`$DB`)**:
   - Queries Moodle's 5 core tables (`mdl_course`, `mdl_grade_grades`, `mdl_course_completions`, `mdl_user`, `mdl_log`).
   - Normalizes data into course completion ratios, student-level grade averages, and rolling 7-day login frequencies.
2. **Asynchronous Transport Layer (`ajax.php`)**:
   - Dedicated AJAX endpoint outputting sanitized JSON data.
   - Designed with proper HTTP cache-control headers (`no-cache, no-store`).
3. **Presentation & Rendering Engine (`Chart.js`)**:
   - Integrated via CDN (`chart.umd.min.js`).
   - **Bar Chart**: Visualizes Course Completion Rates (0–100%).
   - **Dual-Axis Line Chart**: Correlates login activity with student academic achievement.
4. **Reactive DOM Poller**:
   - Event-driven JavaScript fetching fresh metrics from `ajax.php`.
   - Modifies Chart.js dataset arrays in-memory and invokes `chart.update()`, refreshing visuals with **zero page reload**.

---

## 2. Test Verification with 4 Student Personas

The system was evaluated against 4 real student records in the local MariaDB instance, verifying proper handling of students with and without grades:

| Student Name | Username | Active Enrolled Courses | Course Completion Status | Final Grade Average | 7-Day Logins | Chart Behavior |
| :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| **Rahul Sharma** | `rahul.s` | FS101, DB201 | Completed (FS101), In Progress (DB201) | **90.25%** | 5 | Plotted on both login & grade axes |
| **Priya Patel** | `priya.p` | FS101, DB201 | In Progress (FS101), Completed (DB201) | **89.75%** | 7 | Highest login frequency & top grade |
| **Amit Verma** | `amit.v` | FS101 | Enrolled / Not Started | **71.50%** | 2 | Lower engagement, single grade record |
| **Neha Gupta** | `neha.g` | FS101 | Enrolled / Not Started | **NULL** *(No grades)* | 1 | Handled gracefully without breaking charts |

---

## 3. Dynamic Zero-Reload Verification

1. When the user loads the dashboard, initial counters and chart canvases are populated immediately.
2. Clicking **`↻ Refresh`** triggers the JavaScript `fetchAndUpdateStats()` function.
3. The button disables and shows a loading state (`⏳...`).
4. Upon receiving the JSON response:
   - Summary cards update in the DOM (`#cynaris-count-courses`, `#cynaris-count-users`).
   - The completion bar chart re-evaluates percentages.
   - The dual-axis line chart updates points.
   - The status badge updates to `Updated: <current time>`.
5. The button re-enables without any full page refresh.

---

## 4. AI Tasks (CIA — Cynaris Intelligence Assistant) Logs

### Session 1: Full Stack Mentor Mode (Code Review)
- **Prompt**:
  > *"Review our Chart.js integration inside block_cynaris_stats.php and our ajax.php endpoint. Does our handling of students without grades and dual-axis chart configuration follow full stack best practices?"*
- **CIA Feedback**:
  > "1. **Null Safety**: In `ajax.php`, using `LEFT JOIN {grade_grades}` ensures students who have not attempted assessments (like Neha Gupta) still appear in the dataset with `avg_grade: null` rather than being omitted.
  > 2. **Dual-Axis Scaling**: In Chart.js, binding logins to axis `y` (unbounded count) and grades to axis `y1` (fixed 0–100%) prevents large grade numbers from flattening small login frequencies.
  > 3. **Memory Leaks**: When updating charts dynamically, mutating `chart.data` and calling `chart.update()` is much cleaner than destroying and recreating the Canvas 2D context."

### Session 2: Code Mode (Edge Cases Analysis)
- **Prompt**:
  > *"What edge cases should we prepare for when running Chart.js inside Moodle's Boost theme layout sidebars?"*
- **CIA Feedback**:
  > "1. **Responsive Canvas Resizing**: Enclose `<canvas>` tags within parent containers with explicit CSS classes (`chart-container`) to avoid infinite resize loops in responsive flexbox drawers.
  > 2. **DOM Readiness**: Wrap script initialization inside both `DOMContentLoaded` and a fallback check for `document.readyState === 'complete'`, ensuring charts render reliably even if scripts load asynchronously or via PJAX."

---

## 5. Completed Self-Review Checklist

| Checklist Item | Status | Verification Notes |
| :--- | :---: | :--- |
| **All tasks completed as specified — no shortcuts taken** | [x] Passed | Queried 5 core tables, returned JSON via AJAX, rendered bar and line charts via Chart.js with dynamic zero-reload refresh. |
| **Code follows existing project conventions and patterns** | [x] Passed | Uses `$DB->get_records_sql()`, Moodle `html_writer`, and standard semantic JavaScript. |
| **At least 2 meaningful commits with descriptive messages** | [x] Passed | Commit 1 (`feat(api): ...`) and Commit 2 (`feat(dashboard): ...`) structured strictly. |
| **No hardcoded secrets, API keys, or passwords in code** | [x] Passed | Endpoints utilize dynamic `$CFG->wwwroot` routing and database parameter binding. |
| **CIA used minimum twice — interactions logged** | [x] Passed | Full Stack Mentor Mode and Code Mode sessions documented in section 4. |

---

## 6. Viva Voce Questions & In-Depth Expert Answers

### Q1: Walk me through your implementation — what did you build and why did you structure it that way?
**Answer**:
I implemented a dynamic analytics progress dashboard inside `block_cynaris_stats`:
1. **Backend AJAX Service (`ajax.php`)**: I built a dedicated service endpoint that queries Moodle's 5 core tables using `$DB->get_records_sql()`. It aggregates course completion percentages, computes average student grades (with `LEFT JOIN` to gracefully handle students without assessments), and tallies 7-day login events, outputting a structured JSON payload.
2. **Frontend Visualizations (`Chart.js`)**: I loaded Chart.js via CDN and constructed two visualizations: a bar chart displaying course completion rates and a dual-axis line chart plotting learner login frequencies against their academic achievement.
3. **Reactive Zero-Reload Engine**: I implemented a client-side JavaScript poller with an event listener on the refresh button. When triggered, it fetches fresh JSON from `ajax.php`, updates Chart.js dataset arrays in-memory, and calls `chart.update()`, updating the UI dynamically without any page reload.

### Q2: What was the most challenging part and how did you solve it?
**Answer**:
The most challenging part was correlating two fundamentally different data dimensions—login frequencies (integers typically ranging from 0 to 10) and course grade averages (percentages ranging from 0 to 100)—within a single compact widget. Plotting both on a single Y-axis caused the login frequency line to hug the bottom axis invisibly. I solved this by configuring a **dual-axis scale** in Chart.js: axis `y` (left) manages unbounded login integers, while axis `y1` (right, with `grid: { drawOnChartArea: false }`) handles percentages from 0 to 100. This maintains clear visual distinction while revealing the correlation between engagement and academic performance.

### Q3: If you had one more day, what would you improve?
**Answer**:
If granted another day, I would:
1. **Server-Sent Events (SSE) or WebSockets**: Replace client-side polling with real-time push events (`sse.php`) so that whenever a teacher records a grade or a student completes a course, the dashboard charts animate and update instantly across all connected screens.
2. **Export Capabilities**: Add a "Download CSV / PDF Report" button using jsPDF and HTML5 Canvas export (`chart.toBase64Image()`), allowing academic directors to export snapshot reports for accreditation audits.
3. **MUC Memory Caching**: Cache the aggregated JSON payload inside Moodle Universal Cache (MUC) with a 60-second TTL to eliminate database query load during peak traffic.
