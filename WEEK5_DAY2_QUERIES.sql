-- =============================================================================
-- Week 5 Day 2: Moodle Core Architecture Workshop Queries
-- Student Engineer: Uday Reddy (udayreddy5643)
-- Database Engine: MariaDB 11.4 / MySQL Compatible
-- Tables: mdl_course, mdl_grade_grades, mdl_course_completions, mdl_user, mdl_log
-- Note: Replace table prefix `m_` with `mdl_` if running on standard instances.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Query (a): List all students enrolled in a course with their completion status
-- -----------------------------------------------------------------------------
SELECT 
    c.id AS course_id,
    c.shortname AS course_code,
    c.fullname AS course_name,
    u.id AS user_id,
    u.username,
    CONCAT(u.firstname, ' ', u.lastname) AS student_name,
    u.email,
    FROM_UNIXTIME(cc.timeenrolled) AS enrolled_at,
    CASE 
        WHEN cc.timecompleted IS NOT NULL AND cc.timecompleted > 0 THEN 'Completed'
        WHEN cc.timestarted IS NOT NULL AND cc.timestarted > 0 THEN 'In Progress'
        ELSE 'Not Started / Enrolled'
    END AS completion_status,
    FROM_UNIXTIME(cc.timecompleted) AS completed_at
FROM m_course c
JOIN m_course_completions cc ON cc.course = c.id
JOIN m_user u ON u.id = cc.userid
WHERE u.deleted = 0
ORDER BY c.id, u.lastname, u.firstname;


-- -----------------------------------------------------------------------------
-- Query (b): Get average grade per course
-- -----------------------------------------------------------------------------
SELECT 
    c.id AS course_id,
    c.shortname AS course_code,
    c.fullname AS course_name,
    COUNT(gg.id) AS total_graded_students,
    ROUND(AVG(gg.finalgrade), 2) AS average_final_grade,
    ROUND(MIN(gg.finalgrade), 2) AS lowest_grade,
    ROUND(MAX(gg.finalgrade), 2) AS highest_grade
FROM m_course c
JOIN m_grade_items gi ON gi.courseid = c.id
JOIN m_grade_grades gg ON gg.itemid = gi.id
WHERE gi.itemtype = 'course'
  AND gg.finalgrade IS NOT NULL
GROUP BY c.id, c.shortname, c.fullname
ORDER BY c.id;


-- -----------------------------------------------------------------------------
-- Query (c): Count login events per user in the last 7 days from mdl_log
-- -----------------------------------------------------------------------------
-- Execution using legacy mdl_log:
SELECT 
    u.id AS user_id,
    u.username,
    CONCAT(u.firstname, ' ', u.lastname) AS user_name,
    u.email,
    COUNT(l.id) AS login_events_last_7_days,
    FROM_UNIXTIME(MIN(l.time)) AS earliest_login_in_window,
    FROM_UNIXTIME(MAX(l.time)) AS latest_login
FROM m_user u
JOIN m_log l ON l.userid = u.id
WHERE l.action = 'login'
  AND l.time >= UNIX_TIMESTAMP(NOW() - INTERVAL 7 DAY)
  AND u.deleted = 0
GROUP BY u.id, u.username, u.firstname, u.lastname, u.email
ORDER BY login_events_last_7_days DESC, u.username;

-- Execution using modern mdl_logstore_standard_log (Moodle 2.7+ / 4.x / 5.x standard):
SELECT 
    u.id AS user_id,
    u.username,
    CONCAT(u.firstname, ' ', u.lastname) AS user_name,
    u.email,
    COUNT(l.id) AS login_events_last_7_days,
    FROM_UNIXTIME(MIN(l.timecreated)) AS earliest_login_in_window,
    FROM_UNIXTIME(MAX(l.timecreated)) AS latest_login
FROM m_user u
JOIN m_logstore_standard_log l ON l.userid = u.id
WHERE (l.action = 'loggedin' OR l.eventname LIKE '%user_loggedin%')
  AND l.timecreated >= UNIX_TIMESTAMP(NOW() - INTERVAL 7 DAY)
  AND u.deleted = 0
GROUP BY u.id, u.username, u.firstname, u.lastname, u.email
ORDER BY login_events_last_7_days DESC, u.username;
