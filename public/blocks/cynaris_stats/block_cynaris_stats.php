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
 * Cynaris Stats Block class definition.
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
     * Assemble and return block content.
     *
     * @return stdClass
     */
    public function get_content() {
        global $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Block presentation markup.
        $html = '';
        $html .= html_writer::start_div('cynaris-stats-container p-2');
        
        // Welcome and description.
        $html .= html_writer::tag('h6', get_string('cynaris_stats_welcome', 'block_cynaris_stats'), ['class' => 'font-weight-bold text-primary mb-2']);
        $html .= html_writer::tag('p', get_string('cynaris_stats_content', 'block_cynaris_stats'), ['class' => 'small text-muted mb-3']);

        // Stats summary cards.
        $html .= html_writer::start_div('row text-center mb-2');
        
        // Courses count card.
        $html .= html_writer::start_div('col-4 p-1');
        $html .= html_writer::start_div('border rounded p-2 bg-light');
        $html .= html_writer::tag('div', '2', ['class' => 'h5 mb-0 font-weight-bold text-dark']);
        $html .= html_writer::tag('small', get_string('total_courses', 'block_cynaris_stats'), ['class' => 'text-secondary d-block font-weight-semibold']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        // Learners count card.
        $html .= html_writer::start_div('col-4 p-1');
        $html .= html_writer::start_div('border rounded p-2 bg-light');
        $html .= html_writer::tag('div', '3', ['class' => 'h5 mb-0 font-weight-bold text-success']);
        $html .= html_writer::tag('small', get_string('total_users', 'block_cynaris_stats'), ['class' => 'text-secondary d-block font-weight-semibold']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        // Completions count card.
        $html .= html_writer::start_div('col-4 p-1');
        $html .= html_writer::start_div('border rounded p-2 bg-light');
        $html .= html_writer::tag('div', '2', ['class' => 'h5 mb-0 font-weight-bold text-info']);
        $html .= html_writer::tag('small', get_string('total_completions', 'block_cynaris_stats'), ['class' => 'text-secondary d-block font-weight-semibold']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= html_writer::end_div(); // End row

        // Health indicator badge.
        $html .= html_writer::start_div('alert alert-success py-1 px-2 small mb-0 text-center');
        $html .= html_writer::tag('span', '● ', ['class' => 'text-success mr-1']);
        $html .= html_writer::tag('strong', get_string('system_healthy', 'block_cynaris_stats'));
        $html .= html_writer::end_div();

        $html .= html_writer::end_div(); // End container

        $this->content->text = $html;
        $this->content->footer = 'Cynaris Full Stack Dev • Week 5 Day 3';

        return $this->content;
    }
}
