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

use qtype_programmingtask\utility\proforma_xml\grading_hints_helper;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");
require_once("$CFG->dirroot/question/type/programmingtask/locallib.php");

class qtype_programmingtask_external extends external_api {

    public static function extract_task_infos_from_draft_file_parameters() {
        return new external_function_parameters(
                array(
            'itemid' => new external_value(PARAM_INT, 'id of the draft area')
                )
        );
    }

    public static function extract_task_infos_from_draft_file_returns() {
        return new external_single_structure(
                array(
            'error' => new external_value(PARAM_TEXT, 'error message', VALUE_OPTIONAL),
            'title' => new external_value(PARAM_TEXT, 'title of the task', VALUE_OPTIONAL),
            'description' => new external_value(PARAM_RAW, 'description of the task', VALUE_OPTIONAL),
            'internaldescription' => new external_value(PARAM_RAW, 'internal description of the task', VALUE_OPTIONAL),
            'taskuuid' => new external_value(PARAM_RAW, 'task\'s uuid', VALUE_OPTIONAL),
            'maxscoregradinghints' => new external_value(PARAM_FLOAT, 'maximum score', VALUE_OPTIONAL),
            'filesdisplayedingeneralfeedback' => new external_value(PARAM_RAW, 'general feedback', VALUE_OPTIONAL),
            'moodleValidationProformaNamespace' => new external_value(PARAM_TEXT, 'detected namespace', VALUE_OPTIONAL),
            'moodleValidationWarningInvalidNamespace' => new external_value(PARAM_TEXT, 'warning message in case of invalid XML namespace', VALUE_OPTIONAL),
            'moodleValidationWarnings' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'msg' => new external_value(PARAM_TEXT, 'warning message')
                        )
                    ), 'XML validation warning messages', VALUE_OPTIONAL)
                )
        );
    }

    /**
     * Extracts the relevant information from the file in the draft area with the supplied id.
     * Relevant information are for example: Title, description, internal description.
     *
     * @param type $itemid
     */
    public static function extract_task_infos_from_draft_file($itemid) {
        global $USER;

        // Do some validation.
        $params = self::validate_parameters(self::extract_task_infos_from_draft_file_parameters(), array('itemid' => $itemid));
        $draftid = $params['itemid'];

        $usercontext = context_user::instance($USER->id);
        self::validate_context($usercontext);

        $taskfilename = unzip_task_file_in_draft_area($draftid, $usercontext);

        if ($taskfilename == null) {
            return ['error' => 'Error extracting zip file'];
        }

        $doc = create_domdocument_from_task_xml($usercontext, $draftid, $taskfilename);
        $namespace = detect_proforma_namespace($doc);
        $returnval = array();

        if ($namespace == null) {

            $returnval['moodleValidationWarningInvalidNamespace'] = get_string('invalidproformanamespace', 'qtype_programmingtask',
                    implode(", ", PROFORMA_TASK_XML_NAMESPACES));
        } else {

            $validationerrors = validate_proforma_file_against_schema($doc, $namespace);
            if (!empty($validationerrors)) {
                $returnval['moodleValidationProformaNamespace'] = $namespace;
                $msgarr = array();
                foreach ($validationerrors as $msg) {
                    array_push($msgarr, array('msg' => $msg));
                }
                $returnval['moodleValidationWarnings'] = $msgarr;
            }

            foreach ($doc->getElementsByTagNameNS($namespace, 'description') as $des) {
                if ($des->parentNode->localName == 'task') {
                    $returnval['description'] = $des->nodeValue;
                    break;
                }
            }

            foreach ($doc->getElementsByTagNameNS($namespace, 'title') as $des) {
                if ($des->parentNode->localName == 'task') {
                    $returnval['title'] = $des->nodeValue;
                    break;
                }
            }

            foreach ($doc->getElementsByTagNameNS($namespace, 'internal-description') as $des) {
                if ($des->parentNode->localName == 'task') {
                    $returnval['internaldescription'] = $des->nodeValue;
                    break;
                }
            }

            // Currently only supports tns:task-type; neither tns:external-task-type nor tns:included-task-file-type
            // TODO: Implement the other two.
            foreach ($doc->getElementsByTagNameNS($namespace, 'task') as $task) {
                $returnval['taskuuid'] = $task->getAttribute('uuid');
                break;
            }

            // Pre-calculate the max score in the grading hints to use as the default/max mark
            $gradinghints = $doc->getElementsByTagNameNS($namespace, 'grading-hints')[0];
            $tests = $doc->getElementsByTagNameNS($namespace, 'tests')[0];
            $gradinghintshelper = new grading_hints_helper($gradinghints, $tests, $namespace);
            $maxscoregradinghints = $gradinghintshelper->calculate_max_score();
            $returnval['maxscoregradinghints'] = $maxscoregradinghints;

            // Fill in the question's general feedback
            // Look for files that have their 'visible' attribute set to 'delayed' and the
            // 'usage-by-lms' attribute set to 'display'. Display these files in the
            // question's general feedback.
            $filesdisplayedingeneralfeedback = '';
            foreach ($doc->getElementsByTagNameNS($namespace, 'file') as $file) {
                $filediv = '';
                foreach ($file->childNodes as $child) {
                    if($file->attributes->getNamedItem('visible')->nodeValue == 'delayed' &&
                        $file->attributes->getNamedItem('usage-by-lms') != null &&
                        $file->attributes->getNamedItem('usage-by-lms')->nodeValue == 'display') {

                        $filecontent = '';

                        if($child->localName == 'embedded-txt-file') {
//                            $filepath = pathinfo('/' . $file->attributes->getNamedItem('id')->nodeValue . '/' .
//                                $child->attributes->getNamedItem('filename')->nodeValue);
                            $filecontent = $child->nodeValue;
                        } else if ($child->localName == 'attached-txt-file') {
                            $pathinfo = pathinfo('/' . $child->nodeValue);
                            $filecontent = get_text_content_from_file($usercontext, $draftid, $taskfilename,
                                $pathinfo['dirname'] . '/', $pathinfo['basename']);
                        }

                        if(!empty($filecontent)) {
                            $filediv = html_writer::start_div('delayeddisplayedfile', ['style' => 'overflow: auto;']);
                            $filediv .= $filecontent;
                            $filediv .= html_writer::end_div('delayeddisplayedfile');
                        }
                    }
                }
                if(!empty($filediv)) {
                    $filesdisplayedingeneralfeedback .= $filediv;
//                    $filesdisplayedingeneralfeedback .= '<p />';
                }
            }
            $returnval['filesdisplayedingeneralfeedback'] = $filesdisplayedingeneralfeedback;
        }

        // Do a little bit of cleanup and remove everything from the file area we extracted.
        remove_all_files_from_draft_area($draftid, $usercontext, $taskfilename);

        return $returnval;
    }

    public static function retrieve_grading_results_parameters() {
        return new external_function_parameters(
                ['qubaid' => new external_value(PARAM_INT, 'id of the question usage')]
        );
    }

    public static function retrieve_grading_results_returns() {
        return new external_value(PARAM_BOOL, "whether any grade process finished");
    }

    public static function retrieve_grading_results($qubaid) {
        global $USER, $SESSION, $DB;

        // Do some param validation.
        $params = self::validate_parameters(self::retrieve_grading_results_parameters(), array('qubaid' => $qubaid));
        $qubaid = $params['qubaid'];

        // Check if calling user is teacher.
        $qubarecord = $DB->get_record('question_usages', ['id' => $qubaid]);
        $contextrecord = $DB->get_record('context', ['id' => $qubarecord->contextid]);
        $context = context_module::instance($contextrecord->instanceid);
        self::validate_context($context);
        $isteacher = has_capability('mod/quiz:grade', $context);

        $lastaccess = $SESSION->last_retrieve_grading_results ?? microtime(true);
        $SESSION->last_retrieve_grading_results = microtime(true);
        if (microtime(true) - $lastaccess < get_config("qtype_programmingtask", "service_client_polling_interval") *
                0.9 && !$isteacher) {
            // Only allow a request every n seconds from the same user.
            return false;
        }

        // Check if the question usage given by the qubaid belongs to the requesting user.
        if (!$isteacher) {
            $record = $DB->get_record('quiz_attempts', ['uniqueid' => $qubaid], 'userid');
            if (!$record) {
                return false;
            }
            $userid = $record->userid;
            if ($userid != $USER->id) {
                return false;
            }
        }

        return retrieve_grading_results($qubaid);
    }

}
