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
 * Cynaris Stats Block with Dynamic Chart.js Analytics & AI Tutor (Groq/OpenRouter Fallback).
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
     * Assemble and return block content with tabbed Analytics and AI Academic Tutor views.
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

        $html = '';
        $html .= html_writer::start_div('cynaris-stats-wrapper p-2');

        // Navigation Tabs: Analytics vs. AI Tutor
        $html .= '
        <div class="btn-group btn-group-sm w-100 mb-2" role="group" aria-label="Cynaris View Switcher">
            <button type="button" id="tab-btn-analytics" class="btn btn-primary" onclick="window.cynarisSwitchTab(\'analytics\')">
                📊 Analytics
            </button>
            <button type="button" id="tab-btn-aitutor" class="btn btn-outline-primary" onclick="window.cynarisSwitchTab(\'aitutor\')">
                🤖 AI Tutor
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

        // Metrics counter summary badges
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

        $html .= html_writer::end_div(); // End metrics row

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

        // Dual Engine Architecture Banner
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

        <!-- Question Input & Submit -->
        <div class="mb-2">
            <textarea id="cynaris-ai-input" class="form-control form-control-sm mb-2" rows="2"
                      placeholder="Ask the AI Tutor any full stack or Moodle question..."></textarea>
            <button type="button" id="btn-cynaris-ask-ai" class="btn btn-primary btn-sm w-100 font-weight-bold">
                🚀 Ask AI Academic Tutor
            </button>
        </div>

        <!-- Loading Indicator -->
        <div id="cynaris-ai-loading" class="text-center my-3" style="display: none;">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <span class="small text-muted ml-2">Consulting AI Tutor...</span>
        </div>

        <!-- AI Tutor Response Card -->
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

            // Tab Switching Controller
            window.cynarisSwitchTab = function(tab) {
                var paneAnalytics = document.getElementById("cynaris-pane-analytics");
                var paneAi = document.getElementById("cynaris-pane-aitutor");
                var btnAnalytics = document.getElementById("tab-btn-analytics");
                var btnAi = document.getElementById("tab-btn-aitutor");

                if (!paneAnalytics || !paneAi || !btnAnalytics || !btnAi) return;

                if (tab === "aitutor") {
                    paneAnalytics.style.display = "none";
                    paneAi.style.display = "block";
                    btnAnalytics.className = "btn btn-outline-primary";
                    btnAi.className = "btn btn-primary";
                } else {
                    paneAi.style.display = "none";
                    paneAnalytics.style.display = "block";
                    btnAi.className = "btn btn-outline-primary";
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

                var payload = {
                    question: question,
                    force_fallback: forceFallback ? 1 : 0
                };

                fetch(aiAjaxUrl, {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "Accept": "application/json" },
                    body: JSON.stringify(payload)
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
                        if (pillLatency) {
                            pillLatency.innerText = "⚡ " + data.latency_ms + " ms";
                        }
                        if (alertFailover) {
                            alertFailover.style.display = data.fallback_triggered ? "block" : "none";
                            if (data.primary_failure_reason) {
                                alertFailover.innerHTML = "⚠️ <strong>Failover Active:</strong> " + data.primary_failure_reason;
                            }
                        }
                        if (answerDiv) {
                            answerDiv.innerText = data.answer;
                        }
                        if (card) card.style.display = "block";
                    } else {
                        alert("AI Tutor Error: " + (data.message || "Unknown error"));
                    }
                })
                .catch(function(err) {
                    console.error("AI Tutor API fetch error:", err);
                    alert("Network error contacting AI Tutor API: " + err.message);
                })
                .finally(function() {
                    if (loading) loading.style.display = "none";
                    if (btnAsk) btnAsk.disabled = false;
                });
            }

            // Event Listeners Initialization
            function initBlock() {
                var btnRefresh = document.getElementById("btn-cynaris-refresh");
                if (btnRefresh) {
                    btnRefresh.addEventListener("click", fetchAndUpdateStats);
                }

                var btnAskAi = document.getElementById("btn-cynaris-ask-ai");
                if (btnAskAi) {
                    btnAskAi.addEventListener("click", handleAskAi);
                }

                var aiInput = document.getElementById("cynaris-ai-input");
                if (aiInput) {
                    aiInput.addEventListener("keydown", function(e) {
                        if (e.key === "Enter" && !e.shiftKey) {
                            e.preventDefault();
                            handleAskAi();
                        }
                    });
                }

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
        $this->content->footer = 'Cynaris Full Stack Dev • Week 6 Day 1';

        return $this->content;
    }
}
