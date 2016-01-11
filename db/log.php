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
 * Definition of log events
 *
 * @package    mod_workshopplus
 * @category   log
 * @copyright  2010 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    // workshopplus instance log actions
    array('module'=>'workshopplus', 'action'=>'add', 'mtable'=>'workshopplus', 'field'=>'name'),
    array('module'=>'workshopplus', 'action'=>'update', 'mtable'=>'workshopplus', 'field'=>'name'),
    array('module'=>'workshopplus', 'action'=>'view', 'mtable'=>'workshopplus', 'field'=>'name'),
    array('module'=>'workshopplus', 'action'=>'view all', 'mtable'=>'workshopplus', 'field'=>'name'),
    // submission log actions
    array('module'=>'workshopplus', 'action'=>'add submission', 'mtable'=>'workshopplus_submissions', 'field'=>'title'),
    array('module'=>'workshopplus', 'action'=>'update submission', 'mtable'=>'workshopplus_submissions', 'field'=>'title'),
    array('module'=>'workshopplus', 'action'=>'view submission', 'mtable'=>'workshopplus_submissions', 'field'=>'title'),
    // assessment log actions
    array('module'=>'workshopplus', 'action'=>'add assessment', 'mtable'=>'workshopplus_submissions', 'field'=>'title'),
    array('module'=>'workshopplus', 'action'=>'update assessment', 'mtable'=>'workshopplus_submissions', 'field'=>'title'),
    // example log actions
    array('module'=>'workshopplus', 'action'=>'add example', 'mtable'=>'workshopplus_submissions', 'field'=>'title'),
    array('module'=>'workshopplus', 'action'=>'update example', 'mtable'=>'workshopplus_submissions', 'field'=>'title'),
    array('module'=>'workshopplus', 'action'=>'view example', 'mtable'=>'workshopplus_submissions', 'field'=>'title'),
    // example assessment log actions
    array('module'=>'workshopplus', 'action'=>'add reference assessment', 'mtable'=>'workshopplus_submissions', 'field'=>'title'),
    array('module'=>'workshopplus', 'action'=>'update reference assessment', 'mtable'=>'workshopplus_submissions', 'field'=>'title'),
    array('module'=>'workshopplus', 'action'=>'add example assessment', 'mtable'=>'workshopplus_submissions', 'field'=>'title'),
    array('module'=>'workshopplus', 'action'=>'update example assessment', 'mtable'=>'workshopplus_submissions', 'field'=>'title'),
    // grading evaluation log actions
    array('module'=>'workshopplus', 'action'=>'update aggregate grades', 'mtable'=>'workshopplus', 'field'=>'name'),
    array('module'=>'workshopplus', 'action'=>'update clear aggregated grades', 'mtable'=>'workshopplus', 'field'=>'name'),
    array('module'=>'workshopplus', 'action'=>'update clear assessments', 'mtable'=>'workshopplus', 'field'=>'name'),
);
