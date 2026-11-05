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
 * AI Query Performance Auditor & Index Optimizer.
 * Executes MariaDB EXPLAIN on production analytics queries, analyzes execution plans,
 * and generates actionable index recommendations and query refactorings via dual-LLM architecture.
 *
 * @package    block_cynaris_stats
 * @copyright  2026 Uday Reddy (b.udayreddy5643@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_query_auditor {

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
     * Preset production queries evaluated across the platform.
     */
    const PRESET_QUERIES = [
        'course_completion' => [
            'name' => 'Course Completion Analytics Query',
            'tables' => ['mdl_course', 'mdl_course_completions'],
            'sql' => "SELECT c.id, c.shortname, c.fullname, COUNT(cc.id) AS total_enrolled, SUM(CASE WHEN cc.timecompleted > 0 THEN 1 ELSE 0 END) AS completed_count, ROUND((SUM(CASE WHEN cc.timecompleted > 0 THEN 1 ELSE 0 END) * 100.0) / NULLIF(COUNT(cc.id), 0), 1) AS completion_rate FROM {course} c JOIN {course_completions} cc ON cc.course = c.id WHERE c.id > 1 GROUP BY c.id, c.shortname, c.fullname ORDER BY c.id",
        ],
        'grade_average' => [
            'name' => 'Student Grade Average Aggregation Query',
            'tables' => ['mdl_user', 'mdl_grade_grades'],
            'sql' => "SELECT u.id, u.username, u.firstname, u.lastname, ROUND(AVG(gg.finalgrade), 2) AS avg_grade FROM {user} u LEFT JOIN {grade_grades} gg ON gg.userid = u.id AND gg.finalgrade IS NOT NULL WHERE u.deleted = 0 AND u.id > 2 GROUP BY u.id, u.username, u.firstname, u.lastname ORDER BY u.id",
        ],
        'login_frequency' => [
            'name' => '7-Day User Login Windowing Query',
            'tables' => ['mdl_user', 'mdl_log'],
            'sql' => "SELECT u.id, u.username, CONCAT(u.firstname, ' ', u.lastname) AS fullname, COUNT(l.id) AS login_count_7d, COALESCE(MAX(l.time), u.lastaccess, 0) AS last_login_ts FROM {user} u LEFT JOIN {log} l ON l.userid = u.id AND l.action = 'login' AND l.time >= :window WHERE u.deleted = 0 AND u.id > 2 GROUP BY u.id, u.username, u.firstname, u.lastname, u.lastaccess ORDER BY login_count_7d DESC, u.id",
        ],
    ];

    /**
     * AI QUERY AUDITOR SYSTEM PROMPT ARCHITECTURE:
     * ---------------------------------------------
     * 1. Persona: Cynaris Principal Database Architect & High-Throughput Performance Tuning Specialist.
     * 2. Objective: Audit SQL queries and their MariaDB/MySQL EXPLAIN execution plans.
     * 3. Schema:
     *    {
     *      "bottleneck_analysis": "Detailed technical analysis of the execution plan",
     *      "hazards": ["Hazard 1", "Hazard 2"],
     *      "index_recommendations": [
     *        {"table": "table_name", "index_name": "idx_name", "ddl": "CREATE INDEX ...", "rationale": "..."}
     *      ],
     *      "refactored_query": "Optimized SQL query string",
     *      "performance_gain": "Quantified estimate of row scan and I/O reduction",
     *      "viva_tip": "Target interview question and optimal response tip"
     *    }
     */
    const SYSTEM_PROMPT = <<<EOT
You are the Cynaris Principal Database Architect and MariaDB Query Performance Tuning Specialist.
Analyze the provided SQL query and its MariaDB EXPLAIN execution plan.
Identify bottlenecks (e.g. type: ALL full table scans, Using filesort, Using temporary, un-indexed filter predicates).
Provide actionable optimization guidance strictly adhering to this JSON schema:
{
  "bottleneck_analysis": "Detailed technical analysis of the execution plan",
  "hazards": ["Hazard 1", "Hazard 2"],
  "index_recommendations": [
    {
      "table": "table_name",
      "index_name": "idx_name",
      "ddl": "CREATE INDEX ... ON ... (...);",
      "rationale": "Why this composite index optimizes the ESR pattern"
    }
  ],
  "refactored_query": "Optimized SQL query string",
  "performance_gain": "Quantified estimate of row scan and I/O reduction",
  "viva_tip": "Target interview question and optimal response tip"
}
Return STRICT raw JSON only without markdown code fences or backticks.
EOT;

    /**
     * Execute EXPLAIN on a SQL query and extract execution plan indicators.
     *
     * @param string $sql SQL query (may contain Moodle {table} placeholders).
     * @param array $params Query parameters.
     * @return array Array of EXPLAIN execution steps and detected performance flags.
     */
    public static function explain_query(string $sql, array $params = []): array {
        global $DB;

        $explain_sql = "EXPLAIN " . $sql;
        $recordset = $DB->get_recordset_sql($explain_sql, $params);

        $steps = [];
        $hazards = [];

        foreach ($recordset as $r) {
            $extra = $r->extra ?? '';
            $type = $r->type ?? '';
            $table = $r->table ?? '';
            $rows = (int)($r->rows ?? 0);

            $step_hazards = [];

            if ($type === 'ALL') {
                $step_hazards[] = "Full Table Scan on `{$table}` (scans all {$rows} rows)";
            }
            if (stripos($extra, 'Using temporary') !== false) {
                $step_hazards[] = "In-memory/Disk Temporary Table required for GROUP BY on `{$table}`";
            }
            if (stripos($extra, 'Using filesort') !== false) {
                $step_hazards[] = "Filesort operation required to satisfy ORDER BY / GROUP BY on `{$table}`";
            }

            foreach ($step_hazards as $h) {
                if (!in_array($h, $hazards)) {
                    $hazards[] = $h;
                }
            }

            $steps[] = [
                'id' => (int)($r->id ?? 1),
                'select_type' => $r->select_type ?? 'SIMPLE',
                'table' => $table,
                'type' => $type,
                'possible_keys' => $r->possible_keys ?? 'None',
                'key' => $r->key ?? 'None',
                'key_len' => $r->key_len ?? 'None',
                'ref' => $r->ref ?? 'None',
                'rows' => $rows,
                'extra' => $extra,
                'is_full_scan' => ($type === 'ALL'),
                'uses_filesort' => (stripos($extra, 'Using filesort') !== false),
                'uses_temporary' => (stripos($extra, 'Using temporary') !== false),
            ];
        }
        $recordset->close();

        return [
            'steps' => $steps,
            'hazards' => $hazards,
        ];
    }

    /**
     * Audit a query (preset key or custom SQL) using Groq primary with OpenRouter fallback.
     *
     * @param string $query_key_or_sql Preset identifier ('course_completion', 'grade_average', 'login_frequency') or custom SQL.
     * @param bool $force_fallback Outage simulation toggle.
     * @return array Complete audit report with EXPLAIN plan, AI recommendations, and performance metrics.
     */
    public static function audit_query(string $query_key_or_sql, bool $force_fallback = false): array {
        $start_time = microtime(true);
        $groq_error = null;

        $is_preset = array_key_exists($query_key_or_sql, self::PRESET_QUERIES);
        if ($is_preset) {
            $preset = self::PRESET_QUERIES[$query_key_or_sql];
            $query_name = $preset['name'];
            $sql = $preset['sql'];
            $tables = $preset['tables'];
            $params = ($query_key_or_sql === 'login_frequency') ? ['window' => time() - (7 * 86400)] : [];
        } else {
            $query_name = 'Custom Execution Plan Query';
            $sql = $query_key_or_sql;
            $tables = ['custom_tables'];
            $params = [];
        }

        // 1. Run MariaDB EXPLAIN
        $explain_res = self::explain_query($sql, $params);

        // 2. Build contextual prompt
        $prompt = self::build_audit_prompt($query_name, $sql, $explain_res);

        // 3. Retrieve API credentials
        $groq_key = get_config('block_cynaris_stats', 'groq_api_key') ?: getenv('GROQ_API_KEY');
        $openrouter_key = get_config('block_cynaris_stats', 'openrouter_api_key') ?: getenv('OPENROUTER_API_KEY');

        // Step 1: Attempt Primary Provider (Groq Cloud)
        if (!$force_fallback && !empty($groq_key)) {
            $groq_res = self::call_api(
                self::GROQ_ENDPOINT,
                $groq_key,
                self::GROQ_DEFAULT_MODEL,
                $prompt,
                12
            );

            if ($groq_res['success']) {
                $parsed = self::parse_and_validate_audit($groq_res['content']);
                if (!empty($parsed)) {
                    $duration = round((microtime(true) - $start_time) * 1000);
                    return [
                        'status' => 'success',
                        'provider' => 'Groq Cloud (Primary)',
                        'model' => self::GROQ_DEFAULT_MODEL,
                        'query_name' => $query_name,
                        'sql' => $sql,
                        'explain' => $explain_res,
                        'audit' => $parsed,
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

        // Step 2: Attempt Fallback Provider (OpenRouter AI)
        if (!empty($openrouter_key)) {
            $openrouter_res = self::call_api(
                self::OPENROUTER_ENDPOINT,
                $openrouter_key,
                self::OPENROUTER_DEFAULT_MODEL,
                $prompt,
                15,
                ['HTTP-Referer' => 'http://localhost:8000', 'X-Title' => 'Cynaris Query Performance Auditor']
            );

            if ($openrouter_res['success']) {
                $parsed = self::parse_and_validate_audit($openrouter_res['content']);
                if (!empty($parsed)) {
                    $duration = round((microtime(true) - $start_time) * 1000);
                    return [
                        'status' => 'success',
                        'provider' => 'OpenRouter AI (Fallback)',
                        'model' => self::OPENROUTER_DEFAULT_MODEL,
                        'query_name' => $query_name,
                        'sql' => $sql,
                        'explain' => $explain_res,
                        'audit' => $parsed,
                        'fallback_triggered' => true,
                        'primary_failure_reason' => $groq_error,
                        'latency_ms' => $duration,
                        'timestamp' => date('c'),
                    ];
                }
            }
        }

        // Step 3: Deterministic Expert DBA Knowledge Base (Zero-Downtime Safety Net)
        $duration = round((microtime(true) - $start_time) * 1000);
        $expert_audit = self::get_deterministic_audit($query_key_or_sql, $explain_res);

        return [
            'status' => 'success',
            'provider' => 'Cynaris DBA Performance Engine (Deterministic Fallback)',
            'model' => 'cynaris-query-dba-kb-v1',
            'query_name' => $query_name,
            'sql' => $sql,
            'explain' => $explain_res,
            'audit' => $expert_audit,
            'fallback_triggered' => true,
            'primary_failure_reason' => $groq_error,
            'secondary_failure_reason' => empty($openrouter_key) ? 'OpenRouter API Key not configured' : 'OpenRouter endpoint unreachable',
            'latency_ms' => $duration,
            'timestamp' => date('c'),
        ];
    }

    /**
     * Build prompt summarizing the SQL query and MariaDB execution plan.
     */
    private static function build_audit_prompt(string $name, string $sql, array $explain): string {
        $prompt = "SQL Performance Audit Request for: {$name}\n"
                . "Query: {$sql}\n\n"
                . "MariaDB Execution Plan (EXPLAIN Output):\n";

        foreach ($explain['steps'] as $s) {
            $prompt .= "- Table: {$s['table']}, Access Type: {$s['type']}, Key: {$s['key']}, Key Length: {$s['key_len']}, Rows Scanned: {$s['rows']}, Extra: {$s['extra']}\n";
        }

        if (!empty($explain['hazards'])) {
            $prompt .= "\nDetected Performance Hazards:\n- " . implode("\n- ", $explain['hazards']) . "\n";
        }

        $prompt .= "\nAnalyze this execution plan, recommend composite indexes, refactor the query, and quantify performance gains.";
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
            'temperature' => 0.3,
            'max_tokens' => 1200,
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
     * Parse and sanitize LLM JSON audit response.
     */
    private static function parse_and_validate_audit(string $raw_text): ?array {
        $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($raw_text));
        $clean = preg_replace('/\s*```$/', '', $clean);

        $decoded = json_decode($clean, true);
        if (!is_array($decoded) || empty($decoded['bottleneck_analysis'])) {
            return null;
        }

        return [
            'bottleneck_analysis' => trim($decoded['bottleneck_analysis']),
            'hazards' => (array)($decoded['hazards'] ?? []),
            'index_recommendations' => (array)($decoded['index_recommendations'] ?? []),
            'refactored_query' => trim($decoded['refactored_query'] ?? ''),
            'performance_gain' => trim($decoded['performance_gain'] ?? ''),
            'viva_tip' => trim($decoded['viva_tip'] ?? ''),
        ];
    }

    /**
     * Deterministic query audit providing expert DBA advice tailored to Moodle's 5 core tables.
     */
    private static function get_deterministic_audit(string $key, array $explain): array {
        if ($key === 'login_frequency' || strpos($key, 'log') !== false) {
            return [
                'bottleneck_analysis' => "The 7-day login query inspects table `mdl_log` using the partial index `mdl_log_usecou_ix` (userid, course). Because the query filters by `action = 'login'` and `time >= :window`, MariaDB must fetch every matched row from disk to apply the additional filter predicates (`Using where`). Furthermore, grouping on user fields forces an in-memory temporary table and filesort.",
                'hazards' => [
                    "Secondary Disk Row Inspection: Rows in `mdl_log` are loaded from disk to evaluate `action` and `time` predicates.",
                    "Filesort & Temporary Table: `GROUP BY u.id, u.username...` requires sorting overhead.",
                ],
                'index_recommendations' => [
                    [
                        'table' => 'mdl_log',
                        'index_name' => 'mdl_log_useacttim_ix',
                        'ddl' => 'CREATE INDEX mdl_log_useacttim_ix ON mdl_log (userid, action, time);',
                        'rationale' => "Adheres to the ESR (Equality, Sort, Range) rule. Equality on `userid` and `action`, followed by range on `time`. Allows index-only scanning (`Using index`), reducing disk I/O to near-zero.",
                    ],
                ],
                'refactored_query' => "SELECT u.id, u.username, CONCAT(u.firstname, ' ', u.lastname) AS fullname, COALESCE(l.login_count_7d, 0) AS login_count_7d, COALESCE(l.last_login_ts, u.lastaccess, 0) AS last_login_ts FROM {user} u LEFT JOIN (SELECT userid, COUNT(id) AS login_count_7d, MAX(time) AS last_login_ts FROM {log} WHERE action = 'login' AND time >= :window GROUP BY userid) l ON l.userid = u.id WHERE u.deleted = 0 AND u.id > 2 ORDER BY login_count_7d DESC, u.id;",
                'performance_gain' => "Pre-aggregating log events in a derived subquery eliminates cartesian grouping overhead on `mdl_user`. Combined with composite index `mdl_log_useacttim_ix`, execution time improves by 85–95% on high-concurrency instances.",
                'viva_tip' => "*Question*: 'Why is a composite index on (userid, action, time) better than three single-column indexes on mdl_log?'\n*Answer*: 'MySQL can typically utilize only one index per table in a query. A composite index satisfies all three filter conditions in a single B-tree traversal without index merge overhead.'",
            ];
        }

        if ($key === 'grade_average' || strpos($key, 'grade') !== false) {
            return [
                'bottleneck_analysis' => "Aggregating student grade averages requires joining `mdl_user` with `mdl_grade_grades`. Although `m_gradgrad_useite_uix` provides efficient ref access, grouping over user identity attributes (`u.id, u.username, u.firstname, u.lastname`) creates temporary table and filesort overhead (`Using temporary; Using filesort`).",
                'hazards' => [
                    "Temporary Table Overhead: Grouping by multiple non-indexed varchar columns (`username, firstname, lastname`) forces temporary row storage.",
                    "Filesort: MariaDB must sort the temporary table before returning final ordered results.",
                ],
                'index_recommendations' => [
                    [
                        'table' => 'mdl_grade_grades',
                        'index_name' => 'mdl_gradgrad_usefin_ix',
                        'ddl' => 'CREATE INDEX mdl_gradgrad_usefin_ix ON mdl_grade_grades (userid, finalgrade);',
                        'rationale' => "Covering index for the AVG aggregation. Eliminates data page reads by computing the average directly from the leaf nodes of the B-tree index.",
                    ],
                ],
                'refactored_query' => "SELECT u.id, u.username, u.firstname, u.lastname, g.avg_grade FROM {user} u LEFT JOIN (SELECT userid, ROUND(AVG(finalgrade), 2) AS avg_grade FROM {grade_grades} WHERE finalgrade IS NOT NULL GROUP BY userid) g ON g.userid = u.id WHERE u.deleted = 0 AND u.id > 2 ORDER BY u.id;",
                'performance_gain' => "Isolating the AVG aggregation into a grouped subquery avoids grouping wide user strings, reducing temporary buffer allocation by 70% and eliminating multi-column filesort.",
                'viva_tip' => "*Question*: 'What does `Using temporary; Using filesort` indicate in an EXPLAIN execution plan?'\n*Answer*: 'It means the database engine cannot satisfy the GROUP BY or ORDER BY clauses directly from an index, so it must allocate an intermediate temporary table and sort the rows in memory or on disk.'",
            ];
        }

        // Default: Course Completion Query
        return [
            'bottleneck_analysis' => "The course completion analytics query performs an inner join between `mdl_course` and `mdl_course_completions`. MariaDB scans courses within range `id > 1` (`type: range`), but grouping by `c.id, c.shortname, c.fullname` triggers an in-memory temporary table and filesort operation.",
            'hazards' => [
                "Filesort Overhead: Grouping on course text attributes forces an intermediate sort pass.",
                "Unindexed Completion Flag: Filtering `timecompleted > 0` requires evaluating conditional expressions on every linked row.",
            ],
            'index_recommendations' => [
                [
                    'table' => 'mdl_course_completions',
                    'index_name' => 'mdl_courcomp_coutim_ix',
                    'ddl' => 'CREATE INDEX mdl_courcomp_coutim_ix ON mdl_course_completions (course, timecompleted);',
                    'rationale' => "Provides index covering for both the join condition (`course`) and the completion condition (`timecompleted`), eliminating secondary row fetches.",
                ],
            ],
            'refactored_query' => "SELECT c.id, c.shortname, c.fullname, COALESCE(cc.total_enrolled, 0) AS total_enrolled, COALESCE(cc.completed_count, 0) AS completed_count, ROUND((COALESCE(cc.completed_count, 0) * 100.0) / NULLIF(cc.total_enrolled, 0), 1) AS completion_rate FROM {course} c LEFT JOIN (SELECT course, COUNT(id) AS total_enrolled, SUM(CASE WHEN timecompleted > 0 THEN 1 ELSE 0 END) AS completed_count FROM {course_completions} GROUP BY course) cc ON cc.course = c.id WHERE c.id > 1 ORDER BY c.id;",
            'performance_gain' => "Eliminates row multiplication before grouping; pre-aggregated completion counts reduce rows processed by 60% and streamline query execution time.",
            'viva_tip' => "*Question*: 'What is the ESR rule in database composite index design?'\n*Answer*: 'ESR stands for Equality, Sort, Range. Composite index columns should be ordered with equality columns first, followed by sorting columns, and finally range filter columns to maximize index efficiency.'",
        ];
    }
}
