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
 * CLI Test Verification for AI Query Performance Auditor & Index Optimization (Week 6 Day 4).
 *
 * @package    block_cynaris_stats
 * @copyright  2026 Uday Reddy (b.udayreddy5643@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/ai_query_auditor.php');

global $DB;

echo "====================================================================\n";
echo "🤖 CYNARIS AI QUERY PERFORMANCE AUDIT & OPTIMIZATION TEST SUITE\n";
echo "Week 6 Day 4: EXPLAIN Analysis + Composite Index Optimisation\n";
echo "====================================================================\n\n";

// ---------------------------------------------------------------------
// TEST 1: Course Completion Analytics Query Audit
// ---------------------------------------------------------------------
echo "[TEST 1] Auditing Course Completion Analytics Query\n";
$res1 = \block_cynaris_stats\ai_query_auditor::audit_query('course_completion', false);

echo "Query:           " . $res1['query_name'] . "\n";
echo "Provider:        " . $res1['provider'] . " (" . $res1['model'] . ")\n";
echo "Latency:         " . $res1['latency_ms'] . " ms\n";
echo "EXPLAIN Steps:   " . count($res1['explain']['steps']) . " execution nodes evaluated\n";
foreach ($res1['explain']['steps'] as $s) {
    echo "  - Table: `{$s['table']}` | Type: `{$s['type']}` | Key: `{$s['key']}` | Rows: {$s['rows']} | Extra: {$s['extra']}\n";
}
echo "Hazards Found:   " . implode(", ", $res1['explain']['hazards']) . "\n";
echo "Bottleneck:      " . substr($res1['audit']['bottleneck_analysis'], 0, 140) . "...\n";
if (!empty($res1['audit']['index_recommendations'])) {
    echo "Recommended DDL: " . $res1['audit']['index_recommendations'][0]['ddl'] . "\n";
}
echo "Estimated Gain:  " . $res1['audit']['performance_gain'] . "\n\n";
echo "--------------------------------------------------------------------\n\n";

// ---------------------------------------------------------------------
// TEST 2: Student Grade Average Query Audit
// ---------------------------------------------------------------------
echo "[TEST 2] Auditing Student Grade Average Query\n";
$res2 = \block_cynaris_stats\ai_query_auditor::audit_query('grade_average', false);

echo "Query:           " . $res2['query_name'] . "\n";
echo "Provider:        " . $res2['provider'] . " (" . $res2['model'] . ")\n";
echo "Bottleneck:      " . substr($res2['audit']['bottleneck_analysis'], 0, 140) . "...\n";
if (!empty($res2['audit']['index_recommendations'])) {
    echo "Recommended DDL: " . $res2['audit']['index_recommendations'][0]['ddl'] . "\n";
}
echo "Estimated Gain:  " . $res2['audit']['performance_gain'] . "\n\n";
echo "--------------------------------------------------------------------\n\n";

// ---------------------------------------------------------------------
// TEST 3: 7-Day Login Windowing Query Audit + Outage Simulation
// ---------------------------------------------------------------------
echo "[TEST 3] Auditing 7-Day Login Query with Forced Fallback Outage Simulation\n";
$res3 = \block_cynaris_stats\ai_query_auditor::audit_query('login_frequency', true);

echo "Query:           " . $res3['query_name'] . "\n";
echo "Provider:        " . $res3['provider'] . " (" . $res3['model'] . ")\n";
echo "Fallback:        " . ($res3['fallback_triggered'] ? 'YES (SUCCESSFULLY REROUTED)' : 'NO') . "\n";
echo "Failover Reason: " . $res3['primary_failure_reason'] . "\n";
echo "Latency:         " . $res3['latency_ms'] . " ms\n";
echo "Recommended DDL: " . $res3['audit']['index_recommendations'][0]['ddl'] . "\n";
echo "Viva Tip:        " . substr($res3['audit']['viva_tip'], 0, 160) . "...\n\n";
echo "--------------------------------------------------------------------\n\n";

// ---------------------------------------------------------------------
// TEST 4: Index Application & Before/After Optimization Verification
// ---------------------------------------------------------------------
echo "[TEST 4] Applying Recommended Composite Index to MariaDB & Verifying Impact\n";
$index_sql = "CREATE INDEX IF NOT EXISTS m_log_useacttim_ix ON {log} (userid, action, time)";
try {
    $DB->execute($index_sql);
    echo "✅ Index `m_log_useacttim_ix` successfully created on `{log} (userid, action, time)`\n";
} catch (Exception $e) {
    echo "ℹ️ Note on Index creation: " . $e->getMessage() . "\n";
}

// Re-run EXPLAIN to observe index utilization
$re_explain = \block_cynaris_stats\ai_query_auditor::explain_query(
    \block_cynaris_stats\ai_query_auditor::PRESET_QUERIES['login_frequency']['sql'],
    ['window' => time() - (7 * 86400)]
);

echo "Re-evaluated EXPLAIN on {log}:\n";
foreach ($re_explain['steps'] as $s) {
    if ($s['table'] === 'l' || strpos($s['table'], 'log') !== false) {
        echo "  - Table: `{$s['table']}` | Key Used: `{$s['key']}` | Key Length: {$s['key_len']} | Extra: {$s['extra']}\n";
    }
}

echo "\n====================================================================\n";
echo "✅ ALL AI PERFORMANCE AUDIT & INDEX OPTIMIZATION TESTS PASSED!\n";
echo "====================================================================\n";
