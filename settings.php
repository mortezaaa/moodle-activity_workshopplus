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
 * The workshopplus module configuration variables
 *
 * The values defined here are often used as defaults for all module instances.
 *
 * @package    mod
 * @subpackage workshopplus
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/workshopplus/locallib.php');

    $grades = workshopplus::available_maxgrades_list();

    $settings->add(new admin_setting_configselect('workshopplus/grade', get_string('submissiongrade', 'workshopplus'),
                        get_string('configgrade', 'workshopplus'), 80, $grades));

    $settings->add(new admin_setting_configselect('workshopplus/gradinggrade', get_string('gradinggrade', 'workshopplus'),
                        get_string('configgradinggrade', 'workshopplus'), 20, $grades));

    $options = array();
    for ($i = 5; $i >= 0; $i--) {
        $options[$i] = $i;
    }
    $settings->add(new admin_setting_configselect('workshopplus/gradedecimals', get_string('gradedecimals', 'workshopplus'),
                        get_string('configgradedecimals', 'workshopplus'), 0, $options));

    if (isset($CFG->maxbytes)) {
        $maxbytes = get_config('workshopplus', 'maxbytes');
        $options = get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes);
        $settings->add(new admin_setting_configselect('workshopplus/maxbytes', get_string('maxbytes', 'workshopplus'),
                            get_string('configmaxbytes', 'workshopplus'), 0, $options));
    }

    $settings->add(new admin_setting_configselect('workshopplus/strategy', get_string('strategy', 'workshopplus'),
                        get_string('configstrategy', 'workshopplus'), 'accumulative', workshopplus::available_strategies_list()));

    $options = workshopplus::available_example_modes_list();
    $settings->add(new admin_setting_configselect('workshopplus/examplesmode', get_string('examplesmode', 'workshopplus'),
                        get_string('configexamplesmode', 'workshopplus'), workshopplus::EXAMPLES_VOLUNTARY, $options));

    // include the settings of allocation subplugins
    $allocators = core_component::get_plugin_list('workshopplusallocation');
    foreach ($allocators as $allocator => $path) {
        if (file_exists($settingsfile = $path . '/settings.php')) {
            $settings->add(new admin_setting_heading('workshopplusallocationsetting'.$allocator,
                    get_string('allocation', 'workshopplus') . ' - ' . get_string('pluginname', 'workshopplusallocation_' . $allocator), ''));
            include($settingsfile);
        }
    }

    // include the settings of grading strategy subplugins
    $strategies = core_component::get_plugin_list('workshopplusform');
    foreach ($strategies as $strategy => $path) {
        if (file_exists($settingsfile = $path . '/settings.php')) {
            $settings->add(new admin_setting_heading('workshopplusformsetting'.$strategy,
                    get_string('strategy', 'workshopplus') . ' - ' . get_string('pluginname', 'workshopplusform_' . $strategy), ''));
            include($settingsfile);
        }
    }

    // include the settings of grading evaluation subplugins
    $evaluations = core_component::get_plugin_list('workshoppluseval');
    foreach ($evaluations as $evaluation => $path) {
        if (file_exists($settingsfile = $path . '/settings.php')) {
            $settings->add(new admin_setting_heading('workshopplusevalsetting'.$evaluation,
                    get_string('evaluation', 'workshopplus') . ' - ' . get_string('pluginname', 'workshoppluseval_' . $evaluation), ''));
            include($settingsfile);
        }
    }

}
