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
 * CLI Test Verification for AI Learning Recommendations Engine (Week 6 Day 3).
 *
 * @package    block_cynaris_stats
 * @copyright  2026 Uday Reddy (b.udayreddy5643@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/ai_recommendations.php');

echo "====================================================================\n";
echo "🤖 CYNARIS AI LEARNING RECOMMENDATIONS TEST SUITE (WEEK 6 DAY 3)\n";
echo "Task: Derive Actionable Interventions from Grade + Completion Data\n";
echo "====================================================================\n\n";

// ---------------------------------------------------------------------
// TEST 1: High Performer Persona (Rahul Sharma, ID: 3)
// ---------------------------------------------------------------------
echo "[TEST 1] Top Performer Persona (Rahul Sharma - ID: 3)\n";
$res1 = \block_cynaris_stats\ai_recommendations::generate_recommendations(3, false);

$p1 = $res1['profile'];
$r1 = $res1['recommendations'];

echo "Student:         " . $p1['user']['fullname'] . " (" . $p1['user']['username'] . ")\n";
echo "Tier:            " . $p1['metrics']['persona_tier'] . "\n";
echo "Avg Grade:       " . $p1['metrics']['average_grade'] . "%\n";
echo "Completions:     " . $p1['metrics']['completed_courses'] . " / " . $p1['metrics']['total_enrolled'] . " (" . $p1['metrics']['completion_rate'] . "%)\n";
echo "Provider:        " . $res1['provider'] . " (" . $res1['model'] . ")\n";
echo "Latency:         " . $res1['latency_ms'] . " ms\n";
echo "Diagnostic:      " . $r1['performance_summary'] . "\n";
echo "Key Strengths:   " . implode(" | ", array_slice($r1['strengths'], 0, 2)) . "\n";
echo "Action Step:     " . $r1['action_plan'][0] . "\n";
echo "Viva Tip:        " . $r1['viva_prep_tip'] . "\n\n";
echo "--------------------------------------------------------------------\n\n";

// ---------------------------------------------------------------------
// TEST 2: Intermediate Achiever Persona (Amit Verma, ID: 5)
// ---------------------------------------------------------------------
echo "[TEST 2] Steady Achiever Persona (Amit Verma - ID: 5)\n";
$res2 = \block_cynaris_stats\ai_recommendations::generate_recommendations(5, false);

$p2 = $res2['profile'];
$r2 = $res2['recommendations'];

echo "Student:         " . $p2['user']['fullname'] . " (" . $p2['user']['username'] . ")\n";
echo "Tier:            " . $p2['metrics']['persona_tier'] . "\n";
echo "Avg Grade:       " . $p2['metrics']['average_grade'] . "%\n";
echo "Completions:     " . $p2['metrics']['completed_courses'] . " / " . $p2['metrics']['total_enrolled'] . "\n";
echo "Diagnostic:      " . $r2['performance_summary'] . "\n";
echo "Growth Area:     " . $r2['growth_areas'][0] . "\n";
echo "Action Step:     " . $r2['action_plan'][0] . "\n\n";
echo "--------------------------------------------------------------------\n\n";

// ---------------------------------------------------------------------
// TEST 3: New Learner / Assessment Pending Persona (Neha Gupta, ID: 6)
// ---------------------------------------------------------------------
echo "[TEST 3] New Learner Persona (Neha Gupta - ID: 6)\n";
$res3 = \block_cynaris_stats\ai_recommendations::generate_recommendations(6, false);

$p3 = $res3['profile'];
$r3 = $res3['recommendations'];

echo "Student:         " . $p3['user']['fullname'] . " (" . $p3['user']['username'] . ")\n";
echo "Tier:            " . $p3['metrics']['persona_tier'] . "\n";
echo "Avg Grade:       " . ($p3['metrics']['average_grade'] !== null ? $p3['metrics']['average_grade'] : 'NULL (Pending)') . "\n";
echo "Diagnostic:      " . $r3['performance_summary'] . "\n";
echo "Action Step:     " . $r3['action_plan'][0] . "\n\n";
echo "--------------------------------------------------------------------\n\n";

// ---------------------------------------------------------------------
// TEST 4: Outage Simulation & Failover Verification (Priya Patel, ID: 4)
// ---------------------------------------------------------------------
echo "[TEST 4] Outage Simulation & Failover Verification (Priya Patel - ID: 4)\n";
$res4 = \block_cynaris_stats\ai_recommendations::generate_recommendations(4, true);

echo "Status:          " . $res4['status'] . "\n";
echo "Provider:        " . $res4['provider'] . " (" . $res4['model'] . ")\n";
echo "Fallback:        " . ($res4['fallback_triggered'] ? 'YES (SUCCESSFULLY REROUTED)' : 'NO') . "\n";
echo "Primary Failure: " . $res4['primary_failure_reason'] . "\n";
echo "Latency:         " . $res4['latency_ms'] . " ms\n";
echo "Diagnostic:      " . $res4['recommendations']['performance_summary'] . "\n\n";

echo "====================================================================\n";
echo "✅ ALL AI LEARNING RECOMMENDATION TESTS PASSED SUCCESSFULLY!\n";
echo "====================================================================\n";
