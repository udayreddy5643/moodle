# Week 5 Day 3: Moodle Block Plugin Scaffold — `block_cynaris_stats`

## 1. Overview & Architectural Role

- **Plugin Name**: Cynaris Stats
- **Frankenstyle Component Identifier**: `block_cynaris_stats`
- **Plugin Type**: `block` (pluggable UI sidebar / dashboard widget)
- **Directory Path**: `blocks/cynaris_stats/` (mapped to `public/blocks/cynaris_stats/`)
- **Git Branch**: `feat/cynaris-w5d3-uday-b`
- **Target Environments**: User Dashboard (`my-index`), Course Views (`course-view`), and Site Home (`site-index`).

---

## 2. Scaffolded File Structure

```
blocks/cynaris_stats/
├── block_cynaris_stats.php    # Core block class inheriting from block_base
├── version.php                # Plugin metadata, release version, and Moodle requirements
├── db/
│   └── access.php             # Capability definitions (addinstance & myaddinstance)
└── lang/
    └── en/
        └── block_cynaris_stats.php # Localized English language string definitions
```

---

## 3. Core Class Implementation (`block_cynaris_stats.php`)

```php
<?php
defined('MOODLE_INTERNAL') || die();

class block_cynaris_stats extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_cynaris_stats');
    }

    public function applicable_formats() {
        return [
            'all' => true,
            'my' => true,
            'course-view' => true,
            'site' => true,
        ];
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function get_content() {
        global $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Formatted presentation markup
        $html = '';
        $html .= html_writer::start_div('cynaris-stats-container p-2');
        $html .= html_writer::tag('h6', get_string('cynaris_stats_welcome', 'block_cynaris_stats'), ['class' => 'font-weight-bold text-primary mb-2']);
        $html .= html_writer::tag('p', get_string('cynaris_stats_content', 'block_cynaris_stats'), ['class' => 'small text-muted mb-3']);

        // Stats summary cards
        $html .= html_writer::start_div('row text-center mb-2');
        $html .= html_writer::start_div('col-4 p-1');
        $html .= html_writer::start_div('border rounded p-2 bg-light');
        $html .= html_writer::tag('div', '2', ['class' => 'h5 mb-0 font-weight-bold text-dark']);
        $html .= html_writer::tag('small', get_string('total_courses', 'block_cynaris_stats'), ['class' => 'text-secondary d-block font-weight-semibold']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('col-4 p-1');
        $html .= html_writer::start_div('border rounded p-2 bg-light');
        $html .= html_writer::tag('div', '3', ['class' => 'h5 mb-0 font-weight-bold text-success']);
        $html .= html_writer::tag('small', get_string('total_users', 'block_cynaris_stats'), ['class' => 'text-secondary d-block font-weight-semibold']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('col-4 p-1');
        $html .= html_writer::start_div('border rounded p-2 bg-light');
        $html .= html_writer::tag('div', '2', ['class' => 'h5 mb-0 font-weight-bold text-info']);
        $html .= html_writer::tag('small', get_string('total_completions', 'block_cynaris_stats'), ['class' => 'text-secondary d-block font-weight-semibold']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        // Operational health badge
        $html .= html_writer::start_div('alert alert-success py-1 px-2 small mb-0 text-center');
        $html .= html_writer::tag('span', '● ', ['class' => 'text-success mr-1']);
        $html .= html_writer::tag('strong', get_string('system_healthy', 'block_cynaris_stats'));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $this->content->text = $html;
        $this->content->footer = 'Cynaris Full Stack Dev • Week 5 Day 3';

        return $this->content;
    }
}
```

---

## 4. Verification Evidence

### CLI Installation Output:
```text
-->block_cynaris_stats
++ insert_record: Success (1.60 seconds) ++
++ update_capabilities: Success (9.25 seconds) ++
++ Success (12.37 seconds) ++
```

### Database Verification:
```sql
SELECT id, name, visible FROM m_block WHERE name = 'cynaris_stats';
-- Result: id = 41, name = 'cynaris_stats', visible = 1

SELECT * FROM m_config_plugins WHERE plugin = 'block_cynaris_stats';
-- Result: plugin = 'block_cynaris_stats', name = 'version', value = '2026110500'

SELECT * FROM m_block_instances WHERE blockname = 'cynaris_stats';
-- Result: id = 7, pagetypepattern = 'my-index', defaultregion = 'content', defaultweight = -1
```
