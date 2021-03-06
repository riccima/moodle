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
 * Utility class for browsing of course files.
 *
 * @package    core_files
 * @copyright  2008 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Represents a course context in the tree navigated by {@link file_browser}.
 *
 * @package    core_files
 * @copyright  2008 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_info_context_course extends file_info {
    /** @var stdClass course object */
    protected $course;

    /**
     * Constructor
     *
     * @param file_browser $browser file browser instance
     * @param stdClass $context context object
     * @param stdClass $course course object
     */
    public function __construct($browser, $context, $course) {
        parent::__construct($browser, $context);
        $this->course   = $course;
    }

    /**
     * Return information about this specific context level
     *
     * @param string $component component
     * @param string $filearea file area
     * @param int $itemid item ID
     * @param string $filepath file path
     * @param string $filename file name
     * @return file_info|null file_info instance or null if not found or access not allowed
     */
    public function get_file_info($component, $filearea, $itemid, $filepath, $filename) {
        // try to emulate require_login() tests here
        if (!isloggedin()) {
            return null;
        }

        if (!$this->course->visible and !has_capability('moodle/course:viewhiddencourses', $this->context)) {
            return null;
        }

        if (!is_viewing($this->context) and !is_enrolled($this->context)) {
            // no peaking here if not enrolled or inspector
            return null;
        }

        if (empty($component)) {
            return $this;
        }

        $methodname = "get_area_{$component}_{$filearea}";

        if (method_exists($this, $methodname)) {
            return $this->$methodname($itemid, $filepath, $filename);
        }

        return null;
    }

    /**
     * Gets a stored file for the course summary filearea directory
     *
     * @param int $itemid item ID
     * @param string $filepath file path
     * @param string $filename file name
     * @return file_info|null file_info instance or null if not found or access not allowed
     */
    protected function get_area_course_summary($itemid, $filepath, $filename) {
        global $CFG;

        if (!has_capability('moodle/course:update', $this->context)) {
            return null;
        }
        if (is_null($itemid)) {
            return $this;
        }

        $fs = get_file_storage();

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;
        if (!$storedfile = $fs->get_file($this->context->id, 'course', 'summary', 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($this->context->id, 'course', 'summary', 0);
            } else {
                // not found
                return null;
            }
        }
        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        return new file_info_stored($this->browser, $this->context, $storedfile, $urlbase, get_string('areacourseintro', 'repository'), false, true, true, false);
    }

    /**
     * Gets a stored file for the course section filearea directory
     *
     * @param int $itemid item ID
     * @param string $filepath file path
     * @param string $filename file name
     * @return file_info|null file_info instance or null if not found or access not allowed
     */
    protected function get_area_course_section($itemid, $filepath, $filename) {
        global $CFG, $DB;

        if (!has_capability('moodle/course:update', $this->context)) {
            return null;
        }

        if (empty($itemid)) {
            // list all sections
            return new file_info_area_course_section($this->browser, $this->context, $this->course, $this);
        }

        if (!$section = $DB->get_record('course_sections', array('course'=>$this->course->id, 'id'=>$itemid))) {
            return null; // does not exist
        }

        $fs = get_file_storage();

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;
        if (!$storedfile = $fs->get_file($this->context->id, 'course', 'section', $itemid, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($this->context->id, 'course', 'section', $itemid);
            } else {
                // not found
                return null;
            }
        }
        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        return new file_info_stored($this->browser, $this->context, $storedfile, $urlbase, $section->section, true, true, true, false);
    }

    /**
     * Gets a stored file for the course legacy filearea directory
     *
     * @param int $itemid item ID
     * @param string $filepath file path
     * @param string $filename file name
     * @return file_info|null file_info instance or null if not found or access not allowed
     */
    protected function get_area_course_legacy($itemid, $filepath, $filename) {
        if (!has_capability('moodle/course:managefiles', $this->context)) {
            return null;
        }

        if ($this->course->id != SITEID and $this->course->legacyfiles != 2) {
            // bad luck, legacy course files not used any more
        }

        if (is_null($itemid)) {
            return $this;
        }

        $fs = get_file_storage();

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;
        if (!$storedfile = $fs->get_file($this->context->id, 'course', 'legacy', 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($this->context->id, 'course', 'legacy', 0);
            } else {
                // not found
                return null;
            }
        }

        return new file_info_area_course_legacy($this->browser, $this->context, $storedfile);
    }

    /**
     * Gets a stored file for the backup course filearea directory
     *
     * @param int $itemid item ID
     * @param string $filepath file path
     * @param string $filename file name
     * @return file_info|null file_info instance or null if not found or access not allowed
     */
    protected function get_area_backup_course($itemid, $filepath, $filename) {
        global $CFG;

        if (!has_capability('moodle/backup:backupcourse', $this->context) and !has_capability('moodle/restore:restorecourse', $this->context)) {
            return null;
        }
        if (is_null($itemid)) {
            return $this;
        }

        $fs = get_file_storage();

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;
        if (!$storedfile = $fs->get_file($this->context->id, 'backup', 'course', 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($this->context->id, 'backup', 'course', 0);
            } else {
                // not found
                return null;
            }
        }

        $downloadable = has_capability('moodle/backup:downloadfile', $this->context);
        $uploadable   = has_capability('moodle/restore:uploadfile', $this->context);

        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        return new file_info_stored($this->browser, $this->context, $storedfile, $urlbase, get_string('coursebackup', 'repository'), false, $downloadable, $uploadable, false);
    }

    /**
     * Gets a stored file for the automated backup filearea directory
     *
     * @param int $itemid item ID
     * @param string $filepath file path
     * @param string $filename file name
     * @return file_info|null
     */
    protected function get_area_backup_automated($itemid, $filepath, $filename) {
        global $CFG;

        if (!has_capability('moodle/restore:viewautomatedfilearea', $this->context)) {
            return null;
        }
        if (is_null($itemid)) {
            return $this;
        }

        $fs = get_file_storage();

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;
        if (!$storedfile = $fs->get_file($this->context->id, 'backup', 'automated', 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($this->context->id, 'backup', 'automated', 0);
            } else {
                // not found
                return null;
            }
        }

        $downloadable = has_capability('moodle/site:config', $this->context);
        $uploadable   = false;

        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        return new file_info_stored($this->browser, $this->context, $storedfile, $urlbase, get_string('automatedbackup', 'repository'), true, $downloadable, $uploadable, false);
    }

    /**
     * Gets a stored file for the backup section filearea directory
     *
     * @param int $itemid item ID
     * @param string $filepath file path
     * @param string $filename file name
     * @return file_info|null file_info instance or null if not found or access not allowed
     */
    protected function get_area_backup_section($itemid, $filepath, $filename) {
        global $CFG, $DB;

        if (!has_capability('moodle/backup:backupcourse', $this->context) and !has_capability('moodle/restore:restorecourse', $this->context)) {
            return null;
        }

        if (empty($itemid)) {
            // list all sections
            return new file_info_area_backup_section($this->browser, $this->context, $this->course, $this);
        }

        if (!$section = $DB->get_record('course_sections', array('course'=>$this->course->id, 'id'=>$itemid))) {
            return null; // does not exist
        }

        $fs = get_file_storage();

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;
        if (!$storedfile = $fs->get_file($this->context->id, 'backup', 'section', $itemid, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($this->context->id, 'backup', 'section', $itemid);
            } else {
                // not found
                return null;
            }
        }

        $downloadable = has_capability('moodle/backup:downloadfile', $this->context);
        $uploadable   = has_capability('moodle/restore:uploadfile', $this->context);

        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        return new file_info_stored($this->browser, $this->context, $storedfile, $urlbase, $section->id, true, $downloadable, $uploadable, false);
    }

    /**
     * Returns localised visible name.
     *
     * @return string
     */
    public function get_visible_name() {
        return ($this->course->id == SITEID) ? get_string('frontpage', 'admin') : format_string($this->course->fullname, true, array('context'=>$this->context));
    }

    /**
     * Whether or not new files or directories can be added
     *
     * @return bool
     */
    public function is_writable() {
        return false;
    }

    /**
     * Whether or not this is a directory
     *
     * @return bool
     */
    public function is_directory() {
        return true;
    }

    /**
     * Returns list of children.
     *
     * @return array of file_info instances
     */
    public function get_children() {
        $children = array();

        if ($child = $this->get_area_course_summary(0, '/', '.')) {
            $children[] = $child;
        }
        if ($child = $this->get_area_course_section(null, null, null)) {
            $children[] = $child;
        }
        if ($child = $this->get_area_backup_section(null, null, null)) {
            $children[] = $child;
        }
        if ($child = $this->get_area_backup_course(0, '/', '.')) {
            $children[] = $child;
        }
        if ($child = $this->get_area_backup_automated(0, '/', '.')) {
            $children[] = $child;
        }
        if ($child = $this->get_area_course_legacy(0, '/', '.')) {
            $children[] = $child;
        }

        if (!has_capability('moodle/course:managefiles', $this->context)) {
            // 'managefiles' capability is checked in every activity module callback.
            // Don't even waste time on retrieving the modules if we can't browse the files anyway
            return $children;
        }

        // now list all modules
        $modinfo = get_fast_modinfo($this->course);
        foreach ($modinfo->cms as $cminfo) {
            if (empty($cminfo->uservisible)) {
                continue;
            }
            $modcontext = context_module::instance($cminfo->id, IGNORE_MISSING);
            if ($child = $this->browser->get_file_info($modcontext)) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * Returns parent file_info instance
     *
     * @todo error checking if get_parent_contextid() returns false
     * @return file_info or null for root
     */
    public function get_parent() {
        //TODO: error checking if get_parent_contextid() returns false
        $pcid = get_parent_contextid($this->context);
        $parent = context::instance_by_id($pcid, IGNORE_MISSING);
        return $this->browser->get_file_info($parent);
    }
}


/**
 * Subclass of file_info_stored for files in the course files area.
 *
 * @package   core_files
 * @copyright 2008 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_info_area_course_legacy extends file_info_stored {
    /**
     * Constructor
     *
     * @param file_browser $browser file browser instance
     * @param stdClass $context context object
     * @param stored_file $storedfile stored_file instance
     */
    public function __construct($browser, $context, $storedfile) {
        global $CFG;
        $urlbase = $CFG->wwwroot.'/file.php';
        parent::__construct($browser, $context, $storedfile, $urlbase, get_string('coursefiles'), false, true, true, false);
    }

    /**
     * Returns file download url
     *
     * @param bool $forcedownload whether or not force download
     * @param bool $https whether or not force https
     * @return string url
     */
    public function get_url($forcedownload=false, $https=false) {
        if (!$this->is_readable()) {
            return null;
        }

        if ($this->lf->is_directory()) {
            return null;
        }

        $filepath = $this->lf->get_filepath();
        $filename = $this->lf->get_filename();
        $courseid = $this->context->instanceid;

        $path = '/'.$courseid.$filepath.$filename;

        return file_encode_url($this->urlbase, $path, $forcedownload, $https);
    }

    /**
     * Returns list of children.
     *
     * @return array of file_info instances
     */
    public function get_children() {
        if (!$this->lf->is_directory()) {
            return array();
        }

        $result = array();
        $fs = get_file_storage();

        $storedfiles = $fs->get_directory_files($this->context->id, 'course', 'legacy', 0, $this->lf->get_filepath(), false, true, "filepath ASC, filename ASC");
        foreach ($storedfiles as $file) {
            $result[] = new file_info_area_course_legacy($this->browser, $this->context, $file);
        }

        return $result;
    }
}

/**
 * Represents a course category context in the tree navigated by {@link file_browser}.
 *
 * @package    core_files
 * @copyright  2008 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_info_area_course_section extends file_info {
    /** @var stdClass course object */
    protected $course;
    /** @var file_info_context_course course file info object */
    protected $courseinfo;

    /**
     * Constructor
     *
     * @param file_browser $browser file browser instance
     * @param stdClass $context context object
     * @param stdClass $course course object
     * @param file_info_context_course $courseinfo file info instance
     */
    public function __construct($browser, $context, $course, file_info_context_course $courseinfo) {
        parent::__construct($browser, $context);
        $this->course     = $course;
        $this->courseinfo = $courseinfo;
    }

    /**
     * Returns list of standard virtual file/directory identification.
     * The difference from stored_file parameters is that null values
     * are allowed in all fields
     *
     * @return array with keys contextid, filearea, itemid, filepath and filename
     */
    public function get_params() {
        return array('contextid' => $this->context->id,
                     'component' => 'course',
                     'filearea'  => 'section',
                     'itemid'    => null,
                     'filepath'  => null,
                     'filename'  => null);
    }

    /**
     * Returns localised visible name.
     *
     * @return string
     */
    public function get_visible_name() {
        //$format = $this->course->format;
        $sectionsname = get_string("coursesectionsummaries");

        return $sectionsname;
    }

    /**
     * Return whether or not new files or directories can be added
     *
     * @return bool
     */
    public function is_writable() {
        return false;
    }

    /**
     * Return whether or not this is a empty area
     *
     * @return bool
     */
    public function is_empty_area() {
        $fs = get_file_storage();
        return $fs->is_area_empty($this->context->id, 'course', 'section');
    }

    /**
     * Return whether or not this is a empty area
     *
     * @return bool
     */
    public function is_directory() {
        return true;
    }

    /**
     * Returns list of children.
     *
     * @return array of file_info instances
     */
    public function get_children() {
        global $DB;

        $children = array();

        $course_sections = $DB->get_records('course_sections', array('course'=>$this->course->id), 'section');
        foreach ($course_sections as $section) {
            if ($child = $this->courseinfo->get_file_info('course', 'section', $section->id, '/', '.')) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * Returns parent file_info instance
     *
     * @return file_info|null file_info or null for root
     */
    public function get_parent() {
        return $this->courseinfo;
    }
}


/**
 * Implementation of course section backup area
 *
 * @package    core_files
 * @copyright  2008 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_info_area_backup_section extends file_info {
    /** @var stdClass course object */
    protected $course;
    /** @var file_info_context_course course file info object */
    protected $courseinfo;

    /**
     * Constructor
     *
     * @param file_browser $browser file browser instance
     * @param stdClass $context context object
     * @param stdClass $course course object
     * @param file_info_context_course $courseinfo file info instance
     */
    public function __construct($browser, $context, $course, file_info_context_course $courseinfo) {
        parent::__construct($browser, $context);
        $this->course     = $course;
        $this->courseinfo = $courseinfo;
    }

    /**
     * Returns list of standard virtual file/directory identification.
     * The difference from stored_file parameters is that null values
     * are allowed in all fields
     *
     * @return array with keys contextid, component, filearea, itemid, filepath and filename
     */
    public function get_params() {
        return array('contextid' => $this->context->id,
                     'component' => 'backup',
                     'filearea'  => 'section',
                     'itemid'    => null,
                     'filepath'  => null,
                     'filename'  => null);
    }

    /**
     * Returns localised visible name.
     *
     * @return string
     */
    public function get_visible_name() {
        return get_string('sectionbackup', 'repository');
    }

    /**
     * Return whether or not new files and directories can be added
     *
     * @return bool
     */
    public function is_writable() {
        return false;
    }

    /**
     * Whether or not this is an empty area
     *
     * @return bool
     */
    public function is_empty_area() {
        $fs = get_file_storage();
        return $fs->is_area_empty($this->context->id, 'backup', 'section');
    }

    /**
     * Return whether or not this is a directory
     *
     * @return bool
     */
    public function is_directory() {
        return true;
    }

    /**
     * Returns list of children.
     *
     * @return array of file_info instances
     */
    public function get_children() {
        global $DB;

        $children = array();

        $course_sections = $DB->get_records('course_sections', array('course'=>$this->course->id), 'section');
        foreach ($course_sections as $section) {
            if ($child = $this->courseinfo->get_file_info('backup', 'section', $section->id, '/', '.')) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * Returns parent file_info instance
     *
     * @return file_info or null for root
     */
    public function get_parent() {
        return $this->browser->get_file_info($this->context);
    }
}
