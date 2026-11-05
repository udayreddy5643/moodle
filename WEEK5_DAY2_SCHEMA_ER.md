# Week 5 Day 2: Moodle Core Database Schema & ER Analysis

## 1. Five Core Tables Overview

In enterprise Moodle LMS, the core pedagogical and operational lifecycle is anchored by five relational tables:

1. **`mdl_course` (`m_course`)**: Stores instructional units, course metadata, visibility, curriculum format (`topics`, `weeks`), and lifecycle timestamps (`startdate`, `enddate`).
2. **`mdl_user` (`m_user`)**: Central identity store recording credentials, authentication method (`auth`), personal details, and account status flags (`confirmed`, `deleted`, `suspended`).
3. **`mdl_course_completions` (`m_course_completions`)**: Tracks individual learner progress, enrollment timestamps, course start times, and final completion milestones.
4. **`mdl_grade_grades` (`m_grade_grades`)**: Holds assessment outcomes, linking users (`userid`) to grade items (`itemid`), storing raw marks, weighted final grades, and marker feedback.
5. **`mdl_log` / `mdl_logstore_standard_log` (`m_log`)**: Immutable audit trail capturing every user activity, HTTP request, CRUD event, context, timestamp, and IP address.

---

## 2. Plain English Summary (Max 200 Words)

> Moodle organizes digital education around five core database tables. **`mdl_course`** defines individual courses, while **`mdl_user`** stores student and instructor identities. When learners engage with instructional material, **`mdl_course_completions`** connects `mdl_user` and `mdl_course` to track whether students have begun or finished their curriculum requirements. As students complete assignments and quizzes, their marks and feedback are recorded in **`mdl_grade_grades`**, which links learner IDs to course assessment items. Finally, every interaction—from authentication to page views—is continuously audited in **`mdl_log`** (and `mdl_logstore_standard_log`), tying user actions to specific course contexts with precise timestamps and IP addresses. Together, these five tables establish a cohesive relational backbone linking identity, curriculum, academic achievement, completion milestones, and regulatory compliance.

---

## 3. Entity-Relationship (ER) Diagram

```mermaid
erDiagram
    mdl_user ||--o{ mdl_course_completions : "completes (userid)"
    mdl_course ||--o{ mdl_course_completions : "tracked in (course)"
    mdl_user ||--o{ mdl_grade_grades : "receives (userid)"
    mdl_course ||--o{ mdl_grade_items : "contains (courseid)"
    mdl_grade_items ||--o{ mdl_grade_grades : "measures (itemid)"
    mdl_user ||--o{ mdl_log : "triggers (userid)"
    mdl_course ||--o{ mdl_log : "context (course)"

    mdl_course {
        bigint id PK "Primary Key"
        bigint category FK "Course category"
        varchar fullname "Course full name"
        varchar shortname "Unique short code"
        varchar idnumber "External institution code"
        varchar format "topics | weeks | singleactivity"
        bigint startdate "Course start timestamp"
        bigint enddate "Course end timestamp"
        smallint visible "1=Visible, 0=Hidden"
    }

    mdl_user {
        bigint id PK "Primary Key"
        varchar auth "manual | oauth2 | saml2"
        varchar username "Unique login username"
        varchar email "Unique contact email"
        varchar firstname "First name"
        varchar lastname "Last name"
        smallint confirmed "Account confirmation flag"
        smallint deleted "Soft delete flag"
        smallint suspended "Account suspension flag"
        bigint lastaccess "Last activity timestamp"
    }

    mdl_course_completions {
        bigint id PK "Primary Key"
        bigint userid FK "Ref mdl_user.id"
        bigint course FK "Ref mdl_course.id"
        bigint timeenrolled "Enrollment timestamp"
        bigint timestarted "First access timestamp"
        bigint timecompleted "Completion timestamp (NULL if pending)"
        bigint reaggregate "Reaggregation flag"
    }

    mdl_grade_grades {
        bigint id PK "Primary Key"
        bigint itemid FK "Ref mdl_grade_items.id"
        bigint userid FK "Ref mdl_user.id"
        decimal rawgrade "Unadjusted student submission score"
        decimal finalgrade "Calculated and scaled grade"
        text feedback "Instructor qualitative comments"
        bigint timemodified "Grading timestamp"
    }

    mdl_log {
        bigint id PK "Primary Key"
        bigint time "Event UNIX timestamp"
        bigint userid FK "Ref mdl_user.id"
        bigint course FK "Ref mdl_course.id"
        varchar module "Module name (user, course, quiz)"
        varchar action "login | view | add | update"
        varchar url "Triggered endpoint URL"
        varchar ip "Client IP address"
    }
```
