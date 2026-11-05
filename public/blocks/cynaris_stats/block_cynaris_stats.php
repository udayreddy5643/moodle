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
 * Cynaris Stats Block with Analytics, AI Tutor, AI Quiz, AI Recs, and AI Query Auditor.
 *
 * @package    block_cynaris_stats
 * @copyright  2026 Uday Reddy (b.udayreddy5643@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_cynaris_stats extends block_base {

    /**
     * Initialize block title and attributes.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_cynaris_stats');
    }

    /**
     * Specify layout formats where block is permissible.
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'all' => true,
            'my' => true,
            'course-view' => true,
            'site' => true,
        ];
    }

    /**
     * Restrict multiple instances of this block on a single page.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Assemble and return block content with tabbed multi-agent AI interface.
     *
     * @return stdClass
     */
    public function get_content() {
        global $CFG, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Query initial metrics from the 5 approved tables for immediate render.
        $total_courses = $DB->count_records_select('course', 'id > 1');
        $total_users   = $DB->count_records_select('user', 'deleted = 0 AND id > 2');
        $total_completions = $DB->count_records_select('course_completions', 'timecompleted IS NOT NULL AND timecompleted > 0');

        $ajax_analytics_url = $CFG->wwwroot . '/blocks/cynaris_stats/ajax.php';
        $ajax_ai_url        = $CFG->wwwroot . '/blocks/cynaris_stats/ai_tutor_api.php';
        $ajax_quiz_url      = $CFG->wwwroot . '/blocks/cynaris_stats/ai_quiz_api.php';
        $ajax_recs_url      = $CFG->wwwroot . '/blocks/cynaris_stats/ai_recommendations_api.php';
        $ajax_auditor_url   = $CFG->wwwroot . '/blocks/cynaris_stats/ai_auditor_api.php';

        $html = '';
        $html .= html_writer::start_div('cynaris-stats-wrapper p-2');

        // Navigation Tabs (5 Tabs)
        $html .= '
        <div class="btn-group btn-group-sm w-100 mb-2" role="group" aria-label="Cynaris View Switcher">
            <button type="button" id="tab-btn-analytics" class="btn btn-primary" onclick="window.cynarisSwitchTab(\'analytics\')">
                📊 Analytics
            </button>
            <button type="button" id="tab-btn-aitutor" class="btn btn-outline-primary" onclick="window.cynarisSwitchTab(\'aitutor\')">
                🤖 AI Tutor
            </button>
            <button type="button" id="tab-btn-quizgen" class="btn btn-outline-primary" onclick="window.cynarisSwitchTab(\'quizgen\')">
                📝 AI Quiz
            </button>
            <button type="button" id="tab-btn-recommendations" class="btn btn-outline-primary" onclick="window.cynarisSwitchTab(\'recommendations\')">
                💡 AI Recs
            </button>
            <button type="button" id="tab-btn-auditor" class="btn btn-outline-primary" onclick="window.cynarisSwitchTab(\'auditor\')">
                ⚡ Auditor
            </button>
        </div>
        ';

        // =====================================================================
        // TAB 1: Real-Time Analytics Dashboard (Chart.js)
        // =====================================================================
        $html .= html_writer::start_div('cynaris-tab-pane', ['id' => 'cynaris-pane-analytics']);

        $html .= html_writer::start_div('d-flex justify-content-between align-items-center mb-2');
        $html .= html_writer::tag('h6', '📊 Academic Performance', ['class' => 'font-weight-bold text-primary m-0']);
        $html .= html_writer::tag('button', '↻ Refresh', [
            'id' => 'btn-cynaris-refresh',
            'class' => 'btn btn-sm btn-outline-primary py-0 px-2',
            'title' => 'Update charts dynamically via AJAX',
        ]);
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('row text-center mb-3');

        $html .= html_writer::start_div('col-4 p-1');
        $html .= html_writer::start_div('border rounded p-2 bg-light shadow-sm');
        $html .= html_writer::tag('div', (string)$total_courses, ['id' => 'cynaris-count-courses', 'class' => 'h5 mb-0 font-weight-bold text-dark']);
        $html .= html_writer::tag('small', 'Courses', ['class' => 'text-secondary d-block font-weight-bold']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('col-4 p-1');
        $html .= html_writer::start_div('border rounded p-2 bg-light shadow-sm');
        $html .= html_writer::tag('div', (string)$total_users, ['id' => 'cynaris-count-users', 'class' => 'h5 mb-0 font-weight-bold text-success']);
        $html .= html_writer::tag('small', 'Learners', ['class' => 'text-secondary d-block font-weight-bold']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('col-4 p-1');
        $html .= html_writer::start_div('border rounded p-2 bg-light shadow-sm');
        $html .= html_writer::tag('div', (string)$total_completions, ['id' => 'cynaris-count-completions', 'class' => 'h5 mb-0 font-weight-bold text-info']);
        $html .= html_writer::tag('small', 'Completed', ['class' => 'text-secondary d-block font-weight-bold']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= html_writer::end_div();

        // Chart 1: Bar Chart (Course Completion %)
        $html .= html_writer::start_div('chart-container mb-3 border rounded p-2 bg-white shadow-sm', [
            'style' => 'position: relative; max-width: 100%; width: 100%; overflow: hidden;',
        ]);
        $html .= html_writer::tag('p', '📈 Course Completion Rates (%)', ['class' => 'small font-weight-bold text-muted mb-1 text-center']);
        $html .= html_writer::tag('canvas', '', [
            'id' => 'cynaris-completion-chart',
            'height' => '160',
            'role' => 'img',
            'aria-label' => 'Bar chart displaying course completion percentages',
        ]);
        $html .= html_writer::end_div();

        // Chart 2: Line Chart (Student Login Frequency / Activity)
        $html .= html_writer::start_div('chart-container mb-2 border rounded p-2 bg-white shadow-sm', [
            'style' => 'position: relative; max-width: 100%; width: 100%; overflow: hidden;',
        ]);
        $html .= html_writer::tag('p', '📉 Student Login Frequency (7 Days)', ['class' => 'small font-weight-bold text-muted mb-1 text-center']);
        $html .= html_writer::tag('canvas', '', [
            'id' => 'cynaris-login-chart',
            'height' => '160',
            'role' => 'img',
            'aria-label' => 'Line chart displaying student login frequency',
        ]);
        $html .= html_writer::end_div();

        // Live connection indicator
        $html .= html_writer::start_div('d-flex justify-content-between align-items-center mt-2');
        $html .= html_writer::tag('small', '● Live AJAX Connected', ['class' => 'text-success font-weight-bold']);
        $html .= html_writer::tag('small', 'Updated: ' . date('H:i:s'), [
            'id' => 'cynaris-last-updated',
            'class' => 'text-muted',
            'role' => 'status',
            'aria-live' => 'polite',
        ]);
        $html .= html_writer::end_div();

        $html .= html_writer::end_div(); // End tab pane analytics

        // =====================================================================
        // TAB 2: AI Academic Tutor (Groq Primary -> OpenRouter Fallback)
        // =====================================================================
        $html .= html_writer::start_div('cynaris-tab-pane', ['id' => 'cynaris-pane-aitutor', 'style' => 'display: none;']);

        $html .= '
        <div class="border rounded p-2 mb-2 bg-light shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="badge badge-success bg-success text-white">⚡ Primary: Groq</span>
                <span class="badge badge-info bg-info text-white">🛡️ Fallback: OpenRouter</span>
            </div>
            <div class="form-check mt-1 mb-0">
                <input class="form-check-input" type="checkbox" id="cynaris-ai-force-fallback" style="cursor: pointer;">
                <label class="form-check-label small text-muted font-weight-bold" for="cynaris-ai-force-fallback" style="cursor: pointer;">
                    Simulate Groq Outage (Test Fallback)
                </label>
            </div>
        </div>

        <!-- Quick Prompt Chips -->
        <div class="mb-2">
            <small class="text-secondary font-weight-bold d-block mb-1">Quick Prompts:</small>
            <div class="d-flex flex-wrap gap-1">
                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 m-1 text-left" style="font-size: 0.75rem;"
                        onclick="window.cynarisFillPrompt(\'Explain database normalization (1NF, 2NF, 3NF) with a SQL schema example.\')">
                    💡 3NF Normalization
                </button>
                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 m-1 text-left" style="font-size: 0.75rem;"
                        onclick="window.cynarisFillPrompt(\'What Moodle database table tracks student course completions and how does it relate to users?\')">
                    💡 Completion Table
                </button>
                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 m-1 text-left" style="font-size: 0.75rem;"
                        onclick="window.cynarisFillPrompt(\'Explain the full stack architecture flow between Chart.js frontend, PHP Moodle backend, and MariaDB.\')">
                    💡 Full Stack Flow
                </button>
            </div>
        </div>

        <div class="mb-2">
            <textarea id="cynaris-ai-input" class="form-control form-control-sm mb-2" rows="2"
                      placeholder="Ask the AI Tutor any full stack or Moodle question..."></textarea>
            <button type="button" id="btn-cynaris-ask-ai" class="btn btn-primary btn-sm w-100 font-weight-bold">
                🚀 Ask AI Academic Tutor
            </button>
        </div>

        <div id="cynaris-ai-loading" class="text-center my-3" style="display: none;">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <span class="small text-muted ml-2">Consulting AI Tutor...</span>
        </div>

        <div id="cynaris-ai-response-card" class="border rounded p-2 bg-white shadow-sm mt-2" style="display: none;">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-2">
                <span id="cynaris-ai-provider-pill" class="badge badge-primary bg-primary text-white"></span>
                <span id="cynaris-ai-latency-pill" class="badge badge-light bg-light text-muted border"></span>
            </div>
            <div id="cynaris-ai-failover-alert" class="alert alert-warning py-1 px-2 small mb-2" style="display: none;">
                ⚠️ <strong>Failover Active:</strong> Primary provider failed. Responded via fallback.
            </div>
            <div id="cynaris-ai-answer-content" class="small text-dark" style="white-space: pre-line; line-height: 1.45;"></div>
        </div>
        ';

        $html .= html_writer::end_div(); // End tab pane aitutor

        // =====================================================================
        // TAB 3: AI Quiz Generator (MCQs -> Question Bank -> Quiz)
        // =====================================================================
        $html .= html_writer::start_div('cynaris-tab-pane', ['id' => 'cynaris-pane-quizgen', 'style' => 'display: none;']);

        $html .= '
        <div class="border rounded p-2 mb-2 bg-light shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="badge badge-primary bg-primary text-white">🎯 Groq MCQ Generator</span>
                <span class="badge badge-info bg-info text-white">📚 Question Bank API</span>
            </div>
            <p class="small text-muted mb-0">Generate curriculum MCQs from syllabus directly to Question Bank and publish Quiz.</p>
        </div>

        <div class="mb-2">
            <label class="small font-weight-bold text-secondary mb-1">Select Course:</label>
            <select id="cynaris-quiz-course" class="form-control form-control-sm mb-2">
                <option value="2">FS101: Full Stack Web Development (PHP & React)</option>
                <option value="3">DB201: Enterprise Database Engineering & Architecture</option>
            </select>

            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="small font-weight-bold text-secondary mb-1">Question Count:</label>
                    <select id="cynaris-quiz-count" class="form-control form-control-sm">
                        <option value="3">3 Questions</option>
                        <option value="5" selected>5 Questions</option>
                        <option value="10">10 Questions</option>
                    </select>
                </div>
                <div class="col-6 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="cynaris-quiz-force-fallback" style="cursor: pointer;">
                        <label class="form-check-label small text-muted font-weight-bold" for="cynaris-quiz-force-fallback" style="cursor: pointer;">
                            Simulate Outage
                        </label>
                    </div>
                </div>
            </div>

            <button type="button" id="btn-cynaris-gen-quiz" class="btn btn-success btn-sm w-100 font-weight-bold">
                ⚡ Generate & Publish Quiz to Moodle
            </button>
        </div>

        <div id="cynaris-quiz-loading" class="text-center my-3" style="display: none;">
            <div class="spinner-border spinner-border-sm text-success" role="status"></div>
            <span class="small text-muted ml-2">Generating MCQs & writing to Question Bank...</span>
        </div>

        <div id="cynaris-quiz-result-card" class="border rounded p-2 bg-white shadow-sm mt-2" style="display: none;">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-2">
                <span id="cynaris-quiz-provider-pill" class="badge badge-success bg-success text-white"></span>
                <span id="cynaris-quiz-latency-pill" class="badge badge-light bg-light text-muted border"></span>
            </div>
            <div id="cynaris-quiz-failover-alert" class="alert alert-warning py-1 px-2 small mb-2" style="display: none;">
                ⚠️ <strong>Failover Active:</strong> Primary provider failed. Generated via fallback.
            </div>
            <div class="mb-2">
                <h6 id="cynaris-quiz-title" class="font-weight-bold text-primary mb-1"></h6>
                <small id="cynaris-quiz-meta" class="text-muted d-block mb-2"></small>
                <a id="cynaris-quiz-launch-btn" href="#" target="_blank" class="btn btn-sm btn-outline-primary w-100 font-weight-bold">
                    🚀 View / Take Quiz in Moodle
                </a>
            </div>
            <div id="cynaris-quiz-preview-list" class="small border-top pt-2 mt-2" style="max-height: 200px; overflow-y: auto;"></div>
        </div>
        ';

        $html .= html_writer::end_div(); // End tab pane quizgen

        // =====================================================================
        // TAB 4: AI Learning Recommendations (Gradebook & Completions Analysis)
        // =====================================================================
        $html .= html_writer::start_div('cynaris-tab-pane', ['id' => 'cynaris-pane-recommendations', 'style' => 'display: none;']);

        $html .= '
        <div class="border rounded p-2 mb-2 bg-light shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="badge badge-info bg-info text-white">📊 Performance Diagnostic</span>
                <span class="badge badge-success bg-success text-white">💡 Smart Advisory</span>
            </div>
            <p class="small text-muted mb-0">Derive targeted learning plans from student course completions, grade averages, and login logs.</p>
        </div>

        <div class="mb-2">
            <label class="small font-weight-bold text-secondary mb-1">Select Learner Profile:</label>
            <select id="cynaris-rec-user" class="form-control form-control-sm mb-2">
                <option value="3">Rahul Sharma (rahul.s • 90.3% • Top Performer)</option>
                <option value="4">Priya Patel (priya.p • 89.8% • Top Performer)</option>
                <option value="5">Amit Verma (amit.v • 71.5% • Steady Achiever)</option>
                <option value="6">Neha Gupta (neha.g • Assessment Pending)</option>
            </select>

            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="cynaris-rec-force-fallback" style="cursor: pointer;">
                <label class="form-check-label small text-muted font-weight-bold" for="cynaris-rec-force-fallback" style="cursor: pointer;">
                    Simulate Groq Outage (Test Fallback)
                </label>
            </div>

            <button type="button" id="btn-cynaris-gen-recs" class="btn btn-info btn-sm w-100 text-white font-weight-bold">
                🔍 Generate Personalized Learning Plan
            </button>
        </div>

        <div id="cynaris-rec-loading" class="text-center my-3" style="display: none;">
            <div class="spinner-border spinner-border-sm text-info" role="status"></div>
            <span class="small text-muted ml-2">Analyzing gradebook & generating advisory plan...</span>
        </div>

        <div id="cynaris-rec-result-card" class="border rounded p-2 bg-white shadow-sm mt-2" style="display: none;">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-2">
                <span id="cynaris-rec-provider-pill" class="badge badge-info bg-info text-white"></span>
                <span id="cynaris-rec-latency-pill" class="badge badge-light bg-light text-muted border"></span>
            </div>
            <div id="cynaris-rec-failover-alert" class="alert alert-warning py-1 px-2 small mb-2" style="display: none;">
                ⚠️ <strong>Failover Active:</strong> Primary provider failed. Generated via fallback.
            </div>

            <div class="p-2 border rounded bg-light mb-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <strong id="cynaris-rec-student-name" class="text-dark"></strong>
                    <span id="cynaris-rec-persona-badge" class="badge badge-secondary bg-secondary text-white"></span>
                </div>
                <p id="cynaris-rec-summary" class="small text-muted mb-0"></p>
            </div>

            <div class="mb-2">
                <small class="font-weight-bold text-success d-block">🌟 Key Strengths:</small>
                <ul id="cynaris-rec-strengths" class="small pl-3 mb-2 text-dark"></ul>

                <small class="font-weight-bold text-danger d-block">🎯 Growth Areas / Gaps:</small>
                <ul id="cynaris-rec-gaps" class="small pl-3 mb-2 text-dark"></ul>
            </div>

            <div class="mb-2 border-top pt-2">
                <small class="font-weight-bold text-primary d-block">🚀 Targeted Action Plan:</small>
                <ol id="cynaris-rec-actions" class="small pl-3 mb-2 text-dark"></ol>
            </div>

            <div class="p-2 border rounded bg-light border-info">
                <small class="font-weight-bold text-info d-block">💬 Viva Voce Prep Focus:</small>
                <p id="cynaris-rec-viva" class="small text-dark mb-0"></p>
            </div>
        </div>
        ';

        $html .= html_writer::end_div(); // End tab pane recommendations

        // =====================================================================
        // TAB 5: AI Query Performance Auditor (EXPLAIN & Index Optimisation)
        // =====================================================================
        $html .= html_writer::start_div('cynaris-tab-pane', ['id' => 'cynaris-pane-auditor', 'style' => 'display: none;']);

        $html .= '
        <div class="border rounded p-2 mb-2 bg-light shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="badge badge-warning bg-warning text-dark font-weight-bold">⚡ EXPLAIN Profiler</span>
                <span class="badge badge-dark bg-dark text-white">🗄️ Index Optimizer</span>
            </div>
            <p class="small text-muted mb-0">Analyze execution plans for Moodle analytics queries and generate composite index optimizations.</p>
        </div>

        <div class="mb-2">
            <label class="small font-weight-bold text-secondary mb-1">Select Query for Audit:</label>
            <select id="cynaris-audit-query" class="form-control form-control-sm mb-2">
                <option value="login_frequency" selected>Query C: 7-Day Login Windowing ({user} + {log})</option>
                <option value="course_completion">Query A: Course Completion ({course} + {course_completions})</option>
                <option value="grade_average">Query B: Student Grade Averages ({user} + {grade_grades})</option>
            </select>

            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="cynaris-audit-force-fallback" style="cursor: pointer;">
                <label class="form-check-label small text-muted font-weight-bold" for="cynaris-audit-force-fallback" style="cursor: pointer;">
                    Simulate Groq Outage (Test Fallback)
                </label>
            </div>

            <button type="button" id="btn-cynaris-run-audit" class="btn btn-warning btn-sm w-100 font-weight-bold text-dark">
                ⚡ Run EXPLAIN & AI Performance Audit
            </button>
        </div>

        <div id="cynaris-audit-loading" class="text-center my-3" style="display: none;">
            <div class="spinner-border spinner-border-sm text-warning" role="status"></div>
            <span class="small text-muted ml-2">Running EXPLAIN plan & formulating DBA recommendations...</span>
        </div>

        <div id="cynaris-audit-result-card" class="border rounded p-2 bg-white shadow-sm mt-2" style="display: none;">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-2">
                <span id="cynaris-audit-provider-pill" class="badge badge-warning bg-warning text-dark"></span>
                <span id="cynaris-audit-latency-pill" class="badge badge-light bg-light text-muted border"></span>
            </div>
            <div id="cynaris-audit-failover-alert" class="alert alert-warning py-1 px-2 small mb-2" style="display: none;">
                ⚠️ <strong>Failover Active:</strong> Primary provider failed. Audited via fallback.
            </div>

            <!-- EXPLAIN Plan Summary Table -->
            <div class="mb-2">
                <small class="font-weight-bold text-dark d-block mb-1">MariaDB EXPLAIN Execution Plan:</small>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered text-center small mb-1" style="font-size: 0.75rem;">
                        <thead class="bg-light">
                            <tr><th>Table</th><th>Type</th><th>Key</th><th>Rows</th><th>Extra</th></tr>
                        </thead>
                        <tbody id="cynaris-audit-explain-rows"></tbody>
                    </table>
                </div>
            </div>

            <!-- Bottleneck Diagnostic -->
            <div class="p-2 border rounded bg-light mb-2">
                <small class="font-weight-bold text-danger d-block">⚠️ Bottleneck Analysis:</small>
                <p id="cynaris-audit-bottleneck" class="small text-muted mb-0"></p>
            </div>

            <!-- Recommended Index DDL -->
            <div class="mb-2">
                <small class="font-weight-bold text-success d-block">🛠️ Recommended Index (DDL):</small>
                <pre id="cynaris-audit-ddl" class="p-2 bg-dark text-success rounded small mb-1" style="font-size: 0.75rem; white-space: pre-wrap;"></pre>
            </div>

            <!-- Refactored Query -->
            <div class="mb-2">
                <small class="font-weight-bold text-primary d-block">🚀 Refactored Query Suggestion:</small>
                <pre id="cynaris-audit-refactored" class="p-2 bg-light border rounded small mb-1" style="font-size: 0.72rem; white-space: pre-wrap;"></pre>
            </div>

            <!-- Performance Gain & Viva Tip -->
            <div class="p-2 border rounded bg-light border-warning">
                <small class="font-weight-bold text-dark d-block">📈 Estimated Improvement: <span id="cynaris-audit-gain" class="text-success font-weight-normal"></span></small>
                <small class="font-weight-bold text-info d-block mt-1">💬 Viva Voce DBA Tip:</small>
                <p id="cynaris-audit-viva" class="small text-dark mb-0"></p>
            </div>
        </div>
        ';

        $html .= html_writer::end_div(); // End tab pane auditor

        $html .= html_writer::end_div(); // End stats wrapper

        // Chart.js CDN and Client-side Application Controller
        $html .= '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
        $html .= '
        <script>
        (function() {
            var completionChart = null;
            var loginChart = null;
            var analyticsAjaxUrl = ' . json_encode($ajax_analytics_url) . ';
            var aiAjaxUrl        = ' . json_encode($ajax_ai_url) . ';
            var quizAjaxUrl      = ' . json_encode($ajax_quiz_url) . ';
            var recsAjaxUrl      = ' . json_encode($ajax_recs_url) . ';
            var auditorAjaxUrl   = ' . json_encode($ajax_auditor_url) . ';

            // Tab Switching Controller (5 Tabs)
            window.cynarisSwitchTab = function(tab) {
                var paneAnalytics = document.getElementById("cynaris-pane-analytics");
                var paneAi = document.getElementById("cynaris-pane-aitutor");
                var paneQuiz = document.getElementById("cynaris-pane-quizgen");
                var paneRecs = document.getElementById("cynaris-pane-recommendations");
                var paneAuditor = document.getElementById("cynaris-pane-auditor");

                var btnAnalytics = document.getElementById("tab-btn-analytics");
                var btnAi = document.getElementById("tab-btn-aitutor");
                var btnQuiz = document.getElementById("tab-btn-quizgen");
                var btnRecs = document.getElementById("tab-btn-recommendations");
                var btnAuditor = document.getElementById("tab-btn-auditor");

                if (!paneAnalytics || !paneAi || !paneQuiz || !paneRecs || !paneAuditor) return;

                paneAnalytics.style.display = "none";
                paneAi.style.display = "none";
                paneQuiz.style.display = "none";
                paneRecs.style.display = "none";
                paneAuditor.style.display = "none";

                btnAnalytics.className = "btn btn-outline-primary";
                btnAi.className = "btn btn-outline-primary";
                btnQuiz.className = "btn btn-outline-primary";
                btnRecs.className = "btn btn-outline-primary";
                btnAuditor.className = "btn btn-outline-primary";

                if (tab === "aitutor") {
                    paneAi.style.display = "block";
                    btnAi.className = "btn btn-primary";
                } else if (tab === "quizgen") {
                    paneQuiz.style.display = "block";
                    btnQuiz.className = "btn btn-primary";
                } else if (tab === "recommendations") {
                    paneRecs.style.display = "block";
                    btnRecs.className = "btn btn-primary";
                } else if (tab === "auditor") {
                    paneAuditor.style.display = "block";
                    btnAuditor.className = "btn btn-primary";
                } else {
                    paneAnalytics.style.display = "block";
                    btnAnalytics.className = "btn btn-primary";
                }
            };

            // Quick Prompt Fill Helper
            window.cynarisFillPrompt = function(promptText) {
                var input = document.getElementById("cynaris-ai-input");
                if (input) {
                    input.value = promptText;
                    input.focus();
                }
            };

            // 1. Analytics Fetch & Render
            function fetchAndUpdateStats() {
                var btn = document.getElementById("btn-cynaris-refresh");
                if (btn) { btn.disabled = true; btn.innerText = "⏳..."; }

                fetch(analyticsAjaxUrl, { method: "GET", headers: { "Accept": "application/json" } })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.status !== "success") return;

                        if (data.courses) {
                            var coursesCount = document.getElementById("cynaris-count-courses");
                            if (coursesCount) coursesCount.innerText = data.courses.length;
                        }
                        if (data.students) {
                            var usersCount = document.getElementById("cynaris-count-users");
                            if (usersCount) usersCount.innerText = data.students.length;
                        }

                        // Bar Chart: Completion %
                        var courseLabels = data.courses.map(function(c) { return c.code; });
                        var completionRates = data.courses.map(function(c) { return c.completion_rate; });

                        var ctxCompletion = document.getElementById("cynaris-completion-chart");
                        if (ctxCompletion) {
                            if (completionChart) {
                                completionChart.data.labels = courseLabels;
                                completionChart.data.datasets[0].data = completionRates;
                                completionChart.update();
                            } else {
                                completionChart = new Chart(ctxCompletion, {
                                    type: "bar",
                                    data: {
                                        labels: courseLabels,
                                        datasets: [{
                                            label: "Completion %",
                                            data: completionRates,
                                            backgroundColor: ["rgba(54, 162, 235, 0.7)", "rgba(75, 192, 192, 0.7)", "rgba(153, 102, 255, 0.7)"],
                                            borderColor: ["rgb(54, 162, 235)", "rgb(75, 192, 192)", "rgb(153, 102, 255)"],
                                            borderWidth: 1,
                                            borderRadius: 4
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        scales: {
                                            y: { beginAtZero: true, max: 100, ticks: { callback: function(v) { return v + "%"; } } }
                                        },
                                        plugins: { legend: { display: false } }
                                    }
                                });
                            }
                        }

                        // Line Chart: Logins & Grades
                        var studentNames = data.students.map(function(s) { return s.name; });
                        var studentGrades = data.students.map(function(s) { return s.avg_grade !== null ? s.avg_grade : 0; });
                        var loginCounts = data.students.map(function(s) {
                            var match = data.logins.find(function(l) { return l.id === s.id; });
                            return match ? match.login_count : 0;
                        });

                        var ctxLogin = document.getElementById("cynaris-login-chart");
                        if (ctxLogin) {
                            if (loginChart) {
                                loginChart.data.labels = studentNames;
                                loginChart.data.datasets[0].data = loginCounts;
                                loginChart.data.datasets[1].data = studentGrades;
                                loginChart.update();
                            } else {
                                loginChart = new Chart(ctxLogin, {
                                    type: "line",
                                    data: {
                                        labels: studentNames,
                                        datasets: [
                                            {
                                                label: "Logins (7 Days)",
                                                data: loginCounts,
                                                borderColor: "rgb(255, 99, 132)",
                                                backgroundColor: "rgba(255, 99, 132, 0.2)",
                                                tension: 0.3,
                                                fill: true,
                                                yAxisID: "y"
                                            },
                                            {
                                                label: "Avg Grade",
                                                data: studentGrades,
                                                borderColor: "rgb(54, 162, 235)",
                                                borderDash: [5, 5],
                                                tension: 0.2,
                                                yAxisID: "y1"
                                            }
                                        ]
                                    },
                                    options: {
                                        responsive: true,
                                        scales: {
                                            y: { type: "linear", display: true, position: "left", beginAtZero: true, title: { display: true, text: "Logins" } },
                                            y1: { type: "linear", display: true, position: "right", beginAtZero: true, max: 100, grid: { drawOnChartArea: false }, title: { display: true, text: "Grade" } }
                                        }
                                    }
                                });
                            }
                        }

                        var tsElem = document.getElementById("cynaris-last-updated");
                        if (tsElem) {
                            var now = new Date();
                            tsElem.innerText = "Updated: " + now.toLocaleTimeString();
                        }
                    })
                    .catch(function(err) {
                        console.error("Cynaris Stats AJAX fetch error:", err);
                    })
                    .finally(function() {
                        if (btn) { btn.disabled = false; btn.innerText = "↻ Refresh"; }
                    });
            }

            // 2. AI Tutor Ask Handler
            function handleAskAi() {
                var input = document.getElementById("cynaris-ai-input");
                var question = input ? input.value.trim() : "";
                if (!question) {
                    alert("Please enter a question for the AI Tutor.");
                    return;
                }

                var forceFallback = document.getElementById("cynaris-ai-force-fallback")?.checked || false;
                var btnAsk = document.getElementById("btn-cynaris-ask-ai");
                var loading = document.getElementById("cynaris-ai-loading");
                var card = document.getElementById("cynaris-ai-response-card");
                var pillProvider = document.getElementById("cynaris-ai-provider-pill");
                var pillLatency = document.getElementById("cynaris-ai-latency-pill");
                var alertFailover = document.getElementById("cynaris-ai-failover-alert");
                var answerDiv = document.getElementById("cynaris-ai-answer-content");

                if (btnAsk) btnAsk.disabled = true;
                if (loading) loading.style.display = "block";
                if (card) card.style.display = "none";

                fetch(aiAjaxUrl, {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "Accept": "application/json" },
                    body: JSON.stringify({ question: question, force_fallback: forceFallback ? 1 : 0 })
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.status === "success") {
                        if (pillProvider) {
                            pillProvider.innerText = data.provider + " • " + data.model;
                            pillProvider.className = data.fallback_triggered 
                                ? "badge badge-warning bg-warning text-dark" 
                                : "badge badge-success bg-success text-white";
                        }
                        if (pillLatency) pillLatency.innerText = "⚡ " + data.latency_ms + " ms";
                        if (alertFailover) {
                            alertFailover.style.display = data.fallback_triggered ? "block" : "none";
                            if (data.primary_failure_reason) alertFailover.innerHTML = "⚠️ <strong>Failover Active:</strong> " + data.primary_failure_reason;
                        }
                        if (answerDiv) answerDiv.innerText = data.answer;
                        if (card) card.style.display = "block";
                    } else {
                        alert("AI Tutor Error: " + (data.message || "Unknown error"));
                    }
                })
                .catch(function(err) {
                    console.error("AI Tutor API fetch error:", err);
                    alert("Network error: " + err.message);
                })
                .finally(function() {
                    if (loading) loading.style.display = "none";
                    if (btnAsk) btnAsk.disabled = false;
                });
            }

            // 3. AI Quiz Generation Handler
            function handleGenerateQuiz() {
                var courseSelect = document.getElementById("cynaris-quiz-course");
                var countSelect  = document.getElementById("cynaris-quiz-count");
                var forceFallback = document.getElementById("cynaris-quiz-force-fallback")?.checked || false;

                var courseId = courseSelect ? courseSelect.value : 2;
                var count = countSelect ? countSelect.value : 5;

                var btnGen = document.getElementById("btn-cynaris-gen-quiz");
                var loading = document.getElementById("cynaris-quiz-loading");
                var card = document.getElementById("cynaris-quiz-result-card");
                var pillProvider = document.getElementById("cynaris-quiz-provider-pill");
                var pillLatency = document.getElementById("cynaris-quiz-latency-pill");
                var alertFailover = document.getElementById("cynaris-quiz-failover-alert");
                var titleElem = document.getElementById("cynaris-quiz-title");
                var metaElem = document.getElementById("cynaris-quiz-meta");
                var launchBtn = document.getElementById("cynaris-quiz-launch-btn");
                var previewList = document.getElementById("cynaris-quiz-preview-list");

                if (btnGen) btnGen.disabled = true;
                if (loading) loading.style.display = "block";
                if (card) card.style.display = "none";

                fetch(quizAjaxUrl, {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "Accept": "application/json" },
                    body: JSON.stringify({ course_id: parseInt(courseId), count: parseInt(count), force_fallback: forceFallback ? 1 : 0 })
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.status === "success") {
                        if (pillProvider) {
                            pillProvider.innerText = data.provider + " (" + data.model + ")";
                            pillProvider.className = data.fallback_triggered ? "badge badge-warning bg-warning text-dark" : "badge badge-success bg-success text-white";
                        }
                        if (pillLatency) pillLatency.innerText = "⚡ " + data.latency_ms + " ms";
                        if (alertFailover) {
                            alertFailover.style.display = data.fallback_triggered ? "block" : "none";
                            if (data.primary_failure_reason) alertFailover.innerHTML = "⚠️ <strong>Failover Active:</strong> " + data.primary_failure_reason;
                        }
                        if (titleElem) titleElem.innerText = data.quiz.name;
                        if (metaElem) metaElem.innerText = "Course: " + data.course.shortname + " • Questions in Bank: " + data.questions_count + " • Slots: " + data.quiz.slots_added;
                        if (launchBtn) launchBtn.href = data.quiz.url;

                        if (previewList && data.questions) {
                            var qHtml = "<strong>Generated Question Bank Items:</strong><ol class=\"pl-3 mb-0 mt-1\">";
                            data.questions.forEach(function(q) {
                                qHtml += "<li><strong>" + q.question + "</strong><br><small class=\"text-success font-weight-bold\">✓ Answer: " + q[q.correct_answer] + "</small></li>";
                            });
                            qHtml += "</ol>";
                            previewList.innerHTML = qHtml;
                        }

                        if (card) card.style.display = "block";
                    } else {
                        alert("AI Quiz Error: " + (data.message || "Unknown error"));
                    }
                })
                .catch(function(err) {
                    console.error("AI Quiz Generator error:", err);
                    alert("Network error generating quiz: " + err.message);
                })
                .finally(function() {
                    if (loading) loading.style.display = "none";
                    if (btnGen) btnGen.disabled = false;
                });
            }

            // 4. AI Learning Recommendations Handler
            function handleGenerateRecommendations() {
                var userSelect = document.getElementById("cynaris-rec-user");
                var forceFallback = document.getElementById("cynaris-rec-force-fallback")?.checked || false;
                var userId = userSelect ? userSelect.value : 3;

                var btnRec = document.getElementById("btn-cynaris-gen-recs");
                var loading = document.getElementById("cynaris-rec-loading");
                var card = document.getElementById("cynaris-rec-result-card");
                var pillProvider = document.getElementById("cynaris-rec-provider-pill");
                var pillLatency = document.getElementById("cynaris-rec-latency-pill");
                var alertFailover = document.getElementById("cynaris-rec-failover-alert");
                var studentNameElem = document.getElementById("cynaris-rec-student-name");
                var personaBadge = document.getElementById("cynaris-rec-persona-badge");
                var summaryElem = document.getElementById("cynaris-rec-summary");
                var strengthsElem = document.getElementById("cynaris-rec-strengths");
                var gapsElem = document.getElementById("cynaris-rec-gaps");
                var actionsElem = document.getElementById("cynaris-rec-actions");
                var vivaElem = document.getElementById("cynaris-rec-viva");

                if (btnRec) btnRec.disabled = true;
                if (loading) loading.style.display = "block";
                if (card) card.style.display = "none";

                fetch(recsAjaxUrl, {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "Accept": "application/json" },
                    body: JSON.stringify({ user_id: parseInt(userId), force_fallback: forceFallback ? 1 : 0 })
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.status === "success") {
                        if (pillProvider) {
                            pillProvider.innerText = data.provider + " (" + data.model + ")";
                            pillProvider.className = data.fallback_triggered ? "badge badge-warning bg-warning text-dark" : "badge badge-info bg-info text-white";
                        }
                        if (pillLatency) pillLatency.innerText = "⚡ " + data.latency_ms + " ms";
                        if (alertFailover) {
                            alertFailover.style.display = data.fallback_triggered ? "block" : "none";
                            if (data.primary_failure_reason) alertFailover.innerHTML = "⚠️ <strong>Failover Active:</strong> " + data.primary_failure_reason;
                        }

                        var p = data.profile;
                        var r = data.recommendations;

                        if (studentNameElem) studentNameElem.innerText = p.user.fullname + " (" + p.user.username + ")";
                        if (personaBadge) personaBadge.innerText = p.metrics.persona_tier;
                        if (summaryElem) summaryElem.innerText = r.performance_summary;

                        if (strengthsElem && r.strengths) {
                            strengthsElem.innerHTML = r.strengths.map(function(s) { return "<li>" + s + "</li>"; }).join("");
                        }
                        if (gapsElem && r.growth_areas) {
                            gapsElem.innerHTML = r.growth_areas.map(function(g) { return "<li>" + g + "</li>"; }).join("");
                        }
                        if (actionsElem && r.action_plan) {
                            actionsElem.innerHTML = r.action_plan.map(function(a) { return "<li>" + a + "</li>"; }).join("");
                        }
                        if (vivaElem) vivaElem.innerText = r.viva_prep_tip;

                        if (card) card.style.display = "block";
                    } else {
                        alert("AI Recommendations Error: " + (data.message || "Unknown error"));
                    }
                })
                .catch(function(err) {
                    console.error("AI Recommendations error:", err);
                    alert("Network error: " + err.message);
                })
                .finally(function() {
                    if (loading) loading.style.display = "none";
                    if (btnRec) btnRec.disabled = false;
                });
            }

            // 5. AI Query Auditor Handler
            function handleRunQueryAudit() {
                var querySelect = document.getElementById("cynaris-audit-query");
                var forceFallback = document.getElementById("cynaris-audit-force-fallback")?.checked || false;
                var queryKey = querySelect ? querySelect.value : "login_frequency";

                var btnAudit = document.getElementById("btn-cynaris-run-audit");
                var loading = document.getElementById("cynaris-audit-loading");
                var card = document.getElementById("cynaris-audit-result-card");
                var pillProvider = document.getElementById("cynaris-audit-provider-pill");
                var pillLatency = document.getElementById("cynaris-audit-latency-pill");
                var alertFailover = document.getElementById("cynaris-audit-failover-alert");
                var explainTbody = document.getElementById("cynaris-audit-explain-rows");
                var bottleneckElem = document.getElementById("cynaris-audit-bottleneck");
                var ddlElem = document.getElementById("cynaris-audit-ddl");
                var refactoredElem = document.getElementById("cynaris-audit-refactored");
                var gainElem = document.getElementById("cynaris-audit-gain");
                var vivaElem = document.getElementById("cynaris-audit-viva");

                if (btnAudit) btnAudit.disabled = true;
                if (loading) loading.style.display = "block";
                if (card) card.style.display = "none";

                fetch(auditorAjaxUrl, {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "Accept": "application/json" },
                    body: JSON.stringify({ query_key: queryKey, force_fallback: forceFallback ? 1 : 0 })
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.status === "success") {
                        if (pillProvider) {
                            pillProvider.innerText = data.provider + " (" + data.model + ")";
                            pillProvider.className = data.fallback_triggered ? "badge badge-warning bg-warning text-dark" : "badge badge-dark bg-dark text-white";
                        }
                        if (pillLatency) pillLatency.innerText = "⚡ " + data.latency_ms + " ms";
                        if (alertFailover) {
                            alertFailover.style.display = data.fallback_triggered ? "block" : "none";
                            if (data.primary_failure_reason) alertFailover.innerHTML = "⚠️ <strong>Failover Active:</strong> " + data.primary_failure_reason;
                        }

                        // Populate EXPLAIN Plan Rows
                        if (explainTbody && data.explain && data.explain.steps) {
                            var rowsHtml = "";
                            data.explain.steps.forEach(function(s) {
                                var badgeClass = s.type === "ALL" ? "badge badge-danger bg-danger text-white" : "badge badge-success bg-success text-white";
                                rowsHtml += "<tr>"
                                    + "<td><strong>`" + s.table + "`</strong></td>"
                                    + "<td><span class=\"" + badgeClass + "\">" + s.type + "</span></td>"
                                    + "<td><code>" + s.key + "</code></td>"
                                    + "<td>" + s.rows + "</td>"
                                    + "<td class=\"text-left\"><small>" + s.extra + "</small></td>"
                                    + "</tr>";
                            });
                            explainTbody.innerHTML = rowsHtml;
                        }

                        var a = data.audit;
                        if (bottleneckElem) bottleneckElem.innerText = a.bottleneck_analysis;

                        if (ddlElem && a.index_recommendations && a.index_recommendations.length > 0) {
                            ddlElem.innerText = a.index_recommendations.map(function(idx) { return idx.ddl + "\n-- " + idx.rationale; }).join("\n\n");
                        }
                        if (refactoredElem) refactoredElem.innerText = a.refactored_query;
                        if (gainElem) gainElem.innerText = a.performance_gain;
                        if (vivaElem) vivaElem.innerText = a.viva_tip;

                        if (card) card.style.display = "block";
                    } else {
                        alert("AI Auditor Error: " + (data.message || "Unknown error"));
                    }
                })
                .catch(function(err) {
                    console.error("AI Auditor error:", err);
                    alert("Network error: " + err.message);
                })
                .finally(function() {
                    if (loading) loading.style.display = "none";
                    if (btnAudit) btnAudit.disabled = false;
                });
            }

            // Event Listeners Initialization
            function initBlock() {
                var btnRefresh = document.getElementById("btn-cynaris-refresh");
                if (btnRefresh) btnRefresh.addEventListener("click", fetchAndUpdateStats);

                var btnAskAi = document.getElementById("btn-cynaris-ask-ai");
                if (btnAskAi) btnAskAi.addEventListener("click", handleAskAi);

                var btnGenQuiz = document.getElementById("btn-cynaris-gen-quiz");
                if (btnGenQuiz) btnGenQuiz.addEventListener("click", handleGenerateQuiz);

                var btnGenRecs = document.getElementById("btn-cynaris-gen-recs");
                if (btnGenRecs) btnGenRecs.addEventListener("click", handleGenerateRecommendations);

                var btnAudit = document.getElementById("btn-cynaris-run-audit");
                if (btnAudit) btnAudit.addEventListener("click", handleRunQueryAudit);

                fetchAndUpdateStats();
            }

            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", initBlock);
            } else {
                initBlock();
            }
        })();
        </script>
        ';

        $this->content->text = $html;
        $this->content->footer = 'Cynaris Full Stack Dev • Week 6 Day 4';

        return $this->content;
    }
}
