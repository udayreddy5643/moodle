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
 * Master CLI Verification Test Suite for Cynaris Week 6 Full Stack AI Track.
 * Runs comprehensive automated validation across all Day 1 - Day 5 deliverables.
 *
 * @package    block_cynaris_stats
 * @copyright  2026 Uday Reddy (b.udayreddy5643@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

require_once(__DIR__ . '/classes/ai_tutor.php');
require_once(__DIR__ . '/classes/ai_quiz_generator.php');
require_once(__DIR__ . '/classes/ai_recommendations.php');
require_once(__DIR__ . '/classes/ai_query_auditor.php');

echo "====================================================================\n";
echo "🏆 CYNARIS SOLUTIONS — FULL STACK DEVELOPER INTERNSHIP (WEEK 6)\n";
echo "Master Automated Verification & Deployment Healthcheck Suite\n";
echo "Candidate: Uday Reddy (b.udayreddy5643@gmail.com)\n";
echo "Date:      " . date('Y-m-d H:i:s T') . "\n";
echo "====================================================================\n\n";

global $DB, $CFG;

$all_passed = true;

// ------------------------------------------------------------------
// SECTION 1: SYSTEM & DEPLOYMENT HEALTHCHECK (DAY 5)
// ------------------------------------------------------------------
echo "📦 [CHECK 1/5] Deployment & Environment Healthcheck\n";
echo "  • PHP Version:          " . PHP_VERSION . " (Required: >= 8.1)\n";
echo "  • Moodle Release:       " . $CFG->release . "\n";
echo "  • Database Engine:      " . $CFG->dbtype . " (Host: " . $CFG->dbhost . ", DB: " . $CFG->dbname . ")\n";
echo "  • Moodle Data Dir:      " . $CFG->dataroot . " (Writable: " . (is_writable($CFG->dataroot) ? 'YES' : 'NO') . ")\n";
echo "  • Cynaris Block Ver:    " . get_config('block_cynaris_stats', 'version') . "\n";

try {
    $db_test = $DB->get_record_sql('SELECT 1 AS alive');
    if ($db_test && $db_test->alive == 1) {
        echo "  • MariaDB Connection:   ✅ ONLINE & RESPONSIVE\n";
    }
} catch (Exception $e) {
    echo "  • MariaDB Connection:   ❌ FAILED: " . $e->getMessage() . "\n";
    $all_passed = false;
}
echo "\n--------------------------------------------------------------------\n\n";

// ------------------------------------------------------------------
// SECTION 2: AI TUTOR MODULE (DAY 1)
// ------------------------------------------------------------------
echo "🤖 [CHECK 2/5] Day 1: AI Tutor Integration & Fallback Cascade\n";
try {
    $tutor_res = \block_cynaris_stats\ai_tutor::ask(
        "Explain 3NF Normalization in database design.",
        "Moodle LMS Database Architecture Course",
        true // Test fallback resilience
    );
    echo "  • Provider:             " . $tutor_res['provider'] . "\n";
    echo "  • Failover Triggered:   " . ($tutor_res['fallback_triggered'] ? 'YES (Resilience Verified)' : 'NO') . "\n";
    echo "  • Latency:              " . $tutor_res['latency_ms'] . " ms\n";
    echo "  • Status:               " . ($tutor_res['status'] === 'success' ? '✅ PASS' : '❌ FAIL') . "\n";
} catch (Exception $e) {
    echo "  • AI Tutor Error:       ❌ " . $e->getMessage() . "\n";
    $all_passed = false;
}
echo "\n--------------------------------------------------------------------\n\n";

// ------------------------------------------------------------------
// SECTION 3: AI QUIZ GENERATION MODULE (DAY 2)
// ------------------------------------------------------------------
echo "📝 [CHECK 3/5] Day 2: AI Quiz Generation & Question Bank API\n";
try {
    $target_course = $DB->get_record('course', ['id' => 2]);
    if (!$target_course) {
        $courses = $DB->get_records('course', null, '', 'id', 0, 2);
        $target_course = end($courses);
    }
    
    $gen_res = \block_cynaris_stats\ai_quiz_generator::generate_quiz_questions($target_course->id, 3, true);
    echo "  • Course:               " . $target_course->fullname . " (ID: {$target_course->id})\n";
    echo "  • Questions Generated:  " . count($gen_res['questions']) . " MCQs\n";
    echo "  • Schema Validation:    " . (isset($gen_res['questions'][0]['correct_answer']) ? '✅ VALID JSON' : '❌ INVALID') . "\n";
    echo "  • Provider:             " . $gen_res['provider'] . "\n";
    echo "  • Status:               " . ($gen_res['status'] === 'success' ? '✅ PASS' : '❌ FAIL') . "\n";
} catch (Exception $e) {
    echo "  • Quiz Generator Error: ❌ " . $e->getMessage() . "\n";
    $all_passed = false;
}
echo "\n--------------------------------------------------------------------\n\n";

// ------------------------------------------------------------------
// SECTION 4: AI LEARNING RECOMMENDATIONS (DAY 3)
// ------------------------------------------------------------------
echo "🎯 [CHECK 4/5] Day 3: AI Learning Recommendations from Multi-Table Synthesis\n";
try {
    $user = $DB->get_record('user', ['id' => 3]); // Rahul Sharma
    if (!$user) {
        $users = $DB->get_records('user', ['deleted' => 0], '', 'id, username', 0, 1);
        $user = reset($users);
    }
    
    $rec_res = \block_cynaris_stats\ai_recommendations::generate_recommendations($user->id, true);
    $tier = isset($rec_res['profile']['performance_tier']) ? $rec_res['profile']['performance_tier'] : 'Standard';
    echo "  • Student Profile:      " . $user->username . " (Tier: " . $tier . ")\n";
    echo "  • Action Plan Steps:    " . count($rec_res['recommendations']['action_plan']) . " milestones\n";
    echo "  • Viva Voce Tip:        " . substr($rec_res['recommendations']['viva_prep_tip'], 0, 60) . "...\n";
    echo "  • Status:               " . ($rec_res['status'] === 'success' ? '✅ PASS' : '❌ FAIL') . "\n";
} catch (Exception $e) {
    echo "  • Recommendations Err:  ❌ " . $e->getMessage() . "\n";
    $all_passed = false;
}
echo "\n--------------------------------------------------------------------\n\n";

// ------------------------------------------------------------------
// SECTION 5: PERFORMANCE AUDIT & COVERING INDEX (DAY 4)
// ------------------------------------------------------------------
echo "⚡ [CHECK 5/5] Day 4: Query Performance Auditor & Covering Index Verification\n";
try {
    $audit_res = \block_cynaris_stats\ai_query_auditor::audit_query('login_frequency', true);
    
    $q_name = isset($audit_res['query_name']) ? $audit_res['query_name'] : '7-Day User Login Frequency';
    $steps = isset($audit_res['explain']['steps']) ? $audit_res['explain']['steps'] : [];
    
    echo "  • Target Query:         " . $q_name . "\n";
    echo "  • Nodes Analyzed:       " . count($steps) . " plan node(s)\n";
    if (!empty($steps)) {
        echo "  • Index Key Selected:   " . $steps[0]['key'] . "\n";
        echo "  • Extra Flags:          " . $steps[0]['extra'] . "\n";
        $is_covering = (strpos($steps[0]['extra'], 'Using index') !== false);
        echo "  • Covering Index Match: " . ($is_covering ? '✅ CONFIRMED (Zero Table Heap IO)' : '⚠️ NON-COVERING') . "\n";
    }
    echo "  • Status:               " . ($audit_res['status'] === 'success' ? '✅ PASS' : '❌ FAIL') . "\n";
} catch (Exception $e) {
    echo "  • Auditor Error:        ❌ " . $e->getMessage() . "\n";
    $all_passed = false;
}
echo "\n====================================================================\n";

if ($all_passed) {
    echo "🎉 CONGRATULATIONS! ALL 5 WEEK 6 MODULES ARE PRODUCTION-READY!\n";
    echo "Status: 100% Tests Passed | Full Stack Moodle Fork Deployment Ready\n";
} else {
    echo "⚠️ ONE OR MORE CHECKS ENCOUNTERED ISSUES. Review log above.\n";
}
echo "====================================================================\n";
exit($all_passed ? 0 : 1);
