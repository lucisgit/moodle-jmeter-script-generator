<?php

class quiz_test extends master_test {
    var $quiz_view;
    var $pages;

    function __construct($quiz_cmid) {
        global $DB;

        $cm = $DB->get_record('course_modules', array('id' => $quiz_cmid));

        //  If we can't get the $cm something has gone wrong
        //  with this quiz. Marked as failed quiz and exit
        if(empty($cm)) {
            $this->failed = true;
            $this->error = "Cannot load course module";
            return false;
        }

        //  Setup vars
        $this->name     = "[QUIZ $quiz_cmid]";
        $this->courseid = $cm->course;

        //  Now we need to load all the quiz details!
        $quiz = $DB->get_record('quiz', array('id' => $cm->instance));

        //  If we can't get the $quiz something has gone wrong
        //  with this quiz. Marked as failed quiz and exit
        if(empty($quiz)) {
            $this->failed = true;
            $this->error = "Cannot load quiz";
            return false;
        }

        $this->test_startup();

        //  View quiz page
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' View Quiz', 'mod/quiz/view.php', array('id'=>$quiz_cmid)));

        //  Attempt quiz page
        $regex = array(new regex("Find attempt ID", 'attempt_id', '<input.*?name="attempt".*?value="(.*?)".*?\/>'));
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' Start Attempt - page 0', 'mod/quiz/attempt.php', array('id'=>$quiz_cmid), false, $regex));

        //  Get the questions
        $questions = quiz_get_questions($quiz->id);

        //  If we can't get the $questions something has gone wrong
        //  with this quiz. Marked as failed quiz and exit
        if(empty($questions)) {
            $this->failed = true;
            $this->error = "Cannot load questions";
            return false;
        }

        //  Now we have all of the questions, and the answers. What we want to
        //  do now is reproduce the path of a user through the quiz.

        //  First loop through all the questions and construct the posted data
        $post_data = array();
        foreach($questions as $qid => $question) {
            if(!question_type_is_info($question)) {
                //  If we're not on an info page we want to submit the data
                //  depending on the data type
                $key = quiz_question_type($question);
                if(empty($key)) {
                    $this->failed = true;
                    $this->error = "The quiz contains an unsupported question type $question->qtype";
                    return false;
                }
                $fn = "{$key}_correct_post_data";
                $post_data[$question->id] = $this->$fn($question);
            }
        }

        //  Now I've got all the post data I need to work out the layout of the quiz
        $this->set_questions_per_page($quiz->questions);

        //  Loop through all the questions and either visit the page if it's just
        //  info, or visit then submit data if it's a question
        $page = 0;
        foreach($this->pages as $question_ids) {
            //  If we are on the first page we've already visited it (see the
            //  Start Attempt above), don't visit it again.
            if($page > 0) {
                $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' View quiz - page '.$page, 'mod/quiz/attempt.php', array('attempt'=>'${attempt_id}', 'page'=>$page)));
            }

            //  Now work out what data to submit for this page!
            $submit_data = array();
            $qids = false;
            foreach($question_ids as $id) {
                //  If we have a question that is not info
                if(!question_type_is_info($questions[$id])) {
                    $submit_data = array_merge($submit_data, $post_data[$id]);
                }

                //  Store which questions are getting submitted
                if(!empty($qids)) {
                    $qids .= ',';
                }
                $qids .= $id;
            }

            if(!empty($submit_data)) {
                //  Now submit the data
                $submit_data = array_merge(
                        array(
                            'attempt'     => '${attempt_id}',
                            'page'        => $page,
                            'questionids' => $qids
                        ),
                        $submit_data
                );
                $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' Submit quiz data - page '.$page.'', 'mod/quiz/processattempt.php', $submit_data, (object) array('method'=>'POST')));
            }
            $page++;
        }

        //  Finish and submit all!
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' Finsih Attempt', 'mod/quiz/processattempt.php', array('attempt'=>'${attempt_id}','page'=>0,'finishattempt'=>'1','questionids'=>''), (object) array('method'=>'POST')));

        $this->test_finish();

        $this->convert_to_xml_element();
    }

    private function numerical_correct_post_data($question) {
        foreach($question->options->answers as $answer) {
            if($answer->fraction == 1) {
                return array($question->name_prefix.'answer'=>$answer->answer);
            }
        }
    }

    private function shortanswer_correct_post_data($question) {
        foreach($question->options->answers as $answer) {
            if($answer->fraction == 1) {
                return array($question->name_prefix=>$answer->answer);
            }
        }
    }

    private function radio_correct_post_data($question) {
        foreach($question->options->answers as $answer) {
            if($answer->fraction == 1) {
                return array($question->name_prefix=>$answer->id);
            }
        }
    }

    private function match_correct_post_data($question) {
        $return = array();
        foreach($question->options->subquestions as $subq) {
            $key = "{$question->name_prefix}{$subq->id}";
            $return[$key] = $subq->code;
        }
        return $return;
    }

    private function multichoice_correct_post_data($question) {
        $return = array();
        foreach($question->options->answers as $answer) {
            if($answer->fraction > 0) {
                $key = "{$question->name_prefix}{$answer->id}";
                $return[$key] = $answer->id;
            }
        }
        return $return;
    }

    private function essay_correct_post_data($question) {
        return array($question->name_prefix => "Essay {$question->name_prefix} answer");
    }

    private function set_questions_per_page($s){
        //get questions per page
        $s = str_replace(',0,', 'p', $s);//delete all 0's apart from th elast
        $s = str_replace(',0', 'p', $s);//delete last 0
        $tok = strtok($s, 'p');
        $p=0;
        $this->pages = array();
        while ($tok !== false) {
            $this->pages[] = explode(',', $tok);
            $tok = strtok("p");
        }
    }
}

class quiz_test_setup extends test_setup {

    public function get_cms($courseid) {
        $cms = parent::get_cms($courseid);
        foreach ($cms as $cm) {
            if (!$this->check_supported($cm->id)) {
                unset($cms[$cm->id]);
            }
        }
        return $cms;
    }

    public function check_supported($cmid) {
        //  Load this quizzes questions
        $questions = quiz_get_questions($cmid);
        $unsupported_question_type = false;

        if(!empty($questions)) {
            $unsupported_question_type = quiz_check_for_unsupported_questions($questions);
        }

        if(empty($questions) || !empty($unsupported_question_type)) {
            //  Quiz doesn't contain any questions, or contains an unsupported type remove it
            return false;
        }

        // Default return is true
        return true;
    }

    public function create_testplan($jmeter, $activities) {
        foreach($activities as $quiz) {
            $quiz_xml = new quiz_test($quiz->cmid);
            if(empty($quiz_xml->failed)) {
                $jmeter->testplan_hashtree_constructor->add_child($quiz_xml);
            } else {
                $jmeter->testplan_hashtree_constructor->add_child(new random_timer("Failed to insert quiz $quiz->cmid due to $quiz_xml->error", 'false'));
            }
        }
    }
}

function quiz_get_questions($quiz_id) {
    global $USER,$CFG,$QTYPES,$_SESSION, $DB;
    include_once($CFG->dirroot.'/mod/quiz/locallib.php');

    //  Check to see we haven't already gotten these questions. If we have return from the session
    if(!empty($_SESSION['retrived_question_ids'][$quiz_id])) {
        $question_ids = $_SESSION['retrived_question_ids'][$quiz_id];
    } else {
        $question_ids = $DB->get_records('quiz_question_instances', array('quiz' => $quiz_id));
    }

    $questions = array();
    if(empty($question_ids)) {
        return false;
    }
    $_SESSION['retrived_question_ids'][$quiz_id] = $question_ids;

    if(!empty($_SESSION['retrived_questions'][$quiz_id])) {
        $questions = $_SESSION['retrived_questions'][$quiz_id];
    } else {
        foreach($question_ids as $index => $question) {
            $db_question = $DB->get_record('question', array('id' => $question->question));
            $questions[$db_question->id] = $db_question;
        }
        get_question_options($questions);
        $_SESSION['retrived_questions'][$quiz_id] = $questions;
    }

    return $questions;
}

/* Returns the question type if unsupported question found, false otherwise */
function quiz_check_for_unsupported_questions($questions) {
    foreach($questions as $question) {
        $check = quiz_question_type($question);
        if(empty($check) && !question_type_is_info($question)) {
            return $question->qtype;
        }
    }
    return false;
}

function question_type_is_info($question) {
    if($question->qtype == 'description') {
        return true;
    }
    return false;
}

function quiz_question_type($question) {
    switch($question->qtype) {
        case 'numerical':
            $key = 'numerical';
            break;
        case 'shortanswer':
            $key = 'shortanswer';
            break;
        case 'truefalse':
            $key = 'radio';
            break;
        case 'match':
            $key = 'match';
            break;
        case 'multichoice':
            $key = 'multichoice';
            break;
        case 'essay':
            $key = 'essay';
            break;
        default:
            return false;
            break;
    }
    return $key;
}

