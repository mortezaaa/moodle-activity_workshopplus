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
 * @package    workshopplusform_numerrors
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/gradelib.php'); // grade_floatval() called here

/**
 * Conversion handler for the numerrors grading strategy data
 */
class moodle1_workshopplusform_numerrors_handler extends moodle1_workshopplusform_handler {

    /** @var array */
    protected $mappings = array();

    /** @var array */
    protected $dimensions = array();

    /**
     * New workshopplus instance is being processed
     */
    public function on_elements_start() {
        $this->mappings = array();
        $this->dimensions = array();
    }

    /**
     * Converts <ELEMENT> into <workshopplusform_numerrors_dimension> and stores it for later writing
     *
     * @param array $data legacy element data
     * @param array $raw raw element data
     *
     * @return array to be written to workshopplus.xml
     */
    public function process_legacy_element(array $data, array $raw) {

        $workshopplus = $this->parenthandler->get_current_workshopplus();

        $mapping = array();
        $mapping['id'] = $data['id'];
        $mapping['nonegative'] = $data['elementno'];
        if ($workshopplus['grade'] == 0 or $data['maxscore'] == 0) {
            $mapping['grade'] = 0;
        } else {
            $mapping['grade'] = grade_floatval($data['maxscore'] / $workshopplus['grade'] * 100);
        }
        $this->mappings[] = $mapping;

        $converted = null;

        if (trim($data['description']) and $data['description'] <> '@@ GRADE_MAPPING_ELEMENT @@') {
            // prepare a fake record and re-use the upgrade logic
            $fakerecord = (object)$data;
            $converted = (array)workshopplusform_numerrors_upgrade_element($fakerecord, 12345678);
            unset($converted['workshopplusid']);

            $converted['id'] = $data['id'];
            $this->dimensions[] = $converted;
        }

        return $converted;
    }

    /**
     * Writes gathered mappings and dimensions
     */
    public function on_elements_end() {

        foreach ($this->mappings as $mapping) {
            $this->write_xml('workshopplusform_numerrors_map', $mapping, array('/workshopplusform_numerrors_map/id'));
        }

        foreach ($this->dimensions as $dimension) {
            $this->write_xml('workshopplusform_numerrors_dimension', $dimension, array('/workshopplusform_numerrors_dimension/id'));
        }
    }
}

/**
 * Transforms a given record from workshopplus_elements_old into an object to be saved into workshopplusform_numerrors
 *
 * @param stdClass $old legacy record from workshopplus_elements_old
 * @param int $newworkshopplusid id of the new workshopplus instance that replaced the previous one
 * @return stdclass to be saved in workshopplusform_numerrors
 */
function workshopplusform_numerrors_upgrade_element(stdclass $old, $newworkshopplusid) {
    $new = new stdclass();
    $new->workshopplusid = $newworkshopplusid;
    $new->sort = $old->elementno;
    $new->description = $old->description;
    $new->descriptionformat = FORMAT_HTML;
    $new->grade0 = get_string('grade0default', 'workshopplusform_numerrors');
    $new->grade1 = get_string('grade1default', 'workshopplusform_numerrors');
    // calculate new weight of the element. Negative weights are not supported any more and
    // are replaced with weight = 0. Legacy workshopplus did not store the raw weight but the index
    // in the array of weights (see $workshopplus_EWEIGHTS in workshopplus 1.x)
    // workshopplus 2.0 uses integer weights only (0-16) so all previous weights are multiplied by 4.
    switch ($old->weight) {
        case 8: $new->weight = 1; break;
        case 9: $new->weight = 2; break;
        case 10: $new->weight = 3; break;
        case 11: $new->weight = 4; break;
        case 12: $new->weight = 6; break;
        case 13: $new->weight = 8; break;
        case 14: $new->weight = 16; break;
        default: $new->weight = 0;
    }
    return $new;
}
