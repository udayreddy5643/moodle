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
 * Resilient AI Tutor client supporting Groq primary and OpenRouter fallback.
 *
 * @package    block_cynaris_stats
 * @copyright  2026 Uday Reddy (b.udayreddy5643@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_tutor {

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
     * AI TUTOR SYSTEM PROMPT ARCHITECTURE:
     * ------------------------------------
     * 1. Persona: Cynaris Academic AI Tutor — empathetic, rigorous, highly pedagogical.
     * 2. Method: Socratic scaffolding; provides clear conceptual explanations, real code, and viva tips.
     * 3. Domain: Enterprise Full Stack Web Development, Relational Databases (MariaDB/PostgreSQL),
     *    and LMS Architecture (Moodle Frankenstyle, DML, RBAC).
     */
    const SYSTEM_PROMPT = <<<EOT
You are the Cynaris Academic AI Tutor, a dedicated full-stack engineering and LMS architecture mentor for students.
Your mission is to guide learners toward mastery using the following structure:
1. Concept Definition: Explain the core topic in clear, plain language (max 2 sentences).
2. Code or Relational Example: Provide a clean, minimal code snippet, SQL query, or architecture diagram.
3. Real-World Engineering Context: Explain how modern software companies (e.g. Infosys, TCS, funded startups) implement this.
4. Viva Voce Tip: One actionable interview question and high-scoring response tip.
Always maintain a supportive, encouraging, and technically precise pedagogical tone.
EOT;

    /**
     * Query the AI Tutor using Groq as primary and OpenRouter as resilient fallback.
     *
     * @param string $question Student question.
     * @param string|null $context Optional course or page context.
     * @param bool $force_fallback If true, skips Groq to simulate an upstream outage and verify OpenRouter fallback.
     * @return array
     */
    public static function ask(string $question, ?string $context = null, bool $force_fallback = false): array {
        $start_time = microtime(true);
        $groq_error = null;

        // Retrieve API credentials from Moodle configuration or server environment variables.
        $groq_key = get_config('block_cynaris_stats', 'groq_api_key') ?: getenv('GROQ_API_KEY');
        $openrouter_key = get_config('block_cynaris_stats', 'openrouter_api_key') ?: getenv('OPENROUTER_API_KEY');

        // ---------------------------------------------------------------------
        // Step 1: Attempt Primary Provider (Groq)
        // ---------------------------------------------------------------------
        if (!$force_fallback && !empty($groq_key)) {
            $groq_res = self::call_api(
                self::GROQ_ENDPOINT,
                $groq_key,
                self::GROQ_DEFAULT_MODEL,
                $question,
                $context,
                10 // 10s timeout
            );

            if ($groq_res['success']) {
                $duration = round((microtime(true) - $start_time) * 1000);
                return [
                    'status' => 'success',
                    'provider' => 'Groq (Primary)',
                    'model' => self::GROQ_DEFAULT_MODEL,
                    'answer' => $groq_res['content'],
                    'fallback_triggered' => false,
                    'latency_ms' => $duration,
                    'timestamp' => date('c'),
                ];
            } else {
                $groq_error = $groq_res['error'];
            }
        } else {
            $groq_error = $force_fallback ? 'Simulated Groq Outage (Fallback Testing Mode)' : 'Groq API Key not configured';
        }

        // ---------------------------------------------------------------------
        // Step 2: Attempt Fallback Provider (OpenRouter)
        // ---------------------------------------------------------------------
        if (!empty($openrouter_key)) {
            $openrouter_res = self::call_api(
                self::OPENROUTER_ENDPOINT,
                $openrouter_key,
                self::OPENROUTER_DEFAULT_MODEL,
                $question,
                $context,
                12, // 12s timeout
                ['HTTP-Referer' => 'http://localhost:8000', 'X-Title' => 'Cynaris Moodle AI Tutor']
            );

            if ($openrouter_res['success']) {
                $duration = round((microtime(true) - $start_time) * 1000);
                return [
                    'status' => 'success',
                    'provider' => 'OpenRouter (Fallback)',
                    'model' => self::OPENROUTER_DEFAULT_MODEL,
                    'answer' => $openrouter_res['content'],
                    'fallback_triggered' => true,
                    'primary_failure_reason' => $groq_error,
                    'latency_ms' => $duration,
                    'timestamp' => date('c'),
                ];
            }
        }

        // ---------------------------------------------------------------------
        // Step 3: Local Offline Deterministic Knowledge Base (Resilient Safety Net)
        // ---------------------------------------------------------------------
        $duration = round((microtime(true) - $start_time) * 1000);
        $offline_answer = self::get_offline_pedagogical_response($question);

        return [
            'status' => 'success',
            'provider' => 'Cynaris Academic Engine (Local Deterministic Fallback)',
            'model' => 'cynaris-curriculum-kb-v1',
            'answer' => $offline_answer,
            'fallback_triggered' => true,
            'primary_failure_reason' => $groq_error,
            'secondary_failure_reason' => empty($openrouter_key) ? 'OpenRouter API Key not configured' : 'OpenRouter endpoint unreachable',
            'latency_ms' => $duration,
            'timestamp' => date('c'),
        ];
    }

    /**
     * Perform an HTTP POST cURL request to an OpenAI-compatible Chat Completions API.
     *
     * @param string $endpoint API URL.
     * @param string $api_key Bearer authorization token.
     * @param string $model Model identifier.
     * @param string $question Student question.
     * @param string|null $context Contextual information.
     * @param int $timeout Timeout in seconds.
     * @param array $extra_headers Additional headers.
     * @return array ['success' => bool, 'content' => string, 'error' => string]
     */
    private static function call_api(
        string $endpoint,
        string $api_key,
        string $model,
        string $question,
        ?string $context,
        int $timeout,
        array $extra_headers = []
    ): array {
        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
        ];

        if (!empty($context)) {
            $messages[] = ['role' => 'system', 'content' => 'Context: ' . $context];
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.4,
            'max_tokens' => 800,
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development docker containers

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
     * Deterministic pedagogical knowledge base serving as a bulletproof zero-crash fallback.
     *
     * @param string $q User query.
     * @return string
     */
    private static function get_offline_pedagogical_response(string $q): string {
        $q_lower = strtolower($q);

        if (strpos($q_lower, 'completion') !== false || strpos($q_lower, 'moodle') !== false || strpos($q_lower, 'table') !== false) {
            return "### 📘 Concept Definition\nIn Moodle LMS, Course Completion tracks whether an enrolled student has met predefined instructional milestones (passing grades, quiz attempts, activity view limits, or manual supervisor confirmation).\n\n### 💻 Architecture & Code Example\nMoodle persists this in `mdl_course_completions`, linking `userid` and `course` with integer epoch timestamps:\n\n```sql\nSELECT c.fullname, u.username, \n       CASE WHEN cc.timecompleted > 0 THEN 'Completed' ELSE 'In Progress' END AS status\nFROM mdl_course c\nJOIN mdl_course_completions cc ON cc.course = c.id\nJOIN mdl_user u ON u.id = cc.userid;\n```\n\n### 🏢 Real-World Engineering Context\nCorporate LMS platforms use completion triggers to automate certificate generation and synchronize compliance records into enterprise HRIS (Workday/SAP SuccessFactors).\n\n### 💬 Viva Voce Tip\n*Question*: \"Which Moodle database table tracks student course progress?\"\n*Answer*: \"`mdl_course_completions`, which contains `timestarted` and `timecompleted` foreign-keyed to `mdl_user.id` and `mdl_course.id`.\"";
        }

        if (strpos($q_lower, 'normaliz') !== false || strpos($q_lower, '1nf') !== false || strpos($q_lower, '2nf') !== false || strpos($q_lower, '3nf') !== false || strpos($q_lower, 'database') !== false) {
            return "### 📘 Concept Definition\nDatabase normalization is the systematic technique of organizing relational table schemas to eliminate data redundancy and prevent insert, update, and delete anomalies.\n\n### 💻 Relational Example (1NF, 2NF, 3NF)\n- **1NF (First Normal Form)**: Atomic values per cell, unique primary key, no repeating attribute groups.\n- **2NF (Second Normal Form)**: Must be 1NF + every non-key column must depend on the *entire* composite primary key (eliminates partial dependency).\n- **3NF (Third Normal Form)**: Must be 2NF + non-key columns must depend *only* on candidate keys (eliminates transitive dependency: \$A -> \$B -> \$C).\n\n```sql\n-- 3NF Table Design\nCREATE TABLE mdl_course (\n    id BIGINT PRIMARY KEY AUTO_INCREMENT,\n    categoryid BIGINT NOT NULL,\n    fullname VARCHAR(255) NOT NULL,\n    FOREIGN KEY (categoryid) REFERENCES mdl_course_categories(id)\n);\n```\n\n### 🏢 Real-World Engineering Context\nHigh-throughput SaaS platforms (like TCS/Infosys LMS deployments) normalize transactional tables (OLTP) to 3NF for ACID integrity, while selectively denormalizing read-heavy analytics tables (OLAP).\n\n### 💬 Viva Voce Tip\n*Question*: \"What is a transitive dependency?\"\n*Answer*: \"It occurs when a non-key attribute depends on another non-key attribute rather than directly on the primary key, violating 3NF.\"";
        }

        return "### 📘 Concept Definition\nFull Stack Web Engineering combines frontend presentation interfaces, backend transactional logic, and relational persistence engines into a scalable architecture.\n\n### 💻 Architectural Mechanics\n```\n[Client Browser / Chart.js] \n      ▲ \n      │ (Asynchronous JSON over HTTPS)\n      ▼\n[PHP Controller / Moodle Block Engine] \n      ▲ \n      │ (DML Parameterized PDO / SQL)\n      ▼\n[MariaDB Relational Tables]\n```\n\n### 🏢 Real-World Engineering Context\nProduction systems decouple compute from persistence, deploying multi-tier containerized clusters behind reverse proxies (Nginx/Cloudflare) with Redis caching layers.\n\n### 💬 Viva Voce Tip\n*Question*: \"Why is separation of concerns critical in full stack design?\"\n*Answer*: \"It ensures presentation changes can evolve independently from business logic and database persistence schemas without risking cross-cutting regression bugs.\"";
    }
}
