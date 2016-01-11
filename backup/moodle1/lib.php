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
 * Provides support for the conversion of moodle1 backup to the moodle2 format
 *
 * @package    mod
 * @subpackage workshopplus
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * workshopplus conversion handler
 */
class moodle1_mod_workshopplus_handler extends moodle1_mod_handler {

    /** @var array the temporary in-memory cache for the current <MOD> contents */
    protected $currentworkshopplus = null;

    /** @var array in-memory cache for the course module information for the current workshopplus  */
    protected $currentcminfo = null;

    /** @var array the mapping of legacy elementno => newelementid for the current workshopplus */
    protected $newelementids = array();

    /** @var moodle1_file_manager for the current workshopplus */
    protected $fileman = null;

    /** @var moodle1_inforef_manager */
    protected $inforefman = null;

    /** @var array list of moodle1_workshopplusform_handler instances */
    private $strategyhandlers = null;

    /** @var int parent id for the rubric level */
    private $currentelementid = null;

    /**
     * Declare the paths in moodle.xml we are able to convert
     *
     * The method returns list of {@link convert_path} instances. For each path returned,
     * at least one of on_xxx_start(), process_xxx() and on_xxx_end() methods must be
     * defined. The method process_xxx() is not executed if the associated path element is
     * empty (i.e. it contains none elements or sub-paths only).
     *
     * Note that the path /MOODLE_BACKUP/COURSE/MODULES/MOD/workshopplus does not
     * actually exist in the file. The last element with the module name was
     * appended by the moodle1_converter class.
     *
     * @return array of {@link convert_path} instances
     */
    public function get_paths() {
        return array(
            new convert_path('workshopplus', '/MOODLE_BACKUP/COURSE/MODULES/MOD/workshopplus'),
            new convert_path('workshopplus_elements', '/MOODLE_BACKUP/COURSE/MODULES/MOD/workshopplus/ELEMENTS'),
            new convert_path(
                'workshopplus_element', '/MOODLE_BACKUP/COURSE/MODULES/MOD/workshopplus/ELEMENTS/ELEMENT',
                array(
                    'dropfields' => array(
                        'stddev',
                        'totalassessments',
                    ),
                )
            ),
            new convert_path('workshopplus_element_rubric', '/MOODLE_BACKUP/COURSE/MODULES/MOD/workshopplus/ELEMENTS/ELEMENT/RUBRICS/RUBRIC'),
        );
    }

    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/workshopplus
     * data available
     */
    public function process_workshopplus($data, $raw) {

        // re-use the upgrade function to convert workshopplus record
        $fakerecord = (object)$data;
        $fakerecord->course = 12345678;
        $this->currentworkshopplus = (array)workshopplus_upgrade_transform_instance($fakerecord);
        unset($this->currentworkshopplus['course']);

        // add the new fields with the default values
        $this->currentworkshopplus['id']                        = $data['id'];
        $this->currentworkshopplus['evaluation']                = 'best';
        $this->currentworkshopplus['examplesmode']              = workshopplus::EXAMPLES_VOLUNTARY;
        $this->currentworkshopplus['gradedecimals']             = 0;
        $this->currentworkshopplus['instructauthors']           = '';
        $this->currentworkshopplus['instructauthorsformat']     = FORMAT_HTML;
        $this->currentworkshopplus['instructreviewers']         = '';
        $this->currentworkshopplus['instructreviewersformat']   = FORMAT_HTML;
        $this->currentworkshopplus['latesubmissions']           = 0;
        $this->currentworkshopplus['conclusion']                = '';
        $this->currentworkshopplus['conclusionformat']          = FORMAT_HTML;

        foreach (array('submissionend', 'submissionstart', 'assessmentend', 'assessmentstart') as $field) {
            if (!array_key_exists($field, $this->currentworkshopplus)) {
                $this->currentworkshopplus[$field] = null;
            }
        }

        // get the course module id and context id
        $instanceid          = $data['id'];
        $this->currentcminfo = $this->get_cminfo($instanceid);
        $moduleid            = $this->currentcminfo['id'];
        $contextid           = $this->converter->get_contextid(CONTEXT_MODULE, $moduleid);

        // get a fresh new inforef manager for this instance
        $this->inforefman = $this->converter->get_inforef_manager('activity', $moduleid);

        // get a fresh new file manager for this instance
        $this->fileman = $this->converter->get_file_manager($contextid, 'mod_workshopplus');

        // convert course files embedded into the intro
        $this->fileman->filearea = 'intro';
        $this->fileman->itemid   = 0;
        $this->currentworkshopplus['intro'] = moodle1_converter::migrate_referenced_files($this->currentworkshopplus['intro'], $this->fileman);

        // write workshopplus.xml
        $this->open_xml_writer("activities/workshopplus_{$moduleid}/workshopplus.xml");
        $this->xmlwriter->begin_tag('activity', array('id' => $instanceid, 'moduleid' => $moduleid,
            'modulename' => 'workshopplus', 'contextid' => $contextid));
        $this->xmlwriter->begin_tag('workshopplus', array('id' => $instanceid));

        foreach ($this->currentworkshopplus as $field => $value) {
            if ($field <> 'id') {
                $this->xmlwriter->full_tag($field, $value);
            }
        }

        return $this->currentworkshopplus;
    }

    /**
     * This is executed when the parser reaches <ELEMENTS>
     *
     * The dimensions definition follows. One of the grading strategy subplugins
     * will append dimensions data in {@link self::process_workshopplus_element()}
     */
    public function on_workshopplus_elements_start() {

        $this->xmlwriter->begin_tag('subplugin_workshopplusform_'.$this->currentworkshopplus['strategy'].'_workshopplus');

        // inform the strategy handler that a new workshopplus instance is being processed
        $handler = $this->get_strategy_handler($this->currentworkshopplus['strategy']);
        $handler->use_xml_writer($this->xmlwriter);
        $handler->on_elements_start();
    }

    /**
     * Processes one <ELEMENT> tag from moodle.xml
     */
    public function process_workshopplus_element($data, $raw) {

        // generate artificial element id and remember it for later usage
        $data['id'] = $this->converter->get_nextid();
        $this->currentelementid = $data['id'];
        $this->newelementids[$data['elementno']] = $data['id'];

        // let the strategy subplugin do whatever it needs to
        $handler = $this->get_strategy_handler($this->currentworkshopplus['strategy']);
        return $handler->process_legacy_element($data, $raw);
    }

    /**
     * Processes one <RUBRIC> tag from moodle.xml
     */
    public function process_workshopplus_element_rubric($data, $raw) {
        if ($this->currentworkshopplus['strategy'] == 'rubric') {
            $handler = $this->get_strategy_handler('rubric');
            $data['elementid'] = $this->currentelementid;
            $handler->process_legacy_rubric($data, $raw);
        }
    }

    /**
     * This is executed when the parser reaches </ELEMENT>
     */
    public function on_workshopplus_element_end() {
        // give the strategy handlers a chance to write what they need
        $handler = $this->get_strategy_handler($this->currentworkshopplus['strategy']);
        $handler->on_legacy_element_end();
    }

    /**
     * This is executed when the parser reaches </ELEMENTS>
     */
    public function on_workshopplus_elements_end() {
        // give the strategy hanlders last chance to write what they need
        $handler = $this->get_strategy_handler($this->currentworkshopplus['strategy']);
        $handler->on_elements_end();

        // close the dimensions definition
        $this->xmlwriter->end_tag('subplugin_workshopplusform_'.$this->currentworkshopplus['strategy'].'_workshopplus');

        // as a temporary hack, we just write empty wrappers for the rest of data
        $this->write_xml('examplesubmissions', array());
        $this->write_xml('submissions', array());
        $this->write_xml('aggregations', array());
    }

    /**
     * This is executed when the parser reaches </MOD>
     */
    public function on_workshopplus_end() {
        // close workshopplus.xml
        $this->xmlwriter->end_tag('workshopplus');
        $this->xmlwriter->end_tag('activity');
        $this->close_xml_writer();

        // write inforef.xml
        $this->inforefman->add_refs('file', $this->fileman->get_fileids());
        $moduleid = $this->currentcminfo['id'];
        $this->open_xml_writer("activities/workshopplus_{$moduleid}/inforef.xml");
        $this->inforefman->write_refs($this->xmlwriter);
        $this->close_xml_writer();

        // get ready for the next instance
        $this->currentworkshopplus = null;
        $this->currentcminfo   = null;
        $this->newelementids   = array();
    }

    /**
     * Provides access to the current <workshopplus> data
     *
     * @return array|null
     */
    public function get_current_workshopplus() {
        return $this->currentworkshopplus;
    }

    /**
     * Provides access to the instance's inforef manager
     *
     * @return moodle1_inforef_manager
     */
    public function get_inforef_manager() {
        return $this->inforefman;
    }

    /// internal implementation details follow /////////////////////////////////

    /**
     * Factory method returning the handler of the given grading strategy subplugin
     *
     * @param string $strategy the name of the grading strategy
     * @throws moodle1_convert_exception
     * @return moodle1_workshopplusform_handler the instance of the handler
     */
    protected function get_strategy_handler($strategy) {
        global $CFG; // we include other files here

        if (is_null($this->strategyhandlers)) {
            $this->strategyhandlers = array();
            $subplugins = core_component::get_plugin_list('workshopplusform');
            foreach ($subplugins as $name => $dir) {
                $handlerfile  = $dir.'/backup/moodle1/lib.php';
                $handlerclass = "moodle1_workshopplusform_{$name}_handler";
                if (!file_exists($handlerfile)) {
                    continue;
                }
                require_once($handlerfile);

                if (!class_exists($handlerclass)) {
                    throw new moodle1_convert_exception('missing_handler_class', $handlerclass);
                }
                $this->log('preparing workshopplus grading strategy handler', backup::LOG_DEBUG, $handlerclass);
                $this->strategyhandlers[$name] = new $handlerclass($this, $name);
                if (!$this->strategyhandlers[$name] instanceof moodle1_workshopplusform_handler) {
                    throw new moodle1_convert_exception('wrong_handler_class', get_class($this->strategyhandlers[$name]));
                }
            }
        }

        if (!isset($this->strategyhandlers[$strategy])) {
            throw new moodle1_convert_exception('usupported_subplugin', 'workshopplusform_'.$strategy);
        }

        return $this->strategyhandlers[$strategy];
    }
}


/**
 * Base class for the grading strategy subplugin handler
 */
abstract class moodle1_workshopplusform_handler extends moodle1_submod_handler {

    /**
     * @param moodle1_mod_handler $workshopplushandler the handler of a module we are subplugin of
     * @param string $subpluginname the name of the subplugin
     */
    public function __construct(moodle1_mod_handler $workshopplushandler, $subpluginname) {
        parent::__construct($workshopplushandler, 'workshopplusform', $subpluginname);
    }

    /**
     * Provides a xml_writer instance to this workshopplusform handler
     *
     * @param xml_writer $xmlwriter
     */
    public function use_xml_writer(xml_writer $xmlwriter) {
        $this->xmlwriter = $xmlwriter;
    }

    /**
     * Called when we reach <ELEMENTS>
     *
     * Gives the handler a chance to prepare for a new workshopplus instance
     */
    public function on_elements_start() {
        // do nothing by default
    }

    /**
     * Called everytime when legacy <ELEMENT> data are available
     *
     * @param array $data legacy element data
     * @param array $raw raw element data
     *
     * @return array converted
     */
    public function process_legacy_element(array $data, array $raw) {
        return $data;
    }

    /**
     * Called when we reach </ELEMENT>
     */
    public function on_legacy_element_end() {
        // do nothing by default
    }

    /**
     * Called when we reach </ELEMENTS>
     */
    public function on_elements_end() {
        // do nothing by default
    }
}

/**
 * Given a record containing data from 1.9 workshopplus table, returns object containing data as should be saved in 2.0 workshopplus table
 *
 * @param stdClass $old record from 1.9 workshopplus table
 * @return stdClass
 */
function workshopplus_upgrade_transform_instance(stdClass $old) {
    global $CFG;
    require_once(dirname(dirname(dirname(__FILE__))) . '/locallib.php');

    $new                = new stdClass();
    $new->course        = $old->course;
    $new->name          = $old->name;
    $new->intro         = $old->description;
    $new->introformat   = $old->format;
    $new->nattachments  = $old->nattachments;
    $new->maxbytes      = $old->maxbytes;
    $new->grade         = $old->grade;
    $new->gradinggrade  = $old->gradinggrade;
    $new->phase         = workshopplus::PHASE_CLOSED;
    $new->timemodified  = time();
    if ($old->ntassessments > 0) {
        $new->useexamples = 1;
    } else {
        $new->useexamples = 0;
    }
    $new->usepeerassessment = 1;
    $new->useselfassessment = $old->includeself;
    switch ($old->gradingstrategy) {
    case 0: // 'notgraded' - renamed
        $new->strategy = 'comments';
        break;
    case 1: // 'accumulative'
        $new->strategy = 'accumulative';
        break;
    case 2: // 'errorbanded' - renamed
        $new->strategy = 'numerrors';
        break;
    case 3: // 'criterion' - will be migrated into 'rubric'
        $new->strategy = 'rubric';
        break;
    case 4: // 'rubric'
        $new->strategy = 'rubric';
        break;
    }
    if ($old->submissionstart < $old->submissionend) {
        $new->submissionstart = $old->submissionstart;
        $new->submissionend   = $old->submissionend;
    }
    if ($old->assessmentstart < $old->assessmentend) {
        $new->assessmentstart = $old->assessmentstart;
        $new->assessmentend   = $old->assessmentend;
    }

    return $new;
}