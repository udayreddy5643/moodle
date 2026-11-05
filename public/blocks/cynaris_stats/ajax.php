<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AJAX Analytics Service Endpoint for Cynaris Stats Block.
 *
 * @package    block_cynaris_stats
 * @copyright  2026 Uday Reddy (b.udayreddy5643@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

// Set JSON response header.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    global $DB;

    // 1. Course Completion Percentage Query ({course} & {course_completions})
    $sql_courses = "
        SELECT 
            c.id,
            c.shortname,
            c.fullname,
            COUNT(cc.id) AS total_enrolled,
            SUM(CASE WHEN cc.timecompleted IS NOT NULL AND cc.timecompleted > 0 THEN 1 ELSE 0 END) AS completed_count,
            ROUND(
                (SUM(CASE WHEN cc.timecompleted IS NOT NULL AND cc.timecompleted > 0 THEN 1 ELSE 0 END) * 100.0) / 
                NULLIF(COUNT(cc.id), 0), 1
            ) AS completion_rate
        FROM {course} c
        JOIN {course_completions} cc ON cc.course = c.id
        WHERE c.id > 1
        GROUP BY c.id, c.shortname, c.fullname
        ORDER BY c.id
    ";
    $courses_records = $DB->get_records_sql($sql_courses);

    $courses_data = [];
    foreach ($courses_records as $rec) {
        $courses_data[] = [
            'id' => (int)$rec->id,
            'code' => $rec->shortname,
            'name' => $rec->fullname,
            'enrolled' => (int)$rec->total_enrolled,
            'completed' => (int)$rec->completed_count,
            'completion_rate' => $rec->completion_rate !== null ? (float)$rec->completion_rate : 0.0,
        ];
    }

    // 2. Student Grade Average Query ({user} & {grade_grades})
    $sql_students = "
        SELECT 
            u.id,
            u.username,
            u.firstname,
            u.lastname,
            ROUND(AVG(gg.finalgrade), 2) AS avg_grade,
            COUNT(gg.id) AS grade_items_count
        FROM {user} u
        LEFT JOIN {grade_grades} gg ON gg.userid = u.id AND gg.finalgrade IS NOT NULL
        WHERE u.deleted = 0 AND u.id > 2
        GROUP BY u.id, u.username, u.firstname, u.lastname
        ORDER BY u.id
    ";
    $students_records = $DB->get_records_sql($sql_students);

    $students_data = [];
    foreach ($students_records as $rec) {
        $students_data[] = [
            'id' => (int)$rec->id,
            'username' => $rec->username,
            'name' => trim($rec->firstname . ' ' . $rec->lastname),
            'avg_grade' => $rec->avg_grade !== null ? (float)$rec->avg_grade : null,
            'has_grade' => $rec->avg_grade !== null,
            'items_count' => (int)$rec->grade_items_count,
        ];
    }

    // 3. User Login Frequency & Last Login ({user} & {log})
    $seven_days_ago = time() - (7 * 86400);
    $sql_logins = "
        SELECT 
            u.id,
            u.username,
            CONCAT(u.firstname, ' ', u.lastname) AS fullname,
            COUNT(l.id) AS login_count_7d,
            COALESCE(MAX(l.time), u.lastaccess, 0) AS last_login_ts
        FROM {user} u
        LEFT JOIN {log} l ON l.userid = u.id AND l.action = 'login' AND l.time >= :window
        WHERE u.deleted = 0 AND u.id > 2
        GROUP BY u.id, u.username, u.firstname, u.lastname, u.lastaccess
        ORDER BY login_count_7d DESC, u.id
    ";
    $logins_records = $DB->get_records_sql($sql_logins, ['window' => $seven_days_ago]);

    $logins_data = [];
    foreach ($logins_records as $rec) {
        $last_ts = (int)$rec->last_login_ts;
        $logins_data[] = [
            'id' => (int)$rec->id,
            'username' => $rec->username,
            'name' => $rec->fullname,
            'login_count' => (int)$rec->login_count_7d,
            'last_login' => $last_ts > 0 ? date('Y-m-d H:i', $last_ts) : 'Never',
        ];
    }

    echo json_encode([
        'status' => 'success',
        'timestamp' => date('c'),
        'courses' => $courses_data,
        'students' => $students_data,
        'logins' => $logins_data,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
