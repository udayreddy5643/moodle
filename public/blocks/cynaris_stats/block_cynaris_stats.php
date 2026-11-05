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
 * Cynaris Stats Block class definition with dynamic Chart.js visualizations.
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
     * Assemble and return block content with dynamic Chart.js visualizations.
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

        // Query server-side stats from the 5 approved tables for immediate initial render.
        $total_courses = $DB->count_records_select('course', 'id > 1');
        $total_users   = $DB->count_records_select('user', 'deleted = 0 AND id > 2');
        $total_completions = $DB->count_records_select('course_completions', 'timecompleted IS NOT NULL AND timecompleted > 0');

        $ajax_url = $CFG->wwwroot . '/blocks/cynaris_stats/ajax.php';

        // Construct HTML container.
        $html = '';
        $html .= html_writer::start_div('cynaris-analytics-dashboard p-2');

        // Header and description.
        $html .= html_writer::start_div('d-flex justify-content-between align-items-center mb-2');
        $html .= html_writer::tag('h6', '📊 Real-Time Academic Analytics', ['class' => 'font-weight-bold text-primary m-0']);
        $html .= html_writer::tag('button', '↻ Refresh', [
            'id' => 'btn-cynaris-refresh',
            'class' => 'btn btn-sm btn-outline-primary py-0 px-2',
            'title' => 'Update charts dynamically via AJAX',
        ]);
        $html .= html_writer::end_div();

        // Metric counter summary badges.
        $html .= html_writer::start_div('row text-center mb-3');

        $html .= html_writer::start_div('col-4 p-1');
        $html .= html_writer::start_div('border rounded p-2 bg-light shadow-sm');
        $html .= html_writer::tag('div', (string)$total_courses, ['id' => 'cynaris-count-courses', 'class' => 'h5 mb-0 font-weight-bold text-dark']);
        $html .= html_writer::tag('small', 'Active Courses', ['class' => 'text-secondary d-block font-weight-bold']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('col-4 p-1');
        $html .= html_writer::start_div('border rounded p-2 bg-light shadow-sm');
        $html .= html_writer::tag('div', (string)$total_users, ['id' => 'cynaris-count-users', 'class' => 'h5 mb-0 font-weight-bold text-success']);
        $html .= html_writer::tag('small', 'Active Learners', ['class' => 'text-secondary d-block font-weight-bold']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('col-4 p-1');
        $html .= html_writer::start_div('border rounded p-2 bg-light shadow-sm');
        $html .= html_writer::tag('div', (string)$total_completions, ['id' => 'cynaris-count-completions', 'class' => 'h5 mb-0 font-weight-bold text-info']);
        $html .= html_writer::tag('small', 'Completions', ['class' => 'text-secondary d-block font-weight-bold']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= html_writer::end_div(); // End row

        // Chart 1: Bar Chart (Course Completion %).
        $html .= html_writer::start_div('chart-container mb-3 border rounded p-2 bg-white shadow-sm');
        $html .= html_writer::tag('p', '📈 Course Completion Rates (%)', ['class' => 'small font-weight-bold text-muted mb-1 text-center']);
        $html .= html_writer::tag('canvas', '', ['id' => 'cynaris-completion-chart', 'height' => '170']);
        $html .= html_writer::end_div();

        // Chart 2: Line Chart (Student Login Frequency / 7-Day Activity).
        $html .= html_writer::start_div('chart-container mb-2 border rounded p-2 bg-white shadow-sm');
        $html .= html_writer::tag('p', '📉 Student Login Frequency (Last 7 Days)', ['class' => 'small font-weight-bold text-muted mb-1 text-center']);
        $html .= html_writer::tag('canvas', '', ['id' => 'cynaris-login-chart', 'height' => '170']);
        $html .= html_writer::end_div();

        // Timestamp status badge.
        $html .= html_writer::start_div('d-flex justify-content-between align-items-center mt-2');
        $html .= html_writer::tag('small', '● Live AJAX Connected', ['class' => 'text-success font-weight-bold']);
        $html .= html_writer::tag('small', 'Updated: ' . date('H:i:s'), ['id' => 'cynaris-last-updated', 'class' => 'text-muted']);
        $html .= html_writer::end_div();

        $html .= html_writer::end_div(); // End dashboard container

        // Include Chart.js CDN and Dynamic AJAX Poller.
        $html .= '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
        $html .= '
        <script>
        (function() {
            var completionChart = null;
            var loginChart = null;
            var ajaxUrl = ' . json_encode($ajax_url) . ';

            function fetchAndUpdateStats() {
                var btn = document.getElementById("btn-cynaris-refresh");
                if (btn) { btn.disabled = true; btn.innerText = "⏳..."; }

                fetch(ajaxUrl, { method: "GET", headers: { "Accept": "application/json" } })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.status !== "success") { return; }

                        // 1. Update summary counters
                        if (data.courses) {
                            var coursesCount = document.getElementById("cynaris-count-courses");
                            if (coursesCount) coursesCount.innerText = data.courses.length;
                        }
                        if (data.students) {
                            var usersCount = document.getElementById("cynaris-count-users");
                            if (usersCount) usersCount.innerText = data.students.length;
                        }

                        // 2. Render / Update Bar Chart (Course Completion %)
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

                        // 3. Render / Update Line Chart (Login Frequency & Average Grade)
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

                        // Update timestamp
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

            // Bind click event
            document.addEventListener("DOMContentLoaded", function() {
                var btn = document.getElementById("btn-cynaris-refresh");
                if (btn) {
                    btn.addEventListener("click", fetchAndUpdateStats);
                }
                // Initial load
                fetchAndUpdateStats();
            });

            // Fallback load if DOM already ready
            if (document.readyState === "complete" || document.readyState === "interactive") {
                fetchAndUpdateStats();
            }
        })();
        </script>
        ';

        $this->content->text = $html;
        $this->content->footer = 'Cynaris Full Stack Dev • Week 5 Day 4';

        return $this->content;
    }
}
