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

namespace block_cynaris_stats;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * AI Quiz Generator Engine: Generates MCQs from Course Content to Moodle Question Bank & Quizzes.
 * Supports Groq Cloud primary with OpenRouter fallback and deterministic curriculum resilience.
 *
 * @package    block_cynaris_stats
 * @copyright  2026 Uday Reddy (b.udayreddy5643@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_quiz_generator {

    /**
     * Primary LLM Provider configuration (Groq Cloud).
     */
    const GROQ_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    const GROQ_DEFAULT_MODEL = 'llama-3.3-70b-versatile';

    /**
     * Fallback LLM Provider configuration (OpenRouter AI).
     */
    const OPENROUTER_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
    const OPENROUTER_DEFAULT_MODEL = 'meta-llama/llama-3.1-8b-instruct:free';

    /**
     * AI QUIZ GENERATOR SYSTEM PROMPT DESIGN:
     * ----------------------------------------
     * 1. Role: Senior Pedagogical Assessment Engineer & Full Stack Curriculum Specialist.
     * 2. Objective: Generate strictly formatted JSON multiple-choice questions (MCQs)
     *    directly derived from course topics, targeting Bloom's Taxonomy (Understanding, Application, Analysis).
     * 3. Schema:
     *    [
     *      {
     *        "question": "Clear, unambiguous question text",
     *        "option_a": "Option A text",
     *        "option_b": "Option B text",
     *        "option_c": "Option C text",
     *        "option_d": "Option D text",
     *        "correct_answer": "option_a" | "option_b" | "option_c" | "option_d",
     *        "explanation": "Detailed pedagogical explanation of why this answer is correct"
     *      }
     *    ]
     * 4. Output Rule: STRICT raw JSON only — no conversational filler, no markdown wrappers, no backticks.
     */
    const SYSTEM_PROMPT = <<<EOT
You are an expert academic curriculum assessment engineer.
Generate high-quality multiple choice questions (MCQs) based on the provided course title, code, and syllabus summary.
Requirements:
1. Return ONLY a valid JSON array of question objects without markdown backticks or commentary.
2. Each object MUST contain exact keys: "question", "option_a", "option_b", "option_c", "option_d", "correct_answer", "explanation".
3. "correct_answer" MUST be one of: "option_a", "option_b", "option_c", "option_d".
4. Focus on technical accuracy, practical coding scenarios, relational schema design, and enterprise patterns.
EOT;

    /**
     * Generate MCQs from course content using Groq Cloud primary with OpenRouter fallback.
     *
     * @param int $course_id Target Moodle course ID.
     * @param int $count Number of MCQs to generate (default 5).
     * @param bool $force_fallback If true, skips Groq to simulate an outage and test fallback.
     * @return array ['status' => string, 'provider' => string, 'model' => string, 'questions' => array, 'latency_ms' => int]
     */
    public static function generate_quiz_questions(int $course_id, int $count = 5, bool $force_fallback = false): array {
        global $DB;
        $start_time = microtime(true);
        $groq_error = null;

        $course = $DB->get_record('course', ['id' => $course_id], '*', MUST_EXIST);
        $summary = strip_tags($course->summary ?: $course->fullname);

        $prompt = "Generate {$count} challenging and pedagogically rigorous multiple-choice questions (MCQs) for the Moodle course: '{$course->fullname}' (Code: {$course->shortname}).\n"
                . "Course Syllabus / Context: {$summary}.\n"
                . "Return strictly a JSON array of {$count} question objects.";

        // Retrieve API credentials.
        $groq_key = get_config('block_cynaris_stats', 'groq_api_key') ?: getenv('GROQ_API_KEY');
        $openrouter_key = get_config('block_cynaris_stats', 'openrouter_api_key') ?: getenv('OPENROUTER_API_KEY');

        // ---------------------------------------------------------------------
        // Step 1: Attempt Primary Provider (Groq Cloud)
        // ---------------------------------------------------------------------
        if (!$force_fallback && !empty($groq_key)) {
            $groq_res = self::call_api(
                self::GROQ_ENDPOINT,
                $groq_key,
                self::GROQ_DEFAULT_MODEL,
                $prompt,
                12
            );

            if ($groq_res['success']) {
                $parsed = self::parse_and_validate_json($groq_res['content']);
                if (!empty($parsed)) {
                    $duration = round((microtime(true) - $start_time) * 1000);
                    return [
                        'status' => 'success',
                        'provider' => 'Groq Cloud (Primary)',
                        'model' => self::GROQ_DEFAULT_MODEL,
                        'questions' => array_slice($parsed, 0, $count),
                        'fallback_triggered' => false,
                        'latency_ms' => $duration,
                    ];
                } else {
                    $groq_error = 'JSON parsing failed from Groq output';
                }
            } else {
                $groq_error = $groq_res['error'];
            }
        } else {
            $groq_error = $force_fallback ? 'Simulated Groq Outage (Fallback Testing Mode)' : 'Groq API Key not configured';
        }

        // ---------------------------------------------------------------------
        // Step 2: Attempt Fallback Provider (OpenRouter AI)
        // ---------------------------------------------------------------------
        if (!empty($openrouter_key)) {
            $openrouter_res = self::call_api(
                self::OPENROUTER_ENDPOINT,
                $openrouter_key,
                self::OPENROUTER_DEFAULT_MODEL,
                $prompt,
                15,
                ['HTTP-Referer' => 'http://localhost:8000', 'X-Title' => 'Cynaris Quiz Generator']
            );

            if ($openrouter_res['success']) {
                $parsed = self::parse_and_validate_json($openrouter_res['content']);
                if (!empty($parsed)) {
                    $duration = round((microtime(true) - $start_time) * 1000);
                    return [
                        'status' => 'success',
                        'provider' => 'OpenRouter AI (Fallback)',
                        'model' => self::OPENROUTER_DEFAULT_MODEL,
                        'questions' => array_slice($parsed, 0, $count),
                        'fallback_triggered' => true,
                        'primary_failure_reason' => $groq_error,
                        'latency_ms' => $duration,
                    ];
                }
            }
        }

        // ---------------------------------------------------------------------
        // Step 3: Local Offline Deterministic Knowledge Base (Zero-Downtime Safety Net)
        // ---------------------------------------------------------------------
        $duration = round((microtime(true) - $start_time) * 1000);
        $questions = self::get_deterministic_curriculum_questions($course, $count);

        return [
            'status' => 'success',
            'provider' => 'Cynaris Curriculum Assessment Engine (Deterministic Fallback)',
            'model' => 'cynaris-quiz-curriculum-kb-v1',
            'questions' => $questions,
            'fallback_triggered' => true,
            'primary_failure_reason' => $groq_error,
            'latency_ms' => $duration,
        ];
    }

    /**
     * Import an array of MCQ questions into Moodle's Question Bank.
     *
     * @param int $course_id Course ID.
     * @param array $questions Array of validated question dictionaries.
     * @return array Array of created question IDs in mdl_question.
     */
    public static function import_questions_to_question_bank(int $course_id, array $questions): array {
        global $DB;

        $course = $DB->get_record('course', ['id' => $course_id], '*', MUST_EXIST);
        $context = \context_course::instance($course_id);

        // Find or create default question category for course.
        $category = $DB->get_record('question_categories', ['contextid' => $context->id, 'parent' => 0]);
        if (!$category) {
            $cat = new \stdClass();
            $cat->name = 'Default for ' . $course->shortname;
            $cat->contextid = $context->id;
            $cat->info = 'Default question category for course ' . $course->shortname;
            $cat->infoformat = FORMAT_HTML;
            $cat->stamp = make_unique_id_code();
            $cat->parent = 0;
            $cat->sortorder = 999;
            $cat->idnumber = null;
            $cat_id = $DB->insert_record('question_categories', $cat);
        } else {
            $cat_id = $category->id;
        }

        $imported_question_ids = [];

        foreach ($questions as $q) {
            // 1. Insert into mdl_question
            $question = new \stdClass();
            $question->category = $cat_id;
            $question->parent = 0;
            $question->name = shorten_text($q['question'], 45);
            $question->questiontext = $q['question'];
            $question->questiontextformat = FORMAT_HTML;
            $question->generalfeedback = $q['explanation'];
            $question->generalfeedbackformat = FORMAT_HTML;
            $question->defaultmark = 1.0000000;
            $question->penalty = 0.3333333;
            $question->qtype = 'multichoice';
            $question->length = 1;
            $question->stamp = make_unique_id_code();
            $question->version = make_unique_id_code();
            $question->timecreated = time();
            $question->timemodified = time();
            $question->createdby = 2; // Admin user
            $question->modifiedby = 2;
            $qid = $DB->insert_record('question', $question);

            // 2. Insert into mdl_question_bank_entries (Moodle 4.x/5.x/6.x architecture)
            $qbe = new \stdClass();
            $qbe->questioncategoryid = $cat_id;
            $qbe->idnumber = null;
            $qbe->ownerid = 2;
            $qbe_id = $DB->insert_record('question_bank_entries', $qbe);

            // 3. Insert into mdl_question_versions
            $qv = new \stdClass();
            $qv->questionbankentryid = $qbe_id;
            $qv->version = 1;
            $qv->questionid = $qid;
            $qv->status = 'ready';
            $DB->insert_record('question_versions', $qv);

            // 4. Insert 4 options into mdl_question_answers
            $options = [
                'option_a' => $q['option_a'],
                'option_b' => $q['option_b'],
                'option_c' => $q['option_c'],
                'option_d' => $q['option_d'],
            ];

            $correct_key = strtolower(trim($q['correct_answer']));

            foreach ($options as $key => $opt_text) {
                $is_correct = ($key === $correct_key);
                $answer = new \stdClass();
                $answer->question = $qid;
                $answer->answer = $opt_text;
                $answer->answerformat = FORMAT_HTML;
                $answer->fraction = $is_correct ? 1.0000000 : 0.0000000;
                $answer->feedback = $is_correct ? ('Correct! ' . $q['explanation']) : ('Incorrect. ' . $q['explanation']);
                $answer->feedbackformat = FORMAT_HTML;
                $DB->insert_record('question_answers', $answer);
            }

            // 5. Insert into mdl_qtype_multichoice_options
            $mco = new \stdClass();
            $mco->questionid = $qid;
            $mco->layout = 0;
            $mco->single = 1; // Single correct choice
            $mco->shuffleanswers = 1;
            $mco->correctfeedback = 'Your answer is correct.';
            $mco->correctfeedbackformat = FORMAT_HTML;
            $mco->partiallycorrectfeedback = 'Your answer is partially correct.';
            $mco->partiallycorrectfeedbackformat = FORMAT_HTML;
            $mco->incorrectfeedback = 'Your answer is incorrect.';
            $mco->incorrectfeedbackformat = FORMAT_HTML;
            $mco->answernumbering = 'abc';
            $mco->shownumcorrect = 0;
            $mco->showstandardinstruction = 1;
            $DB->insert_record('qtype_multichoice_options', $mco);

            $imported_question_ids[] = $qid;
        }

        return $imported_question_ids;
    }

    /**
     * Programmatically create a Quiz activity in the course and populate it with question IDs.
     *
     * @param int $course_id Course ID.
     * @param string $quiz_name Quiz title.
     * @param array $question_ids Array of question IDs in mdl_question.
     * @return \stdClass Quiz instance with coursemodule ID and slot count.
     */
    public static function create_quiz_for_course(int $course_id, string $quiz_name, array $question_ids): \stdClass {
        global $DB;

        $quiz_module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);

        // 1. Create quiz record in mdl_quiz
        $quiz = new \stdClass();
        $quiz->course = $course_id;
        $quiz->name = $quiz_name;
        $quiz->intro = '<p>AI-Generated Assessment created automatically from course syllabus and persisted to Question Bank.</p>';
        $quiz->introformat = FORMAT_HTML;
        $quiz->timeopen = 0;
        $quiz->timeclose = 0;
        $quiz->timelimit = 0;
        $quiz->overduehandling = 'autosubmit';
        $quiz->graceperiod = 0;
        $quiz->preferredbehaviour = 'deferredfeedback';
        $quiz->canredoquestions = 0;
        $quiz->attempts = 0;
        $quiz->grademethod = 1;
        $quiz->decimalpoints = 2;
        $quiz->questiondecimalpoints = -1;
        $quiz->reviewattempt = 69888;
        $quiz->reviewcorrectness = 4352;
        $quiz->reviewmaxmarks = 4352;
        $quiz->reviewmarks = 4352;
        $quiz->reviewspecificfeedback = 4352;
        $quiz->reviewgeneralfeedback = 4352;
        $quiz->reviewrightanswer = 4352;
        $quiz->reviewoverallfeedback = 4352;
        $quiz->questionsperpage = 1;
        $quiz->navmethod = 'free';
        $quiz->shuffleanswers = 1;
        $quiz->sumgrades = count($question_ids);
        $quiz->grade = 100.00000;
        $quiz->timecreated = time();
        $quiz->timemodified = time();
        $quiz->id = $DB->insert_record('quiz', $quiz);

        // 2. Create course module in mdl_course_modules
        $cm = new \stdClass();
        $cm->course = $course_id;
        $cm->module = $quiz_module->id;
        $cm->instance = $quiz->id;
        $cm->section = 1;
        $cm->visible = 1;
        $cm->added = time();
        $cmid = $DB->insert_record('course_modules', $cm);
        $quiz->cmid = $cmid;

        // Attach module to course section 1
        course_add_cm_to_section($course_id, $cmid, 1);
        rebuild_course_cache($course_id, true);

        // 3. Add questions to quiz slots using Moodle's native quiz_add_quiz_question()
        $slots_added = 0;
        foreach ($question_ids as $qid) {
            $added = quiz_add_quiz_question($qid, $quiz);
            if ($added !== false) {
                $slots_added++;
            }
        }

        $quiz->slots_added = $slots_added;
        return $quiz;
    }

    /**
     * End-to-end pipeline: Generates questions, saves to Question Bank, creates Quiz, and links questions.
     *
     * @param int $course_id Course ID.
     * @param int $count Question count.
     * @param string|null $quiz_name Custom quiz name.
     * @param bool $force_fallback Outage simulation flag.
     * @return array Comprehensive result metadata.
     */
    public static function generate_and_publish_quiz(
        int $course_id,
        int $count = 5,
        ?string $quiz_name = null,
        bool $force_fallback = false
    ): array {
        global $DB;

        $course = $DB->get_record('course', ['id' => $course_id], '*', MUST_EXIST);
        if (empty($quiz_name)) {
            $quiz_name = "AI Quiz: " . $course->shortname . " Assessment (" . date('M d, H:i') . ")";
        }

        // 1. Generate Questions via LLM
        $gen_res = self::generate_quiz_questions($course_id, $count, $force_fallback);
        $questions = $gen_res['questions'];

        // 2. Import into Question Bank
        $imported_ids = self::import_questions_to_question_bank($course_id, $questions);

        // 3. Create Quiz and link questions
        $quiz = self::create_quiz_for_course($course_id, $quiz_name, $imported_ids);

        return [
            'status' => 'success',
            'course' => [
                'id' => (int)$course->id,
                'shortname' => $course->shortname,
                'fullname' => $course->fullname,
            ],
            'quiz' => [
                'id' => (int)$quiz->id,
                'cmid' => (int)$quiz->cmid,
                'name' => $quiz->name,
                'slots_added' => (int)$quiz->slots_added,
                'url' => '/mod/quiz/view.php?id=' . $quiz->cmid,
            ],
            'provider' => $gen_res['provider'],
            'model' => $gen_res['model'],
            'fallback_triggered' => $gen_res['fallback_triggered'],
            'primary_failure_reason' => $gen_res['primary_failure_reason'] ?? null,
            'latency_ms' => $gen_res['latency_ms'],
            'questions_count' => count($questions),
            'imported_question_ids' => $imported_ids,
            'questions' => $questions,
        ];
    }

    /**
     * cURL POST request to OpenAI-compatible LLM endpoint.
     */
    private static function call_api(
        string $endpoint,
        string $api_key,
        string $model,
        string $prompt,
        int $timeout,
        array $extra_headers = []
    ): array {
        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $prompt],
        ];

        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => 1500,
        ]);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ];
        foreach ($extra_headers as $k => $v) {
            $headers[] = $k . ': ' . $v;
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curl_error)) {
            return ['success' => false, 'content' => '', 'error' => 'cURL Error: ' . $curl_error];
        }

        if ($http_code !== 200) {
            return ['success' => false, 'content' => '', 'error' => "HTTP $http_code: " . substr($response, 0, 150)];
        }

        $decoded = json_decode($response, true);
        if (isset($decoded['choices'][0]['message']['content'])) {
            return ['success' => true, 'content' => trim($decoded['choices'][0]['message']['content']), 'error' => ''];
        }

        return ['success' => false, 'content' => '', 'error' => 'Malformed JSON response from API'];
    }

    /**
     * Parse and sanitize raw LLM output into validated array of question objects.
     */
    private static function parse_and_validate_json(string $raw_text): array {
        // Strip markdown code fences if model returned ```json ... ```
        $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($raw_text));
        $clean = preg_replace('/\s*```$/', '', $clean);

        $decoded = json_decode($clean, true);
        if (!is_array($decoded)) {
            return [];
        }

        $validated = [];
        $required_keys = ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'explanation'];

        foreach ($decoded as $item) {
            if (!is_array($item)) continue;

            $valid = true;
            foreach ($required_keys as $k) {
                if (!isset($item[$k]) || trim((string)$item[$k]) === '') {
                    $valid = false;
                    break;
                }
            }

            if (!$valid) continue;

            $correct = strtolower(trim($item['correct_answer']));
            if (!in_array($correct, ['option_a', 'option_b', 'option_c', 'option_d'])) {
                $correct = 'option_a'; // fallback
            }

            $validated[] = [
                'question' => trim($item['question']),
                'option_a' => trim($item['option_a']),
                'option_b' => trim($item['option_b']),
                'option_c' => trim($item['option_c']),
                'option_d' => trim($item['option_d']),
                'correct_answer' => $correct,
                'explanation' => trim($item['explanation']),
            ];
        }

        return $validated;
    }

    /**
     * Deterministic curriculum question bank providing guaranteed zero-downtime offline responses.
     */
    private static function get_deterministic_curriculum_questions(\stdClass $course, int $count): array {
        $name_lower = strtolower($course->fullname . ' ' . $course->shortname);

        if (strpos($name_lower, 'database') !== false || strpos($name_lower, 'db') !== false) {
            $pool = [
                [
                    'question' => 'Which normal form eliminates partial dependency on a composite primary key?',
                    'option_a' => 'Second Normal Form (2NF)',
                    'option_b' => 'First Normal Form (1NF)',
                    'option_c' => 'Third Normal Form (3NF)',
                    'option_d' => 'Boyce-Codd Normal Form (BCNF)',
                    'correct_answer' => 'option_a',
                    'explanation' => '2NF requires that the table is in 1NF and all non-key attributes are fully functionally dependent on the primary key, eliminating partial dependencies.',
                ],
                [
                    'question' => 'In a relational database, what does a transitive dependency violate?',
                    'option_a' => 'Third Normal Form (3NF)',
                    'option_b' => 'First Normal Form (1NF)',
                    'option_c' => 'Second Normal Form (2NF)',
                    'option_d' => 'Domain Integrity Constraint',
                    'correct_answer' => 'option_a',
                    'explanation' => '3NF dictates that no non-prime attribute may transitively depend on any candidate key (A -> B -> C).',
                ],
                [
                    'question' => 'Which SQL JOIN returns all rows from the left table and matched rows from the right table?',
                    'option_a' => 'LEFT OUTER JOIN',
                    'option_b' => 'INNER JOIN',
                    'option_c' => 'RIGHT OUTER JOIN',
                    'option_d' => 'CROSS JOIN',
                    'correct_answer' => 'option_a',
                    'explanation' => 'LEFT JOIN returns all records from the left table, and matching records from the right table, or NULL if there is no match.',
                ],
                [
                    'question' => 'In Moodle database architecture, which table tracks whether a user has finished course completion criteria?',
                    'option_a' => 'mdl_course_completions',
                    'option_b' => 'mdl_course_modules',
                    'option_c' => 'mdl_user_enrolments',
                    'option_d' => 'mdl_grade_grades',
                    'correct_answer' => 'option_a',
                    'explanation' => 'mdl_course_completions stores userid, course, timestarted, and timecompleted for each learner.',
                ],
                [
                    'question' => 'Which MariaDB/MySQL index structure is best suited for range-based queries (e.g. BETWEEN, >, <)?',
                    'option_a' => 'B-Tree Index',
                    'option_b' => 'Hash Index',
                    'option_c' => 'Full-Text Index',
                    'option_d' => 'Spatial R-Tree Index',
                    'correct_answer' => 'option_a',
                    'explanation' => 'B-Tree indexes maintain sorted order across nodes, allowing efficient O(log n) searches and range traversals.',
                ],
            ];
        } else {
            $pool = [
                [
                    'question' => 'In full stack architecture, what is the primary role of an asynchronous AJAX transport layer?',
                    'option_a' => 'Update client UI dynamically without requiring a full browser page reload',
                    'option_b' => 'Compile PHP source code directly into browser WebAssembly',
                    'option_c' => 'Encrypt database files stored on the server hard drive',
                    'option_d' => 'Replace relational SQL tables with client-side localStorage',
                    'correct_answer' => 'option_a',
                    'explanation' => 'AJAX (using modern Fetch or XMLHttpRequest) enables client-side scripts to fetch JSON from backend endpoints asynchronously without full document teardown.',
                ],
                [
                    'question' => 'What is the purpose of Parameterized Queries (PDO Prepared Statements) in backend PHP development?',
                    'option_a' => 'Prevent SQL Injection attacks by separating SQL query structure from data values',
                    'option_b' => 'Increase internet connection bandwidth between user and server',
                    'option_c' => 'Render HTML DOM elements directly into canvas graphics',
                    'option_d' => 'Automatically compile PHP into native C++ machine binaries',
                    'correct_answer' => 'option_a',
                    'explanation' => 'Parameterized queries treat user input strictly as literal values rather than executable SQL syntax, neutralizing injection attacks.',
                ],
                [
                    'question' => 'In Chart.js visualizations, why should dual-axis charts use distinct y-axis identifiers?',
                    'option_a' => 'To prevent metrics with different numerical ranges (e.g. 0-10 logins vs 0-100% grades) from flattening each other',
                    'option_b' => 'Because Chart.js only supports 1 line per canvas element',
                    'option_c' => 'To comply with HTTP/2 concurrent stream protocols',
                    'option_d' => 'Dual-axis charts are forbidden in HTML5 canvas standards',
                    'correct_answer' => 'option_a',
                    'explanation' => 'Assigning separate scales (y and y1) preserves visual proportionality when comparing disparate scales like login counts and grade percentages.',
                ],
                [
                    'question' => 'Which HTTP status code indicates that an external LLM API has rate-limited the incoming requests?',
                    'option_a' => '429 Too Many Requests',
                    'option_b' => '502 Bad Gateway',
                    'option_c' => '404 Not Found',
                    'option_d' => '301 Moved Permanently',
                    'correct_answer' => 'option_a',
                    'explanation' => 'HTTP 429 Too Many Requests is the standard response when a client exceeds rate limits or quota boundaries.',
                ],
                [
                    'question' => 'What architectural benefit does a multi-provider fallback cascade provide in enterprise AI engineering?',
                    'option_a' => 'Guarantees continuous system availability even during third-party cloud outages or rate limits',
                    'option_b' => 'Eliminates the requirement for database foreign keys',
                    'option_c' => 'Reduces client screen resolution requirements',
                    'option_d' => 'Replaces the need for backend unit testing',
                    'correct_answer' => 'option_a',
                    'explanation' => 'A resilient fallback cascade prevents single points of failure by routing requests to secondary providers or offline deterministic safety nets.',
                ],
            ];
        }

        return array_slice($pool, 0, $count);
    }
}
