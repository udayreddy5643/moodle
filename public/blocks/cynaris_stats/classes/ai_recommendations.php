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

/**
 * AI Learning Recommendations Engine: Derives actionable interventions from Grade & Completion data.
 * Multi-provider architecture with Groq Cloud primary, OpenRouter fallback, and deterministic curriculum safety net.
 *
 * @package    block_cynaris_stats
 * @copyright  2026 Uday Reddy (b.udayreddy5643@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_recommendations {

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
     * SYSTEM PROMPT ARCHITECTURE FOR LEARNING RECOMMENDATIONS:
     * --------------------------------------------------------
     * 1. Role: Cynaris Senior Academic Advisor & Full Stack Engineering Career Coach.
     * 2. Objective: Analyze the student's actual LMS engagement metrics (courses enrolled,
     *    completion percentages, final grade averages, login activity) and output personalized,
     *    pedagogically rigorous, and actionable recommendations.
     * 3. Schema:
     *    {
     *      "performance_summary": "1-2 sentence diagnostic overview of the learner's current standing",
     *      "strengths": ["Demonstrated competency 1", "Competency 2"],
     *      "growth_areas": ["Identified skill gap 1", "Gap 2"],
     *      "action_plan": ["Immediate concrete action step 1", "Step 2", "Step 3"],
     *      "recommended_topics": ["Suggested technical focus area 1", "Topic 2"],
     *      "viva_prep_tip": "One high-yield technical interview question and preparation advice"
     *    }
     * 4. Rule: Return ONLY raw valid JSON — no conversational commentary, no markdown wrappers.
     */
    const SYSTEM_PROMPT = <<<EOT
You are the Cynaris Academic Advisor and Full Stack Technical Career Mentor.
Analyze the provided student LMS metrics (enrolled courses, completion rates, grade scores, login activity).
Generate personalized, actionable learning recommendations strictly adhering to this JSON schema:
{
  "performance_summary": "Diagnostic assessment (max 2 sentences)",
  "strengths": ["Bullet 1", "Bullet 2"],
  "growth_areas": ["Gap 1", "Gap 2"],
  "action_plan": ["Action 1", "Action 2", "Action 3"],
  "recommended_topics": ["Topic 1", "Topic 2"],
  "viva_prep_tip": "Target interview question and response guidance"
}
Return STRICT raw JSON only without markdown code fences or backticks.
EOT;

    /**
     * Aggregate learning profile for a student by querying Moodle's 5 core tables.
     *
     * @param int $user_id Student user ID.
     * @return array Aggregated performance profile.
     */
    public static function get_student_learning_profile(int $user_id): array {
        global $DB;

        $user = $DB->get_record('user', ['id' => $user_id, 'deleted' => 0], '*', MUST_EXIST);

        // 1. Enrolled courses and completion statuses ({course} & {course_completions})
        $sql_courses = "
            SELECT 
                c.id,
                c.shortname,
                c.fullname,
                COALESCE(cc.timecompleted, 0) AS timecompleted,
                COALESCE(cc.timestarted, 0) AS timestarted
            FROM {course} c
            JOIN {course_completions} cc ON cc.course = c.id
            WHERE cc.userid = :userid AND c.id > 1
            ORDER BY c.id ASC
        ";
        $course_records = $DB->get_records_sql($sql_courses, ['userid' => $user_id]);

        $enrolled_courses = [];
        $completed_count = 0;
        foreach ($course_records as $rec) {
            $is_completed = ((int)$rec->timecompleted > 0);
            if ($is_completed) {
                $completed_count++;
            }
            $enrolled_courses[] = [
                'course_id' => (int)$rec->id,
                'code' => $rec->shortname,
                'name' => $rec->fullname,
                'status' => $is_completed ? 'Completed' : 'In Progress',
                'completed' => $is_completed,
            ];
        }

        $total_enrolled = count($enrolled_courses);
        $completion_rate = ($total_enrolled > 0) ? round(($completed_count / $total_enrolled) * 100, 1) : 0.0;

        // 2. Grade performance ({grade_grades} & {grade_items})
        $sql_grades = "
            SELECT 
                gi.id AS itemid,
                gi.courseid,
                c.shortname AS course_code,
                gi.itemname,
                gg.finalgrade,
                gg.rawgrademax
            FROM {grade_items} gi
            JOIN {course} c ON c.id = gi.courseid
            LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
            WHERE gi.itemtype = 'course' AND gi.courseid > 1 AND gg.finalgrade IS NOT NULL
            ORDER BY gi.courseid ASC
        ";
        $grade_records = $DB->get_records_sql($sql_grades, ['userid' => $user_id]);

        $grades = [];
        $grade_sum = 0;
        foreach ($grade_records as $g) {
            $val = (float)$g->finalgrade;
            $grades[] = [
                'course_id' => (int)$g->courseid,
                'course_code' => $g->course_code,
                'final_grade' => round($val, 2),
            ];
            $grade_sum += $val;
        }

        $avg_grade = count($grades) > 0 ? round($grade_sum / count($grades), 1) : null;

        // 3. Login engagement activity ({log} & {user})
        $seven_days_ago = time() - (7 * 86400);
        $sql_logins = "
            SELECT COUNT(id) AS login_count
            FROM {log}
            WHERE userid = :userid AND action = 'login' AND time >= :window
        ";
        $login_count = (int)$DB->get_field_sql($sql_logins, ['userid' => $user_id, 'window' => $seven_days_ago]);

        // Classify persona tier
        if ($avg_grade === null) {
            $persona_tier = 'New Learner / Assessment Pending';
        } else if ($avg_grade >= 85.0) {
            $persona_tier = 'Top Performer (Advanced Track)';
        } else if ($avg_grade >= 70.0) {
            $persona_tier = 'Steady Achiever (Core Reinforcement)';
        } else {
            $persona_tier = 'At-Risk / Needs Remedial Support';
        }

        return [
            'user' => [
                'id' => (int)$user->id,
                'username' => $user->username,
                'fullname' => trim($user->firstname . ' ' . $user->lastname),
                'last_access' => (int)$user->lastaccess > 0 ? date('Y-m-d H:i', (int)$user->lastaccess) : 'Never',
            ],
            'metrics' => [
                'total_enrolled' => $total_enrolled,
                'completed_courses' => $completed_count,
                'completion_rate' => $completion_rate,
                'average_grade' => $avg_grade,
                'has_grade' => ($avg_grade !== null),
                'logins_7d' => $login_count,
                'persona_tier' => $persona_tier,
            ],
            'enrolled_courses' => $enrolled_courses,
            'grades' => $grades,
        ];
    }

    /**
     * Generate personalized AI recommendations using Groq primary with OpenRouter fallback.
     *
     * @param int $user_id Student ID.
     * @param bool $force_fallback Outage simulation toggle.
     * @return array Complete recommendation payload with timing and provider info.
     */
    public static function generate_recommendations(int $user_id, bool $force_fallback = false): array {
        $start_time = microtime(true);
        $groq_error = null;

        $profile = self::get_student_learning_profile($user_id);

        // Build prompt summarizing the student's metrics
        $prompt = self::build_prompt_from_profile($profile);

        // Retrieve API credentials
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
                $parsed = self::parse_and_validate_recommendations($groq_res['content']);
                if (!empty($parsed)) {
                    $duration = round((microtime(true) - $start_time) * 1000);
                    return [
                        'status' => 'success',
                        'provider' => 'Groq Cloud (Primary)',
                        'model' => self::GROQ_DEFAULT_MODEL,
                        'profile' => $profile,
                        'recommendations' => $parsed,
                        'fallback_triggered' => false,
                        'latency_ms' => $duration,
                        'timestamp' => date('c'),
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
                ['HTTP-Referer' => 'http://localhost:8000', 'X-Title' => 'Cynaris Learning Recommendations']
            );

            if ($openrouter_res['success']) {
                $parsed = self::parse_and_validate_recommendations($openrouter_res['content']);
                if (!empty($parsed)) {
                    $duration = round((microtime(true) - $start_time) * 1000);
                    return [
                        'status' => 'success',
                        'provider' => 'OpenRouter AI (Fallback)',
                        'model' => self::OPENROUTER_DEFAULT_MODEL,
                        'profile' => $profile,
                        'recommendations' => $parsed,
                        'fallback_triggered' => true,
                        'primary_failure_reason' => $groq_error,
                        'latency_ms' => $duration,
                        'timestamp' => date('c'),
                    ];
                }
            }
        }

        // ---------------------------------------------------------------------
        // Step 3: Local Offline Deterministic Knowledge Base (Safety Net)
        // ---------------------------------------------------------------------
        $duration = round((microtime(true) - $start_time) * 1000);
        $recs = self::get_deterministic_recommendations($profile);

        return [
            'status' => 'success',
            'provider' => 'Cynaris Academic Advisory Engine (Deterministic Fallback)',
            'model' => 'cynaris-recommendations-kb-v1',
            'profile' => $profile,
            'recommendations' => $recs,
            'fallback_triggered' => true,
            'primary_failure_reason' => $groq_error,
            'secondary_failure_reason' => empty($openrouter_key) ? 'OpenRouter API Key not configured' : 'OpenRouter endpoint unreachable',
            'latency_ms' => $duration,
            'timestamp' => date('c'),
        ];
    }

    /**
     * Build contextual prompt summarizing the student's metrics.
     */
    private static function build_prompt_from_profile(array $p): string {
        $u = $p['user'];
        $m = $p['metrics'];

        $grade_str = ($m['average_grade'] !== null) ? ($m['average_grade'] . '%') : 'No graded assessments yet';

        $prompt = "Student: {$u['fullname']} (Username: {$u['username']})\n"
                . "Persona Classification: {$m['persona_tier']}\n"
                . "Performance Metrics:\n"
                . "- Average Course Grade: {$grade_str}\n"
                . "- Courses Completed: {$m['completed_courses']} of {$m['total_enrolled']} ({$m['completion_rate']}% completion rate)\n"
                . "- 7-Day Login Activity: {$m['logins_7d']} login sessions (Last Active: {$u['last_access']})\n\n"
                . "Course Progress Breakdown:\n";

        foreach ($p['enrolled_courses'] as $c) {
            $prompt .= "- {$c['code']}: {$c['name']} [{$c['status']}]\n";
        }

        $prompt .= "\nGenerate personalized pedagogical learning recommendations in strict JSON format.";
        return $prompt;
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
            'temperature' => 0.4,
            'max_tokens' => 1000,
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
     * Parse and validate JSON recommendations dictionary.
     */
    private static function parse_and_validate_recommendations(string $raw_text): ?array {
        $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($raw_text));
        $clean = preg_replace('/\s*```$/', '', $clean);

        $decoded = json_decode($clean, true);
        if (!is_array($decoded) || empty($decoded['performance_summary'])) {
            return null;
        }

        return [
            'performance_summary' => trim($decoded['performance_summary']),
            'strengths' => (array)($decoded['strengths'] ?? []),
            'growth_areas' => (array)($decoded['growth_areas'] ?? []),
            'action_plan' => (array)($decoded['action_plan'] ?? []),
            'recommended_topics' => (array)($decoded['recommended_topics'] ?? []),
            'viva_prep_tip' => trim($decoded['viva_prep_tip'] ?? ''),
        ];
    }

    /**
     * Deterministic recommendations engine tailored to the student's metrics.
     */
    private static function get_deterministic_recommendations(array $p): array {
        $u = $p['user'];
        $m = $p['metrics'];
        $avg = $m['average_grade'];

        if ($avg !== null && $avg >= 85.0) {
            // Persona: Top Performer (Rahul Sharma / Priya Patel)
            return [
                'performance_summary' => "{$u['fullname']} is demonstrating exceptional academic mastery with an average score of {$avg}% and a strong {$m['completion_rate']}% course completion rate.",
                'strengths' => [
                    "High analytical competence across full-stack and database modules (scoring {$avg}%)",
                    "Consistent LMS activity ({$m['logins_7d']} weekly login sessions demonstrating disciplined study habits)",
                    "Successfully completed core instructional milestones in prerequisite curriculum",
                ],
                'growth_areas' => [
                    "Transition from module-level implementation to distributed enterprise system architecture",
                    "Optimize database schemas for high-concurrency read replicas and caching layers",
                ],
                'action_plan' => [
                    "Complete remaining pending modules in DB201 to reach 100% curriculum completion",
                    "Attempt advanced multi-table SQL query optimizations and indexing benchmarks in MariaDB",
                    "Mentor peer learners during upcoming code review sessions",
                ],
                'recommended_topics' => [
                    "Redis & Memcached distributed caching architectures",
                    "Database query execution plans (`EXPLAIN ANALYZE`) & composite indexing",
                    "Microservices communication via message brokers (RabbitMQ / Kafka)",
                ],
                'viva_prep_tip' => "Prepare to explain ACID transaction isolation levels: 'Explain how REPEATABLE READ prevents non-repeatable reads but how Phantom Reads can occur.'",
            ];
        }

        if ($avg !== null && $avg >= 70.0) {
            // Persona: Steady Achiever (Amit Verma)
            return [
                'performance_summary' => "{$u['fullname']} shows solid foundational understanding with a {$avg}% average grade, but requires focused effort to convert in-progress coursework into completed milestones.",
                'strengths' => [
                    "Passing grades in core Full Stack development assignments ({$avg}%)",
                    "Demonstrated ability to implement client-server communication using AJAX",
                ],
                'growth_areas' => [
                    "Course completion velocity is at {$m['completion_rate']}%; several prerequisite modules remain incomplete",
                    "Low login frequency ({$m['logins_7d']} logins in the last 7 days) risks loss of study momentum",
                ],
                'action_plan' => [
                    "Dedicate at least 45 minutes daily to clear remaining activity milestones in FS101",
                    "Attempt the AI-generated practice quiz on Full Stack Essentials to reinforce weak topics",
                    "Review database normalization (1NF, 2NF, 3NF) before moving into the DB201 module",
                ],
                'recommended_topics' => [
                    "Relational schema normalization and anomaly prevention",
                    "Parameterized PDO prepared statements in PHP",
                    "Chart.js event-driven dynamic UI rendering",
                ],
                'viva_prep_tip' => "Be ready for: 'What is the difference between synchronous and asynchronous requests in web development, and why is AJAX preferred for dynamic dashboards?'",
            ];
        }

        // Persona: New Learner / Assessment Pending (Neha Gupta)
        return [
            'performance_summary' => "{$u['fullname']} is at the initial onboarding stage with pending grade records. Structured guidance is recommended to build early momentum.",
            'strengths' => [
                "Successfully enrolled in the foundational Full Stack curriculum (FS101)",
                "Active account initialized and ready for assessment submissions",
            ],
            'growth_areas' => [
                "No graded quiz submissions or assignments recorded in the gradebook yet",
                "Needs to establish a consistent weekly login and study routine",
            ],
            'action_plan' => [
                "Complete the introductory course orientation and syllabus overview in FS101",
                "Take the first diagnostic AI Quiz to establish a baseline grade in the gradebook",
                "Explore the Cynaris Stats analytics dashboard to track personal progression milestones",
            ],
            'recommended_topics' => [
                "HTML5 semantic layout structure and CSS Flexbox",
                "PHP backend syntax fundamentals and script lifecycle",
                "Moodle course completion criteria and activity tracking",
            ],
            'viva_prep_tip' => "Practice the foundational question: 'Explain how the client browser, PHP webserver, and MariaDB database interact in a three-tier web application.'",
        ];
    }
}
