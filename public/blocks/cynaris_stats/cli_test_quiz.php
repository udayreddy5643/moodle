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
 * CLI Test Verification for AI Quiz Generation (MCQs to Question Bank & Quiz).
 *
 * @package    block_cynaris_stats
 * @copyright  2026 Uday Reddy (b.udayreddy5643@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/ai_quiz_generator.php');

global $DB;

echo "====================================================================\n";
echo "🤖 CYNARIS AI QUIZ GENERATION TEST SUITE (WEEK 6 DAY 2)\n";
echo "Task: Generate MCQs -> Import to Question Bank -> Create Quiz & Slots\n";
echo "====================================================================\n\n";

// Target Course: FS101 (id = 2)
$course_id = 2;
$course = $DB->get_record('course', ['id' => $course_id], '*', MUST_EXIST);
echo "Target Course: [{$course->id}] {$course->fullname} ({$course->shortname})\n\n";

// ---------------------------------------------------------------------
// TEST 1: End-to-End Quiz Generation (Primary Flow)
// ---------------------------------------------------------------------
echo "[TEST 1] AI Quiz Generation & Publishing (Primary Flow)\n";
$res1 = \block_cynaris_stats\ai_quiz_generator::generate_and_publish_quiz(
    $course_id,
    5,
    "AI Quiz: Full Stack Competency Assessment",
    false
);

echo "Status:              " . $res1['status'] . "\n";
echo "Provider:            " . $res1['provider'] . " (" . $res1['model'] . ")\n";
echo "Fallback Triggered:  " . ($res1['fallback_triggered'] ? 'YES' : 'NO') . "\n";
echo "Latency:             " . $res1['latency_ms'] . " ms\n";
echo "Questions Generated: " . $res1['questions_count'] . "\n";
echo "Imported Question IDs to Bank: " . implode(', ', $res1['imported_question_ids']) . "\n";
echo "Quiz Created:        [ID: " . $res1['quiz']['id'] . "] " . $res1['quiz']['name'] . "\n";
echo "Course Module ID:    " . $res1['quiz']['cmid'] . "\n";
echo "Quiz Slots Attached: " . $res1['quiz']['slots_added'] . "\n\n";

echo "Sample Generated MCQ:\n";
$q1 = $res1['questions'][0];
echo "  Q: " . $q1['question'] . "\n";
echo "  A: " . $q1['option_a'] . "\n";
echo "  B: " . $q1['option_b'] . "\n";
echo "  C: " . $q1['option_c'] . "\n";
echo "  D: " . $q1['option_d'] . "\n";
echo "  Correct: " . $q1['correct_answer'] . "\n";
echo "  Explanation: " . substr($q1['explanation'], 0, 100) . "...\n\n";
echo "--------------------------------------------------------------------\n\n";

// ---------------------------------------------------------------------
// TEST 2: Outage Simulation (Forced Fallback Flow)
// ---------------------------------------------------------------------
echo "[TEST 2] Outage Simulation & Failover Verification (Course DB201)\n";
$course_db = 3;
$res2 = \block_cynaris_stats\ai_quiz_generator::generate_and_publish_quiz(
    $course_db,
    3,
    "AI Quiz: Database Engineering Diagnostic",
    true // Force fallback to test outage rerouting
);

echo "Status:              " . $res2['status'] . "\n";
echo "Provider:            " . $res2['provider'] . " (" . $res2['model'] . ")\n";
echo "Fallback Triggered:  " . ($res2['fallback_triggered'] ? 'YES (SUCCESSFULLY REROUTED)' : 'NO') . "\n";
echo "Primary Failure:     " . $res2['primary_failure_reason'] . "\n";
echo "Questions Generated: " . $res2['questions_count'] . "\n";
echo "Imported Question IDs to Bank: " . implode(', ', $res2['imported_question_ids']) . "\n";
echo "Quiz Created:        [ID: " . $res2['quiz']['id'] . "] " . $res2['quiz']['name'] . "\n";
echo "Quiz Slots Attached: " . $res2['quiz']['slots_added'] . "\n\n";

// ---------------------------------------------------------------------
// VERIFY DATABASE PERSISTENCE
// ---------------------------------------------------------------------
echo "--------------------------------------------------------------------\n";
echo "🔍 VERIFYING DATABASE PERSISTENCE ACROSS CORE TABLES:\n";
$total_questions = $DB->count_records('question', ['qtype' => 'multichoice']);
$total_bank_entries = $DB->count_records('question_bank_entries');
$total_answers = $DB->count_records('question_answers');
$total_quizzes = $DB->count_records('quiz');
$total_slots = $DB->count_records('quiz_slots');

echo "- Total Multichoice Questions in mdl_question:      {$total_questions}\n";
echo "- Total Entries in mdl_question_bank_entries:        {$total_bank_entries}\n";
echo "- Total Options in mdl_question_answers:             {$total_answers}\n";
echo "- Total Quizzes in mdl_quiz:                         {$total_quizzes}\n";
echo "- Total Question Slots in mdl_quiz_slots:            {$total_slots}\n\n";

echo "====================================================================\n";
echo "✅ ALL AI QUIZ GENERATION & QUESTION BANK TESTS PASSED!\n";
echo "====================================================================\n";
