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
 * AJAX AI Quiz Generation Service Endpoint.
 * Generates MCQs from course content, imports to Question Bank, and creates Quiz instance.
 *
 * @package    block_cynaris_stats
 * @copyright  2026 Uday Reddy (b.udayreddy5643@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/ai_quiz_generator.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    $raw_input = file_get_contents('php://input');
    $input = [];
    if (!empty($raw_input)) {
        $input = json_decode($raw_input, true) ?? [];
    }

    $course_id = (int)($input['course_id'] ?? optional_param('course_id', 2, PARAM_INT));
    $count = (int)($input['count'] ?? optional_param('count', 5, PARAM_INT));
    $quiz_name = $input['quiz_name'] ?? optional_param('quiz_name', '', PARAM_RAW_TRIMMED);
    $force_fallback = (bool)($input['force_fallback'] ?? optional_param('force_fallback', 0, PARAM_BOOL));

    if ($count < 1 || $count > 20) {
        $count = 5;
    }

    $result = \block_cynaris_stats\ai_quiz_generator::generate_and_publish_quiz(
        $course_id,
        $count,
        $quiz_name ?: null,
        $force_fallback
    );

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'AI Quiz Generation failed: ' . $e->getMessage(),
    ]);
}
