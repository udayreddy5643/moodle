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
 * AJAX AI Tutor endpoint supporting Groq primary and OpenRouter fallback.
 *
 * @package    block_cynaris_stats
        * @copyright  2026 Uday Reddy (b.udayreddy5643@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/ai_tutor.php');

// Set JSON response headers.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    // Read input from JSON body or standard request parameters.
    $raw_input = file_get_contents('php://input');
    $input_data = [];
    if (!empty($raw_input)) {
        $input_data = json_decode($raw_input, true) ?? [];
    }

    $question = $input_data['question'] ?? optional_param('question', '', PARAM_RAW_TRIMMED);
    $force_fallback = (bool)($input_data['force_fallback'] ?? optional_param('force_fallback', 0, PARAM_BOOL));
    $context = $input_data['context'] ?? optional_param('context', null, PARAM_RAW_TRIMMED);

    if (empty($question)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Query parameter "question" is required.',
        ]);
        exit;
    }

    // Call the resilient multi-provider AI Tutor engine.
    $response = \block_cynaris_stats\ai_tutor::ask($question, $context, $force_fallback);

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal AI Tutor error: ' . $e->getMessage(),
    ]);
}
