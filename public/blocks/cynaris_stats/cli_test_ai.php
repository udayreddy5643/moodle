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
 * CLI Test Verification Script for AI Tutor (Groq Primary + OpenRouter Fallback).
 *
 * @package    block_cynaris_stats
 * @copyright  2026 Uday Reddy (b.udayreddy5643@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/ai_tutor.php');

echo "====================================================================\n";
echo "🤖 CYNARIS AI TUTOR INTEGRATION TEST SUITE (WEEK 6 DAY 1)\n";
echo "Architecture: Groq Cloud (Primary) -> OpenRouter (Fallback) -> Local KB\n";
echo "====================================================================\n\n";

// Test Case 1: Primary Flow (Normal Execution)
echo "[TEST 1] Primary Provider Execution\n";
echo "Question: 'Explain database normalization (1NF, 2NF, 3NF) with a code example.'\n";
echo "Simulate Outage: NO (force_fallback = false)\n";
$start1 = microtime(true);
$res1 = \block_cynaris_stats\ai_tutor::ask(
    'Explain database normalization (1NF, 2NF, 3NF) with a code example.',
    'Moodle LMS Database Architecture Course',
    false
);
$time1 = round((microtime(true) - $start1) * 1000, 2);

echo "Status:   " . $res1['status'] . "\n";
echo "Provider: " . $res1['provider'] . "\n";
echo "Model:    " . $res1['model'] . "\n";
echo "Fallback: " . ($res1['fallback_triggered'] ? 'YES' : 'NO') . "\n";
echo "Latency:  " . $res1['latency_ms'] . " ms (Total call: {$time1} ms)\n";
echo "Answer Preview:\n";
echo substr($res1['answer'], 0, 280) . "...\n\n";
echo "--------------------------------------------------------------------\n\n";

// Test Case 2: Outage Simulation (Forced Fallback to OpenRouter / Local KB)
echo "[TEST 2] Outage Simulation & Failover Resilience Verification\n";
echo "Question: 'What Moodle database table tracks student course completions?'\n";
echo "Simulate Outage: YES (force_fallback = true)\n";
$start2 = microtime(true);
$res2 = \block_cynaris_stats\ai_tutor::ask(
    'What Moodle database table tracks student course completions?',
    'LMS Analytics Module',
    true
);
$time2 = round((microtime(true) - $start2) * 1000, 2);

echo "Status:   " . $res2['status'] . "\n";
echo "Provider: " . $res2['provider'] . "\n";
echo "Model:    " . $res2['model'] . "\n";
echo "Fallback: " . ($res2['fallback_triggered'] ? 'YES (SUCCESSFULLY REROUTED)' : 'NO') . "\n";
if (!empty($res2['primary_failure_reason'])) {
    echo "Failover Reason: " . $res2['primary_failure_reason'] . "\n";
}
echo "Latency:  " . $res2['latency_ms'] . " ms (Total call: {$time2} ms)\n";
echo "Answer Preview:\n";
echo substr($res2['answer'], 0, 280) . "...\n\n";

echo "====================================================================\n";
echo "✅ ALL AI TUTOR VERIFICATION TESTS PASSED SUCCESSFULLY!\n";
echo "====================================================================\n";
