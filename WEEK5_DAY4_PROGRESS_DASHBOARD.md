# Week 5 Day 4: Progress Dashboard — Chart.js Visualisation from Grade Data

## 1. Feature Architecture Overview

- **Plugin**: `block_cynaris_stats`
- **Feature**: Dynamic Progress Dashboard with Chart.js & AJAX Data Feeds
- **Branch**: `feat/cynaris-w5d4-uday-b`
- **Target Visualization**:
  1. **Course Completion Rates (%)**: Bar chart comparing courses and their curriculum milestone completions.
  2. **Student Engagement & Achievement**: Dual-axis line chart tracking 7-day login frequencies alongside average final grades per student.
  3. **Real-Time Dynamic Refresh**: Asynchronous AJAX endpoint (`ajax.php`) refreshing chart data without full page reload.

---

## 2. Five Core Tables Query Integration

The analytics feed queries Moodle's 5 core tables via `$DB`:

1. **`{course}` & `{course_completions}`**:
   Calculates the completion percentage per course:
   $$\text{Completion \%} = \frac{\text{Completed Count}}{\text{Total Enrolled}} \times 100$$
2. **`{user}` & `{grade_grades}`**:
   Aggregates average grades per student using `LEFT JOIN` to gracefully handle enrolled learners without recorded marks (`avg_grade = null`).
3. **`{user}` & `{log}`**:
   Measures user engagement by counting login events over a 7-day rolling window (`time >= NOW() - 7 days`) and fetching latest login timestamps.

---

## 3. AJAX Service Payload (`ajax.php`)

```json
{
    "status": "success",
    "timestamp": "2026-11-05T06:27:46+00:00",
    "courses": [
        {
            "id": 2,
            "code": "FS101",
            "name": "Full Stack Web Development with React and PHP",
            "enrolled": 4,
            "completed": 1,
            "completion_rate": 25.0
        },
        {
            "id": 3,
            "code": "DB201",
            "name": "Enterprise Database Engineering & Architecture",
            "enrolled": 2,
            "completed": 1,
            "completion_rate": 50.0
        }
    ],
    "students": [
        {
            "id": 3,
            "username": "rahul.s",
            "name": "Rahul Sharma",
            "avg_grade": 90.25,
            "has_grade": true,
            "items_count": 2
        },
        {
            "id": 4,
            "username": "priya.p",
            "name": "Priya Patel",
            "avg_grade": 89.75,
            "has_grade": true,
            "items_count": 2
        },
        {
            "id": 5,
            "username": "amit.v",
            "name": "Amit Verma",
            "avg_grade": 71.50,
            "has_grade": true,
            "items_count": 1
        },
        {
            "id": 6,
            "username": "neha.g",
            "name": "Neha Gupta",
            "avg_grade": null,
            "has_grade": false,
            "items_count": 0
        }
    ],
    "logins": [
        {
            "id": 4,
            "username": "priya.p",
            "name": "Priya Patel",
            "login_count": 7,
            "last_login": "2026-11-04 06:03"
        },
        {
            "id": 3,
            "username": "rahul.s",
            "name": "Rahul Sharma",
            "login_count": 5,
            "last_login": "2026-11-04 06:03"
        },
        {
            "id": 5,
            "username": "amit.v",
            "name": "Amit Verma",
            "login_count": 2,
            "last_login": "2026-11-03 06:03"
        },
        {
            "id": 6,
            "username": "neha.g",
            "name": "Neha Gupta",
            "login_count": 1,
            "last_login": "2026-11-04 06:25"
        }
    ]
}
```

---

## 4. Chart.js Visualizations Architecture

1. **Bar Chart (`#cynaris-completion-chart`)**:
   - Compares Course Completion Percentage across active courses.
   - Fixed Y-axis scale (0 to 100%) with percentage ticks.
2. **Line Chart (`#cynaris-login-chart`)**:
   - Dual-axis graph correlating student login frequency (Left Y-axis) against their academic performance average (Right Y-axis).
   - Dynamically highlights active learners versus disengaged students.
3. **Zero-Reload Dynamic Update**:
   - Event listener bound to `#btn-cynaris-refresh`.
   - Fetches updated JSON from `ajax.php`, mutates Chart.js data buffers, and triggers `chart.update()`.
