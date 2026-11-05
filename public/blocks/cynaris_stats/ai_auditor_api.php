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
 * AJAX AI Query Performance Auditor Service Endpoint.
 *
 * @package    block_cynaris_stats
 * @copyright  2026 Uday Reddy (b.udayreddy5643@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/ai_query_auditor.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    $raw_input = file_get_contents('php://input');
    $input = [];
    if (!empty($raw_input)) {
        $input = json_decode($raw_input, true) ?? [];
    }

    $query_key = $input['query_key'] ?? optional_param('query_key', 'login_frequency', PARAM_RAW_TRIMMED);
    $force_fallback = (bool)($input['force_fallback'] ?? optional_param('force_fallback', 0, PARAM_BOOL));

    $result = \block_cynaris_stats\ai_query_auditor::audit_query(
        $query_key,
        $force_fallback
    );

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'AI Query Auditor failed: ' . $e->getMessage(),
    ]);
}
