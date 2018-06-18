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
 * Renderer for outputting the collapsibleweeks course format.
 *
 * @package format_collapsibleweeks
 * @copyright 2018 - Cellule TICE - Unversite de Namur
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');
require_once($CFG->dirroot.'/course/format/collapsibleweeks/lib.php');


/**
 * Basic renderer for collapsibletopics format.
 *
 * @package    format_collapsibleweeks
 * @copyright  2018 - Cellule TICE - Unversite de Namur
 * @copyright  2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_collapsibleweeks_renderer extends format_section_renderer_base {

    /**
     * @var bool flag allowing to adapt display for legacy themes (based on bootstrapbase)
     */
    protected $legacytheme;

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        if (in_array('bootstrapbase', $page->theme->parents)) {
            $this->legacytheme = true;
        } else {
            $this->legacytheme = false;
        }
    }

    /**
     * Overrides format_section_renderer_base implementation.
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        global $PAGE;

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        // Now the list of sections..
        echo $this->start_section_list();
        $numsections = course_get_format($course)->get_last_section_number();

        foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            if ($section == 0) {
                // 0-section is displayed a little different then the others.
                if ($thissection->summary or !empty($modinfo->sections[0]) or $PAGE->user_is_editing()) {
                    $this->page->requires->strings_for_js(array('collapseall', 'expandall'), 'moodle');
                    $modules = $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->section_header($thissection, $course, false, 0);
                    echo $modules;
                    echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                    echo '<div class="collapsible-actions" >
    <a href="#" class="expandall" role="button">' . get_string('expandall') . '
    </a>
</div>';
                    echo $this->section_footer();
                }
                continue;
            }
            if ($section > $numsections) {
                // Activities inside this section are 'orphaned', this section will be printed as 'stealth' below.
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display.
            $showsection = $thissection->uservisible ||
                ($thissection->visible && !$thissection->available &&
                    !empty($thissection->availableinfo));
            if (!$showsection) {
                // If the hiddensections option is set to 'show hidden sections in collapsed
                // form', then display the hidden section message - UNLESS the section is
                // hidden by the availability system, which is set to hide the reason.
                if (!$course->hiddensections && $thissection->available) {
                    echo $this->section_hidden($section, $course->id);
                }

                continue;
            }

            if ($thissection->uservisible) {
                $modules = $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                $control = $this->courserenderer->course_section_add_cm_control($course, $section, 0);
                echo $this->section_header($thissection, $course, false, 0);
                echo $modules;
                echo $control;
                echo $this->section_footer();
            }

        }

        if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $numsections or empty($modinfo->sections[$section])) {
                    // This is not stealth section or it is empty.
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();

            echo $this->change_number_sections($course, 0);
        } else {
            echo $this->end_section_list();
        }
    }

    /**
     * Generate the starting container html for a list of sections overrides format_section_renderer_base
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'accordion collapsibleweeks', 'aria-multiselectable' => true));
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('weeklyoutline');
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }

    /**
     * Overrides format_section_renderer_base
     * Generate the display of the header part of a section before
     * course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a single-section page
     * @param int $sectionreturn The section to return to after an action
     * @return string HTML to output.
     */
    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null) {
        global $PAGE;

        $o = '';
        $currenttext = '';
        $sectionstyle = '';

        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle = ' hidden';
            } else if (course_get_format($course)->is_section_current($section)) {
                $sectionstyle = ' current';
            }
        }

        $o .= html_writer::start_tag('li', array('id' => 'section-' . $section->section,
            'class' => 'section main clearfix' . $sectionstyle, 'role' => 'region',
            'aria-label' => get_section_name($course, $section)));

        // Create a span that contains the section title to be used to create the keyboard section move menu.
        $o .= html_writer::tag('span', get_section_name($course, $section), array('class' => 'hidden sectionname'));
        $extraclass = $this->legacytheme ? ' legacy' : '';
        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $o .= html_writer::tag('div', $leftcontent, array('class' => 'left side' . $extraclass));

        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $o .= html_writer::tag('div', $rightcontent, array('class' => 'right side'));
        $o .= html_writer::start_tag('div', array('class' => 'content'));

        // When not on a section page, we display the section titles except the general section if null.
        $hasnamenotsecpg = (!$onsectionpage && ($section->section != 0 || !is_null($section->name)));

        // When on a section page, we only display the general section title, if title is not the default one.
        $hasnamesecpg = ($onsectionpage && ($section->section == 0 && !is_null($section->name)));

        $classes = ' accesshide';
        if ($hasnamenotsecpg || $hasnamesecpg) {
            $classes = '';
        }
        if (!$PAGE->user_is_editing()) {
            $sectionname = html_writer::tag('span', $this->section_title_without_link($section, $course),
                array('class' => 'sectionname'));
            // Add collapse toggle.
            $extraclass = $this->legacytheme ? ' legacy' : '';
            if (course_get_format($course)->is_section_current($section)) {
                $o .= '<a class="sectiontoggle' . $extraclass .
                    '" data-toggle="collapse" data-parent="accordion" href="#collapse-' .
                    $section->section .
                    '" aria-expanded="true" aria-controls="collapse-' .
                    $section->section .
                    '">&nbsp;' . $sectionname . '</a> ';
            } else if ($section->section != 0) {
                $o .= '<a class="sectiontoggle collapsed' . $extraclass .
                    '" data-toggle="collapse" data-parent="accordion" href="#collapse-' .
                    $section->section .
                    '" aria-expanded="false" aria-controls="collapse-' .
                    $section->section .
                    '">&nbsp;' . $sectionname .
                    '</a> ';
            }
            // End collapse toggle.

            $o .= '<div class="clearfix">';
            $o .= $this->section_availability($section) . '</div>';
            $o .= $this->section_summary($section, $course, null);

        } else {
            $sectionname = html_writer::tag('span', $this->section_title_without_link($section, $course));

            // Add collapse toggle.
            $extraclass = $this->legacytheme ? ' legacy' : '';
            if (course_get_format($course)->is_section_current($section)) {
                $o .= '<a class="sectiontoggle' . $extraclass .
                    '" data-toggle="collapse" data-parent="accordion" href="#collapse-' .
                    $section->section .
                    '" aria-expanded="true" aria-controls="collapse-' .
                    $section->section .
                    '">&nbsp;</a> ';
            } else if ($section->section != 0) {
                $o .= '<a class="sectiontoggle collapsed' . $extraclass .
                    '" data-toggle="collapse" data-parent="accordion" href="#collapse-' .
                    $section->section .
                    '" aria-expanded="false" aria-controls="collapse-' .
                    $section->section .
                    '">&nbsp;</a> ';
            }
            // End collapse toggle.

            $o .= '<div class="clearfix">' . $this->output->heading($sectionname, 3, 'sectionname' . $classes);
            $o .= $this->section_availability($section) . '</div>';
            $o .= $this->section_summary($section, $course, null);
        }
        if (course_get_format($course)->is_section_current($section)) {
            $classes = 'collapse show';
        } else if ($section->section != 0) {
            $classes = 'collapse';
        } else {
            $classes = '';
        }
        $o .= '<div id="collapse-' .
            $section->section .
            '" class="' .
            $classes .
            '" role="tabpanel" aria-labelledby="heading' .
            $section->section .
            '">';

        return $o;
    }

    /**
     * Generate the display of the footer part of a section
     *
     * @return string HTML to output.
     */
    protected function section_footer() {
        // Collapsing course formats need an extra div surrounding content to allow section collapsing.
        $o = html_writer::end_tag('div');
        $o .= parent::section_footer();

        return $o;
    }

    /**
     * Override to add spacer into current section too.
     * Generate the content to displayed on the left part of a section
     * before course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return string HTML to output.
     */
    protected function section_left_content($section, $course, $onsectionpage) {
        $o = $this->output->spacer();

        if ($section->section != 0) {
            // Only in the non-general sections.
            if (course_get_format($course)->is_section_current($section)) {
                $o .= get_accesshide(get_string('currentsection', 'format_'.$course->format));
            }
        }

        return $o;
    }

    /**
     * Override to separate section summary from section name.
     * @param stdClass $section
     * @param stdClass $course
     * @param array $mods
     * @return string
     */
    protected function section_summary($section, $course, $mods) {
        $o = '';
        $o .= html_writer::start_tag('div', array('class' => 'summarytext'));
        $o .= $this->format_summary_text($section);
        $o .= html_writer::end_tag('div');
        $o .= $this->section_activity_summary($section, $course, null);

        return $o;
    }
}