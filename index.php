<?php

    /**
     * Load Testing Setup
     *
     * @copyright &copy; 2007 The Open University
     * @author j.e.c.brisland@open.ac.uk
     * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
     * @package loadtesting
     */

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

/*
 * This class takes one of my defined xml_elements and adds it as a child element
 * to a simple xml structure given.
 * It will also add any children of the object passed in.
*/
class simple_xml_constructor{
    var $xml_element_pointer;

    function __construct(&$xml_element_pointer) {
        $this->xml_element_pointer = $xml_element_pointer;
    }

    public function add_child($element) {
        //  If there isn't a tagname throw exception
        if(empty($element->tagname)) {
            throw new Exception('Error: No tagname provided');
        }

        $current_child_element_pointer = $this->xml_element_pointer->addChild($element->tagname, $element->value);

        $this->do_attributes($current_child_element_pointer, $element);
        $this->do_children($current_child_element_pointer, $element);
        $this->do_siblings($current_child_element_pointer, $element);
    }

    private function add_sibling($current_child_element_pointer, $element) {
        $parent_element = array_pop($current_child_element_pointer->xpath('..'));
        $current_element = $parent_element->addChild($element->tagname, $element->value);
        $this->do_attributes($current_element, $element);
        $this->do_children($current_element, $element);
        $this->do_siblings($current_element, $element);
    }

    private function do_children($current_child_element_pointer, $element) {
        if(!empty($element->children)) {
            foreach($element->children as $data) {
                $child = new simple_xml_constructor($current_child_element_pointer);
                $child->add_child($data);
            }
        }
    }

    private function do_siblings($current_child_element_pointer, $element) {
        if(!empty($element->siblings)) {
            foreach($element->siblings as $data) {
                $sibling = new simple_xml_constructor($current_child_element_pointer);
                $sibling->add_sibling($current_child_element_pointer, $data);
            }
        }
    }

    private function do_attributes($current_child_element_pointer, $element) {
        if(!empty($element->attributes)) {
            foreach($element->attributes as $k => $v) {
                $current_child_element_pointer->addAttribute($k, $v);
            }
        }
    }
}

/*
 * This class defines an xml element as a php object
*/
class xml_element {
    public $tagname;    //  Tagname
    public $attributes; //  Array of Key => Values pairs
    public $value;      //  Value for tag <tag>VALUE</tag>
    public $children;   //  Array of xml_elements
    public $siblings;   //  This allows siblings to be added. In jMeter some of
                        //  the tags have connected siblings.

    function __construct($tagname, $attributes=false, $value=false, $children=false, $siblings=false) {
        $this->tagname    = $tagname;
        $this->attributes = $attributes;
        $this->value      = $value;
        $this->children   = $children;
        $this->siblings   = $siblings;
    }

    public function add_child($element) {
        $this->children[] = $element;
    }

    public function add_sibling($element) {
        $this->siblings[] = $element;
    }

    public function copy_to_this($data) {
        $this->tagname    = $data->tagname;
        $this->attributes = $data->attributes;
        $this->children   = $data->children;
        $this->siblings   = $data->siblings;
    }
}

class main_element extends xml_element {
    function __construct($tagname, $guiclass=false, $testclass=false, $testname=false, $name=false, $enabled='true', $elementtype=false) {
        $this->tagname = $tagname;

        if(!empty($name)) {
            $this->attributes['name'] = $name;
        }
        if(!empty($elementtype)) {
            $this->attributes['elementType'] = $elementtype;
        }
        if(!empty($guiclass)) {
            $this->attributes['guiclass'] = $guiclass;
        }
        if(!empty($testclass)) {
            $this->attributes['testclass'] = $testclass;
        }
        if(!empty($testname)) {
            $this->attributes['testname'] = $testname;
        }
        if(!empty($enabled)) {
            $this->attributes['enabled'] = $enabled;
        }
    }
}

class csv_dataset extends main_element {
    function __construct($filename, $variablenames, $delimiter=',', $fileencoding=false, $recycle = 'true', $quoteddata = 'false', $stopthread = 'false', $sharemode='All threads') {
        $csvstr = 'CSVDataSet';
        parent::__construct($csvstr, "TestBeanGUI", $csvstr, "CSV Data Set Config");
        $this->add_child(new stringprop('delimiter',     $delimiter));
        $this->add_child(new stringprop('fileEncoding',  $fileencoding));
        $this->add_child(new stringprop('filename',      $filename));
        $this->add_child(new   boolprop('recycle',       $recycle));
        $this->add_child(new stringprop('variableNames', $variablenames));
        $this->add_child(new   boolprop('quotedData',    $quoteddata));
        $this->add_child(new   boolprop('stopThread',    $stopthread));
        $this->add_child(new stringprop('shareMode',     $sharemode));

        $this->add_sibling(new hashtree());
    }
}

class header_manager extends main_element {
    function __construct($name, $key_value_pairs) {
        parent::__construct('HeaderManager', 'HeaderPanel', 'HeaderManager', $name);
        $this->add_child(new user_def_vars($key_value_pairs, 'Header', false, false, 'HeaderManager.headers', 'Header'));
    }
}

class http_request_defaults extends main_element {
    function __construct($properties=false) {
        parent::__construct('ConfigTestElement', 'HttpDefaultsGui', 'ConfigTestElement', 'HTTP Request Defaults');

        $httpstr = 'HTTPsampler';
        $this->add_child(new user_def_vars_eleprop("{$httpstr}.Arguments", 'Arguments', 'HTTPArgumentsPanel', 'Arguments', 'User Defined Variables'));
        $this->httpsampler_defaults($properties);

        //  Now add in an empty hashtree
        $this->add_sibling(new hashtree());
    }

    function httpsampler_defaults($properties=false) {
        global $CFG;
        $defaults                    = new Object();
        $defaults->domain            = str_replace('http://', '', $CFG->wwwroot);

        //  This is for dev only, won't do anything on live!
        //  Find the first /
        if(($pos = strpos($defaults->domain, '/')) !== false)
        {
		$defaults->domain = substr($defaults->domain, 0, $pos);
        }

        $defaults->port              = false;
        $defaults->connect_timeout   = false;
        $defaults->response_timeout  = false;
        $defaults->protocol          = false;
        $defaults->contentEncoding   = false;
        $defaults->path              = false;

        if(empty($properties)) {
            $properties = clone($defaults);
        } else {
            foreach($defaults as $key => $value) {
                if(!isset($properties->$key)) {
                    $properties->$key = $value;
                }
            }
        }

        $htstr = 'HTTPSampler';
        $this->add_child(new stringprop("{$htstr}.domain",            $properties->domain));
        $this->add_child(new stringprop("{$htstr}.port",              $properties->port));
        $this->add_child(new stringprop("{$htstr}.connect_timeout",   $properties->connect_timeout));
        $this->add_child(new stringprop("{$htstr}.response_timeout",  $properties->response_timeout));
        $this->add_child(new stringprop("{$htstr}.protocol",          $properties->protocol));
        $this->add_child(new stringprop("{$htstr}.contentEncoding",   $properties->contentEncoding));
        $this->add_child(new stringprop("{$htstr}.path",              $properties->path));
    }
}

/*
 * DEFAULTS AS GET
*/
class httpsampler extends http_request_defaults {
    function __construct($name, $path, $arguments=array(), $properties=false, $hashtree_children=false) {
        $element = new main_element('HTTPSampler', 'HttpTestSampleGui', 'HTTPSampler', $name);
        $element->add_child(new user_def_vars_eleprop('HTTPsampler.Arguments', 'Arguments', 'HTTPArgumentsPanel', 'Arguments', false, $arguments));

        //  Copy the element to this
        $this->copy_to_this($element);

        if(empty($properties)) {
            $properties = new Object();
        }
        //  Override the domain which is set in the master class to be empty.
        if(!isset($properties->domain)) {
            $properties->domain = false;
        }

        //  Add the moodle path to the properties
        if(!empty($path)) {
            $path = MOODLE_PATH."/$path";
            $properties->path = $path;
        }

        $this->httpsampler_defaults($properties);
        $this->httpsampler_extra($properties);

        //  Now add in an empty hashtree
        $hashtree = new hashtree();
        if(!empty($hashtree_children)) {
            foreach($hashtree_children as $child) {
                $hashtree->add_child($child);
            }
        }

        //  Add the hastree
        $this->add_sibling($hashtree);
    }

    function httpsampler_extra($properties=false) {
        $defaults                    = new Object();
        $defaults->method            = 'GET';
        $defaults->follow_redirects  = 'true';
        $defaults->use_keepalive     = 'false';
        $defaults->auto_redirects    = 'true';
        $defaults->do_multipart_post = 'false';
        $defaults->file_name         = false;
        $defaults->file_field        = false;
        $defaults->mimetype          = false;
        $defaults->monitor           = 'false';
        $defaults->embedded_url_re   = false;
        if(empty($properties)) {
            $properties = clone($defaults);
        } else {
            foreach($defaults as $key => $value) {
                if(!isset($properties->$key)) {
                    $properties->$key = $value;
                }
            }
        }

        $htstr = 'HTTPSampler';
        $this->add_child(new stringprop("{$htstr}.method",            strtoupper($properties->method)));
        $this->add_child(new   boolprop("{$htstr}.follow_redirects",  $properties->follow_redirects));
        $this->add_child(new   boolprop("{$htstr}.auto_redirects",    $properties->use_keepalive));
        $this->add_child(new   boolprop("{$htstr}.use_keepalive",     $properties->auto_redirects));
        $this->add_child(new   boolprop("{$htstr}.DO_MULTIPART_POST", $properties->do_multipart_post));
        $this->add_child(new stringprop("{$htstr}.FILE_NAME",         $properties->file_name));
        $this->add_child(new stringprop("{$htstr}.FILE_FIELD",        $properties->file_field));
        $this->add_child(new stringprop("{$htstr}.mimetype",          $properties->mimetype));
        $this->add_child(new   boolprop("{$htstr}.monitor",           $properties->monitor));
        $this->add_child(new stringprop("{$htstr}.embedded_url_re",   $properties->embedded_url_re));
    }
}

class testplan extends main_element {
    function __construct($name='Test Plan', $user_def_vars=array(), $consecutive_threads='false', $func_test_mode='false', $dir_or_jar_to_classpath=false, $comments=false) {
        $tpstr = 'TestPlan';
        parent::__construct($tpstr, "{$tpstr}Gui", $tpstr, $name);
        $this->add_child(new stringprop("{$tpstr}.comments",               $comments));
        $this->add_child(new   boolprop("{$tpstr}.functional_mode",        $func_test_mode));
        $this->add_child(new   boolprop("{$tpstr}.serialize_threadgroups", $consecutive_threads));
        $this->add_child(new user_def_vars_eleprop("{$tpstr}.user_defined_variables", 'Arguments', 'ArgumentsPanel', 'Arguments', 'User Defined Variables'));
        $this->add_child(new stringprop("{$tpstr}.user_define_classpath"));
    }
}

class stringprop extends xml_element {
    function __construct($name, $value=false) {
        parent::__construct('stringProp', array('name'=>$name), $value);
    }
}

class boolprop extends xml_element {
    function __construct($name, $value='false') {
        parent::__construct('boolProp', array('name'=>$name), $value);
    }
}

class longprop extends xml_element {
    function __construct($name, $value=false) {
        if(!empty($value) && intval($value) !== $value) {
            throw new Exception('The value to long prop wasn\'t an int?!');
        }
        parent::__construct('longProp', array('name'=>$name), $value);
    }
}

class eleprop extends main_element {
    function __construct($name, $elementtype, $guiclass=false, $testclass=false, $testname=false, $enabled=false) {
        parent::__construct('elementProp', $guiclass, $testclass, $testname, $name, $enabled, $elementtype);
    }
}

class key_value_pair extends eleprop {
    function __construct($name, $value, $type, $extra=false, $metadata='=', $string_bit='Argument', $encode='false', $encode_equals = 'true') {
        $argstr = 'Argument';
        parent::__construct($name, $type);

        if(!empty($extra)) {
            $this->add_child(new boolprop("HTTP{$argstr}.always_encode", $encode));
        }

        $this->add_child(new stringprop("{$string_bit}.value", $value));

        if(!empty($metadata)) {
            $this->add_child(new stringprop("{$argstr}.metadata", $metadata));
        }

        if(!empty($extra)) {
            $this->add_child(new boolprop("HTTP{$argstr}.use_equals", $encode_equals));
        }

        $this->add_child(new stringprop("{$string_bit}.name", $name));
    }
}

class user_def_vars extends xml_element {
    function __construct($key_value_pairs=array(), $type='HTTPArgument', $extra=true, $metadata='=', $colprop_name='Arguments.arguments', $kv_name_bit='Argument') {
        $col_prop = new colprop($colprop_name);
        if(!empty($key_value_pairs)) {
            foreach($key_value_pairs as $key => $value) {
                $col_prop->add_child(new key_value_pair($key, $value, $type, $extra, $metadata, $kv_name_bit));
            }
        }
        $this->copy_to_this($col_prop);
    }
}

class user_def_vars_eleprop extends user_def_vars {
    function __construct($name, $elementtype, $guiclass, $testclass, $testname, $key_value_pairs=array(), $enabled='true') {
        parent::__construct($key_value_pairs);
        $element = new eleprop($name, $elementtype, $guiclass, $testclass, $testname, $enabled);
        $element->add_child(clone($this));
        $this->copy_to_this($element);
    }
}

class colprop extends xml_element {
    function __construct($name='Arguments.arguments') {
        $this->tagname    = 'collectionProp';
        $this->attributes = array('name'=>$name);
    }
}

class threadgroup extends main_element {
    function __construct($name, $loops=1, $loop_forever='false', $threads=1, $ramptime=1, $scheduler='false', $starttime=false, $endtime=false, $duration=false, $delay=false, $on_sample_error='continue') {
        $tgstr = 'ThreadGroup';
        $lcstr = 'LoopController';

        parent::__construct($tgstr, "{$tgstr}Gui", $tgstr, $name);

        $eleprop = new eleprop("{$tgstr}.main_controller", $lcstr, 'LoopControlPanel', $lcstr, 'Loop Controller', 'true');
        $eleprop->add_child(new   boolprop("{$lcstr}.continue_forever", $loop_forever));
        $eleprop->add_child(new stringprop("{$lcstr}.loops",            $loops));
        $this->add_child($eleprop);

        $this->add_child(new stringprop("{$tgstr}.num_threads", $threads));
        $this->add_child(new stringprop("{$tgstr}.ramp_time",   $ramptime));

        $starttime = empty($starttime) ? time() : $starttime;
        $endtime   = empty($endtime)   ? time() : $endtime;
        $this->add_child(new   longprop("{$tgstr}.start_time",      $starttime));
        $this->add_child(new   longprop("{$tgstr}.end_time",        $endtime));

        $this->add_child(new   boolprop("{$tgstr}.scheduler",       $scheduler));
        $this->add_child(new stringprop("{$tgstr}.on_sample_error", $on_sample_error));
        $this->add_child(new stringprop("{$tgstr}.duration",        $duration));
        $this->add_child(new stringprop("{$tgstr}.delay",           $delay));
    }
}

class loopcontroller extends main_element {
    function __construct($name, $loops=1, $loop_forever='false') {
        parent::__construct('LoopController', 'LoopControlPanel', 'LoopController', "{$name} Loop Controller");
        $this->add_child(new boolprop("LoopController.continue_forever", $loop_forever));
        $this->add_child(new stringprop("LoopController.loops", $loops));
    }
}

class hashtree extends xml_element {
    function __construct() {
        parent::__construct('hashTree');
    }
}

class cookiemanager extends main_element {
    function __construct() {
        $cmstr = 'CookieManager';
        //  Create the CookieManager
        parent::__construct($cmstr, 'CookiePanel', $cmstr, 'HTTP Cookie Manager');

        //  Create it's child elements
        $this->add_child(new  colprop("{$cmstr}.cookies"));
        $this->add_child(new boolprop("{$cmstr}.clearEachIteration", 'true'));

        //  Create the sibling hashtree
        $this->add_sibling(new hashtree());
    }
}

class moodle_login extends xml_element {
    function __construct($name_part) {
        $arguments = array('username'=>'${username}', 'password'=>'${password}', 'FromURL' => '', 'Proceed1' => 'Sign in');

        //  Create a new HTTPSampler
        $sampler = new httpsampler($name_part.' Login to site', 'login/index.php', $arguments, (object) array('method'=>'POST'));
        $this->copy_to_this($sampler);
    }
}

class master_test extends xml_element {
    var $threadgroup;
    var $threadgroup_hashtree;
    var $name;
    var $courseid;

    protected function convert_to_xml_element() {
        $data = new xml_element($this->threadgroup->tagname, $this->threadgroup->attributes, $this->threadgroup->value, $this->threadgroup->children, array($this->threadgroup_hashtree));
        unset($this->threadgroup);
        unset($this->threadgroup_hashtree);

        //  Now I need to turn this entire object into an xml element
        $this->copy_to_this($data);
    }

    protected function test_startup() {
        if(empty($this->name)) {
            $this->name = '[NOTSET]';
        }

        //  This shouldn't ever be empty!
        if(empty($this->courseid)) {
            throw Exception('Error: Course id not set!');
        }

        //  Start a new thread group
        $this->threadgroup = new threadgroup($this->name, LOOPS, $loop_forever='false', USERS, USERS);

        //  Add in the threadgroup hashtree
        $this->threadgroup_hashtree = new hashtree();

        //  Add in the cookie manager for this test
        $this->threadgroup_hashtree->add_child(new cookiemanager());

        //  Now we need to add in the site login
        $this->threadgroup_hashtree->add_child(new moodle_login($this->name));

        //  Add in the URLRewrite Modifier which automatically adds in sesskey to all post or get forums
        $this->threadgroup_hashtree->add_child(new url_rewrite($this->name));

        //  View course page
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' View Course', 'course/view.php', array('id'=>$this->courseid)));

    }
}

class url_rewrite extends main_element {
    function __construct($name_part) {
        parent::__construct('URLRewritingModifier', 'URLRewritingModifierGui', 'URLRewritingModifier', $name_part.' HTTP URL Re-writing Modifier');
        $this->add_child(new stringprop('argument_name', 'sesskey'));
        $this->add_child(new   boolprop('path_extension'));
        $this->add_child(new   boolprop('path_extension_no_equals'));
        $this->add_child(new   boolprop('path_extension_no_questionmark'));
        $this->add_child(new   boolprop('cache_value', 'true'));

        //  Add in empty hashtree
        $this->add_sibling(new hashtree());
    }
}

class random_timer extends main_element {
    function __construct($name_part, $enabled='true', $delay=300, $range=100) {
        parent::__construct('GaussianRandomTimer', 'GaussianRandomTimerGui', 'GaussianRandomTimer', $name_part.' Gaussian Random Timer', false, $enabled);
        $this->add_child(new stringprop('ConstantTimer.delay', $delay));
        $this->add_child(new stringprop('RandomTimer.range', sprintf ("%0.1f",$range)));

        //  Add in empty hashtree
        $this->add_sibling(new hashtree());
    }
}

class regex extends main_element {
    function __construct($name, $refname, $regex, $match=false, $template='$1$', $default=false, $useheaders = 'false') {
        $restr = 'RegexExtractor';
        parent::__construct($restr, "{$restr}Gui", $restr, $name);

        $this->add_child(new stringprop("{$restr}.useHeaders",   $useheaders));
        $this->add_child(new stringprop("{$restr}.refname",      $refname));
        $this->add_child(new stringprop("{$restr}.regex",        $regex));
        $this->add_child(new stringprop("{$restr}.template",     $template));
        $this->add_child(new stringprop("{$restr}.default",      $default));
        $this->add_child(new stringprop("{$restr}.match_number", $match));

        //  Add in empty hashtree
        $this->add_sibling(new hashtree());
    }
}

class forum_test extends master_test {
    var $forum_view;
    var $view_params;
    var $forum_post;
    var $posts;
    var $replys;
    var $post_regex;

    function __construct($forum) {
        global $DB;

        //  Get course modules record
        $cm                = $DB->get_record('course_modules', array('id' => $forum->cmid));
        $this->name        = "[FORUM {$forum->cmid}:{$forum->name}]";
        $this->forum_view  = 'mod/forum/view.php';
        $this->view_params = array('id'=>$cm->id);
        $this->forum_post  = 'mod/forum/post.php';
        $this->post_params = array('forum'=>$cm->instance);
        $this->posts       = 1;
        $this->replys      = 2;
        $this->courseid    = $cm->course;

        //  Prepare all the regex's for insert into the post page hashtree
        $this->post_to_get = array('message[itemid]', 'forum', 'discussion', 'parent', 'userid', 'groupid', 'edit', 'reply', 'timestart', 'timeend', 'sesskey', '_qf__mod_forum_post_form', 'course');
        $post_regex = array();

        $search  = array('[', ']');
        $replace = array('\[', '\]');

        foreach($this->post_to_get as $name) {
            $regex_name = str_replace($search, $replace, $name);
            $post_regex[] = new regex("$this->name Get $name", $name, '<input.*?name="'.$regex_name.'".*?value="(.*?)".*?\/>');
        }
        $this->post_regex = $post_regex;

        //  Prepare the vars to post to the site
        $this->post_arguments = array(
            'MAX_FILE_SIZE'   => 512000,
            'message[text]'   => 'testing ${username} 456',
            'message[format]' => 1,
            'subscribe'       => 0,
            'submitbutton'    => 'Post to forum'
        );

        foreach($this->post_to_get as $name) {
            $this->post_arguments[$name] = '${'.$name.'}';
        }

        $this->forum_discuss  = 'mod/forum/discuss.php';
        $this->discuss_params = array('d'=>'${discussionid}');
        $this->discuss_regex  = array(new regex("$this->name Find random discussion", 'discussionid', 'discuss\.php\?d=(.+?)["\']>testing', '0'));
        $this->reply_regex    = array(new regex("$this->name Find random reply", 'replyid', '\/mod\/forum\/post\.php\?reply=([^\\\'"]*?)["\\\']', '0'));
        $this->reply_params   = array('reply'=>'${replyid}', 'draft'=>0);

        $this->forum_startup();
    }

    function forum_startup($remove_id_in_reply=false) {
        $this->test_startup();

        //  View forum page
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' View Forum', $this->forum_view, $this->view_params));

        //  Make a post (to insure there is at least one post), find a random discussion, find a reply, make a post
        for($i=1; $i<=$this->posts; $i++) {
            //  Store the id if it hasn't yet been removed, else put it back in if we are looping around again
            if(!empty($remove_id_in_reply) && !empty($this->post_arguments['id'])) {
                $id = $this->post_arguments['id'];
            } else if(!empty($remove_id_in_reply) && empty($this->post_arguments['id']) && !empty($id)) {
                $this->post_arguments['id'] = $id;
            }

            //  View forum post page and regex's to get data
            $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' View First Discussion Post Data', $this->forum_post, $this->post_params, false, $this->post_regex));

            //  Change the subject of the post
            $this->post_arguments['subject'] = 'testing ${username}';

            //  Post new discussion
            $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' Post to Discussion', $this->forum_post, $this->post_arguments, (object) array('method' => 'POST')));

            //  Add in the loopconroller
            $this->threadgroup_hashtree->add_child(new loopcontroller($this->name, $this->replys, $loop_forever='false'));

            //  Add in the loopconroller hashtree
            $loopcontroller_hashtree = new hashtree();

            //  Find random discussion
            $loopcontroller_hashtree->add_child(new httpsampler($this->name.' Find random discussion', $this->forum_view, $this->view_params, false, $this->discuss_regex));

            //  View discussion
            $loopcontroller_hashtree->add_child(new httpsampler($this->name.' View Random Discussion & find random reply', $this->forum_discuss, $this->discuss_params, false, $this->reply_regex));

            //  View random post page passing regex's to get data from
            $loopcontroller_hashtree->add_child(new httpsampler($this->name.' View Random Reply & get post data', $this->forum_post, $this->reply_params, false, $this->post_regex));

            //  Change the subject of the reply
            $this->post_arguments['subject'] = 'Reply to post ${replyid}.';
            if(!empty($remove_id_in_reply) && isset($this->post_arguments['id'])) {
                unset($this->post_arguments['id']);
            }

            //  Post to random reply
            $loopcontroller_hashtree->add_child(new httpsampler($this->name.' Post to Random Reply', $this->forum_post, $this->post_arguments, (object) array('method' => 'POST')));

            $this->threadgroup_hashtree->add_child($loopcontroller_hashtree);

        }
        //  Now add in random timer element
        $this->threadgroup_hashtree->add_child(new random_timer($this->name));

        $this->convert_to_xml_element();
    }
}

class results_collector extends main_element {
    function __construct($name, $guiclass) {
        parent::__construct('ResultCollector', $guiclass, 'ResultCollector', $name);
        $this->add_child(new boolprop('ResultCollector.error_logging', 'false'));
        $obj = new xml_element('objProp');
        $obj->add_child(new xml_element('name', false, 'saveConfig'));
        $value = new xml_element('value', array('class'=>'SampleSaveConfiguration'));
        $value->add_child(new xml_element('time',                               false, 'true'));
        $value->add_child(new xml_element('latency',                            false, 'true'));
        $value->add_child(new xml_element('timestamp',                          false, 'true'));
        $value->add_child(new xml_element('success',                            false, 'true'));
        $value->add_child(new xml_element('label',                              false, 'true'));
        $value->add_child(new xml_element('code',                               false, 'true'));
        $value->add_child(new xml_element('message',                            false, 'true'));
        $value->add_child(new xml_element('threadName',                         false, 'true'));
        $value->add_child(new xml_element('dataType',                           false, 'true'));
        $value->add_child(new xml_element('encoding',                           false, 'false'));
        $value->add_child(new xml_element('assertions',                         false, 'true'));
        $value->add_child(new xml_element('subresults',                         false, 'true'));
        $value->add_child(new xml_element('responseData',                       false, 'false'));
        $value->add_child(new xml_element('samplerData',                        false, 'false'));
        $value->add_child(new xml_element('xml',                                false, 'true'));
        $value->add_child(new xml_element('fieldNames',                         false, 'false'));
        $value->add_child(new xml_element('responseHeaders',                    false, 'false'));
        $value->add_child(new xml_element('requestHeaders',                     false, 'false'));
        $value->add_child(new xml_element('responseDataOnError',                false, 'false'));
        $value->add_child(new xml_element('saveAssertionResultsFailureMessage', false, 'false'));
        $value->add_child(new xml_element('assertionsResultsToSave',            false, '0'));
        $value->add_child(new xml_element('bytes',                              false, 'true'));
        $obj->add_child($value);
        $this->add_child($obj);
        $this->add_child(new stringprop('filename'));
        $this->add_sibling(new hashtree());
    }
}

class tree_results extends results_collector {
    function __construct() {
        parent::__construct('View Results Tree', 'ViewResultsFullVisualizer');
    }
}

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

        //  Now add in random timer element
        $this->threadgroup_hashtree->add_child(new random_timer($this->name));

        //  Attempt quiz page
        $regex = array(new regex("Find attempt ID", 'attempt_id', '<input.*?name="attempt".*?value="(.*?)".*?\/>'));
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' Start Attempt - page 0', 'mod/quiz/attempt.php', array('id'=>$quiz_cmid), false, $regex));

        //  Now add in random timer element
        $this->threadgroup_hashtree->add_child(new random_timer($this->name));

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

                //  Now add in random timer element
                $this->threadgroup_hashtree->add_child(new random_timer($this->name));
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

                //  Now add in random timer element
                $this->threadgroup_hashtree->add_child(new random_timer($this->name));
            }
            $page++;
        }

        //  Finish and submit all!
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' Finsih Attempt', 'mod/quiz/processattempt.php', array('attempt'=>'${attempt_id}','page'=>0,'finishattempt'=>'1','questionids'=>''), (object) array('method'=>'POST')));

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

class jmeter {
    var $xml_element_pointer;
    var $main_hashtree_element_pointer;
    var $testplan_hashtree_element_pointer;
    var $constructor;

    function __construct($jmeter_data) {
        global $CFG;

        //  Define the moodle path
        $site = str_replace('http://', '', $CFG->wwwroot);
        list($site, $path) = explode('/', $site, 2);

        define('MOODLE_PATH', $path);
        define('MOODLE_SITE', $site);
        define('USERS',  $jmeter_data['users']);
        define('LOOPS',  $jmeter_data['loops']);

        //  Start XML
        $xmlstr = "<?xml version='1.0' encoding=\"UTF-8\"?>\n".
                  "<jmeterTestPlan version=\"1.2\" properties=\"2.1\"></jmeterTestPlan>";

        // create the SimpleXMLElement object with an empty <book> element
        $this->xml_element_pointer = new SimpleXMLElement($xmlstr);

        $this->main_hashtree_element_pointer = $this->add_hashTree_to_xml_dom($this->xml_element_pointer);

        $this->constructor = new simple_xml_constructor($this->main_hashtree_element_pointer);

        //  Main testplan element
        $this->constructor->add_child(new testplan());

        //  Now we need to generate the hashTree under the testplan. This is
        //  where all our test threads go
        $this->testplan_hashtree_element_pointer = $this->add_hashTree_to_xml_dom($this->main_hashtree_element_pointer);

        //  Now we need to create the $this->testplan_hashtree_constructor
        $this->testplan_hashtree_constructor =  new simple_xml_constructor($this->testplan_hashtree_element_pointer);

        //  Now we've got to add in any global stuff for the test(s) selected
        $this->testplan_hashtree_constructor->add_child(new csv_dataset('csv_file.csv', 'username,password'));

        //  Add in the HTTP Defaults
        $this->testplan_hashtree_constructor->add_child(new http_request_defaults());

        //  Add the forum testplan
        if(!empty($jmeter_data['forum'])) {
            foreach($jmeter_data['forum'] as $forum) {
                $this->testplan_hashtree_constructor->add_child(new forum_test($forum));
            }
        }

        //  Add the quiz testplan
        if(!empty($jmeter_data['quiz'])) {
            foreach($jmeter_data['quiz'] as $quiz) {
                $quiz_xml = new quiz_test($quiz->cmid);
                if(empty($quiz_xml->failed)) {
                    $this->testplan_hashtree_constructor->add_child($quiz_xml);
                } else {
                    $this->testplan_hashtree_constructor->add_child(new random_timer("Failed to insert quiz $quiz->cmid due to $quiz_xml->error", 'false'));
                }
            }
        }

        //  Now add in the listeners
        $this->testplan_hashtree_constructor->add_child(new tree_results());
    }

    function add_hashTree_to_xml_dom($xml_parent_pointer) {
        return $xml_parent_pointer->addChild('hashTree');
    }

    function get_xml() {
        return $this->xml_element_pointer;
    }
}

    require_once(dirname(__FILE__) . '/../../../config.php');
    require_once($CFG->libdir.'/adminlib.php');

    //  Check to see if we have had the form posted, if so we want to send the zip file!
    if (!empty($_POST['action']) && $_POST['action'] == 'users') {

        $user_count = intval($_POST['users']);

        if(empty($user_count)) {
            print_error('You have not specified how many users to test with');
        }

        $csv = '';
        for($i=1; $i<=$user_count; $i++) {
            //  We need to produce a user row.
            $username = "testaccount$i";
            $password = "password$i";

            //  We should only add the users if we are running moodle tests.
            if(!empty($_POST['generate_users'])) {
                $user = new stdClass();
                $user->username    = $username;
                $user->firstname   = 'Test';
                $user->lastname    = "Account [AC$i]";
                $user->email       = "$username@test.com";
                $user->country     = 'GB';
                $user->mnethostid  = $CFG->mnet_localhost_id;
                $user->lang        = $CFG->lang;
                $user->confirmed   = 1;
                $user->auth        = 'manual';
                $user->firstaccess = time();
                $user->password    = hash_internal_user_password($password);
                $user->trackforums = 1;

                //  If we have been asked to generate the moodle accounts generate them
                //  now. First we need to check the account doesn't already exist
                if(!$DB->get_record('user', array('email' => $user->email))) {
                    $DB->insert_record('user', $user);
                }
            }

            $csv .= "{$username},{$password}\n";
        }

        $jmeter_data = array();

        //  Now we need to add the user and loop info into the jmeter data
        $jmeter_data['users'] = $user_count;
        $jmeter_data['loops'] = intval($_POST['loops']);
        //$jmeter_data['login'] = $_POST['user_type'];

        //  Now we need to work out which activities we are doing
        foreach($_POST['data'] as $type => $data) {
            //  Lazyness!!!!!
            $data = (object) $data;

            //  Now we need to check what the user has requested
            if(!empty($data->all)) {
                //  The user wants to do all of this type!
                $jmeter_data[$type] = $_SESSION['loadtesting_data'][$type];
            } else if(!empty($data->count)) {
                //  The user has selected to do x random selection of the type
                $count = $_SESSION['loadtesting_data']["{$type}_count"];

                //  We need to do some bound checking to make sure they haven't
                //  requested to do more activities than there is
                if($data->count >= $count) {
                    //  We actally want to do all activities of this type
                    $jmeter_data[$type] = $_SESSION['loadtesting_data'][$type];
                } else {
                    //  We want to select a random $count of the activity type
                    $selected = array();
                    for($i=1; $i<=$data->count; $i++) {
                        $rand  = rand(0, $count-1);
                        while(in_array($rand, $selected)) {
                            $rand  = rand(0, $count-1);
                        }
                        $jmeter_data[$type][] = $_SESSION['loadtesting_data'][$type][$rand];
                    }
                }
            } else {
                //  We don't want to do random ones, and we haven't said how many we
                //  want to do. Check to see if the user has selected specific ones.
                if(!empty($data->todo)) {
                    //  The user has selected some activities....
                    foreach($data->todo as $cmid) {
                        //  Find the object... this could be slow should do a different way!
                        foreach($_SESSION['loadtesting_data'][$type] as $activity) {
                            if($activity->cmid == $cmid) {
                                $jmeter_data[$type][] = $activity;
                            }
                        }
                    }
                }
            }
        }

        //  Make the jQuery script
        try {
            // insert the header to tell the browser how to read the document
            //header("Content-type: text/html");
            $jmeter = new jmeter($jmeter_data);

            // print the SimpleXMLElement as a XML well-formed string
            $dom_sxe = dom_import_simplexml($jmeter->get_xml());

            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->formatOutput = true;
            $dom_sxe = $dom->importNode($dom_sxe, true);
            $dom_sxe = $dom->appendChild($dom_sxe);

            $xml = $dom->saveXML();
            //$xml = $jmeter->get_xml()->asXML();

            //  Now we should have the CSV and the XML.
            //  Place them both into a zip file and present to the user
            $zip = new ZipArchive;

            $filename = 'jmeter.zip';
            $filepath = "{$CFG->dataroot}/temp/$filename";

            $res = $zip->open($filepath, ZipArchive::CREATE);
            if ($res === TRUE) {
                $zip->addFromString('script.jmx', $xml);
                $zip->addFromString('csv_file.csv', $csv);
                $zip->close();

                //  Now present the zip to the user
                header("Content-type: application/force-download\n");
                header("Content-disposition: attachment; filename=\"$filename\"\n");
                header("Content-transfer-encoding: binary\n");
                header("Content-length: " . filesize($filepath) . "\n");
                header("Cache-Control: "); // Required for IE Open
                header("Pragma: "); // Required for IE Open

                //send file contents
                $fp=fopen($filepath, "r");
                fpassthru($fp);
                fclose($fp);

                // Deletefile (ignore any errors)
                @unlink($filepath);
                exit;
            } else {
                throw new Exception('failed to create zip archive');
            }
        } catch (Exception $e) {
            print_error($e);
        }
    }











    // Print the header.
    admin_externalpage_setup('reportloadtesting');
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('loadtesting', 'report_loadtesting'));
    echo $OUTPUT->box_start();

    // Check permission to see report
    require_capability('moodle/site:viewreports', get_context_instance(CONTEXT_SYSTEM, SITEID));

    //  Check to see if we've had a form posted
    if(!empty($_POST['action']) && $_POST['action'] == 'cat') {
        $cat_id = intval($_POST['cat']);

        //  Now we have a category we want to loop through all the courses in the cat
        //  and get any forums and quizes.
        $forum_mod_id   = $DB->get_field('modules', 'id', array('name' => 'forum'));
        $quiz_mod_id    = $DB->get_field('modules', 'id', array('name' => 'quiz'));

        $total_forum_count   = 0;
        $total_quiz_count    = 0;

        $all_forums   = array();
        $all_quizes   = array();

        $course_rs = $DB->get_recordset('course', array('category' => $cat_id));
        foreach($course_rs as $rec) {
            //  Check for forums
            $forums = $DB->get_records_sql("
                SELECT
                    cm.id            as cmid,
                    forum.name       as name,
                    forum.id         as id,
                    course.fullname  as course_name,
                    course.id        as course_id
                FROM
                    {$CFG->prefix}course_modules cm
                JOIN
                    {$CFG->prefix}forum forum
                ON
                    cm.instance = forum.id
                JOIN
                    {$CFG->prefix}course course
                ON
                    course.id = cm.course
                WHERE
                    cm.module = $forum_mod_id
                AND
                    cm.visible = 1
                AND
                    cm.course = $rec->id
            ");

            $quizes = $DB->get_records_sql("
                SELECT
                    cm.id            as cmid,
                    quiz.name        as name,
                    quiz.id          as id,
                    course.fullname  as course_name,
                    course.id        as course_id
                FROM
                    {$CFG->prefix}course_modules cm
                JOIN
                    {$CFG->prefix}quiz quiz
                ON
                    cm.instance = quiz.id
                JOIN
                    {$CFG->prefix}course course
                ON
                    course.id = cm.course
                WHERE
                    cm.module = $quiz_mod_id
                AND
                    cm.visible = 1
                AND
                    cm.course = $rec->id
            ");

            //  Now we have all the activities we need to check that the quizes have
            //  questions assigned and those questions are supported
            if(!empty($quizes)) {
                foreach($quizes as $index => $quiz) {
                    //  Load this quizes questions
                    $questions = quiz_get_questions($quiz->id);
                    $unsupported_question_type = false;

                    if(!empty($questions)) {
                        $unsupported_question_type = quiz_check_for_unsupported_questions($questions);
                    }

                    if(empty($questions) || !empty($unsupported_question_type)) {
                        //  Quiz doesn't contain any questions, or contains an unsupported type remove it
                        unset($quizes[$index]);
                    }
                }
            }

            $course_forum_count   = !empty($forums)   ? count($forums)   : 0;
            $course_quiz_count    = !empty($quizes)   ? count($quizes)   : 0;

            $total_forum_count   += $course_forum_count;
            $total_quiz_count    += $course_quiz_count;

            if(!empty($forums)) {
                $all_forums = array_merge($all_forums, $forums);
            }
            if(!empty($quizes)) {
                $all_quizes = array_merge($all_quizes, $quizes);
            }
        }

        //  Check if we have any forums/quizes/activities if not redirect back to selection screen
        if(empty($total_forum_count) && empty($total_quiz_count)) {
            redirect($_SERVER['PHP_SELF'], 'This category has no activies. Redirect to selection screen', 3);
            exit();
        }

        echo '<div style="border:1px solid #ccc;padding:5px; min-width:500px;margin-bottom:5px">';
        echo "Total Forums: $total_forum_count<br/>";
        echo "Total Quizes: $total_quiz_count<br/>";
        echo '</div>';

        //  Now ask the admin if they would like to test all forums.
        //  If this is the case we will need to generate a user in the CSV
        //  for which ever has the most.
        $max_users = $total_forum_count;

        $_SESSION['loadtesting_data'] = array(
            'category'      => $cat_id,
            'forum'         => $all_forums,
            'quiz'          => $all_quizes,
            'forum_count'   => $total_forum_count,
            'quiz_count'    => $total_quiz_count,
        );

        ?>
        <style>
            .center {
                text-align : center;
            }
            .small {
                font-size : 0.8em;
            }
            .border {
                border : 1px solid #CCC;
            }
            .bot_border {
                border-bottom : 1px solid #CCC;
            }
            .input {
                font-size : 0.8em;
                padding   : 3px;
            }
            .options {
                height : 100px;
                width  : 100%;
            }
            .optgroup {
                font-weight   : normal;
                color         : #C00;
                padding-right :5px
            }
            .opt {
                color : #000;
            }
        </style>
            <div style="border:1px solid #ccc;padding:5px;">
                <form name="users" method="POST">
                    <input type="hidden" name="action" value="users" />
                    <div style="float:left"><input type="checkbox" name="generate_users" value="1"/></div>
                    <div style="padding-bottom:15px;">Generate user accounts</div>
                    <div style="float:left;padding-right:5px;"><input class="input border" size="2" type="input" name="users" value="1"/></div>
                    <div style="padding-bottom:15px;">How many users to test with</div>
                    <div style="float:left;padding-right:5px;"><input class="input border" size="2" type="input" name="loops" value="1"/></div>
                    <div style="padding-bottom:15px;">How many times to loop the tests</div>

                    <div style="padding-bottom:15px;">
                        <table cellpadding="5" cellspacing="0" class="border" style="width:100%">
                            <tr>
                                <td class="center small bot_border">Activity Type</td>
                                <td class="center small bot_border">Test All</td>
                                <td class="center small bot_border">Test x</td>
                                <td class="center small bot_border">Activities to Test</td>
                            </tr>
                            <?php
                                function create_selector_row($type, $type_activities) {
                                    $title = $type;
                                    $type = strtolower($type);
                                    ?>
                                        <tr>
                                            <td class="center small"><?php echo $title?></td>
                                            <td class="center"><input class="input border" type="checkbox" name="data[<?php echo $type?>][all]" value="<?php $key = "{$type}_count"; echo $_SESSION['loadtesting_data'][$key]?>"/></td>
                                            <td class="center"><input class="input border" type="text" size="3" name="data[<?php echo $type?>][count]"/></td>
                                            <td>
                                                <select name="data[<?php echo $type?>][todo][]" class="input border options" multiple="multiple">
                                                    <?php
                                                        //  Loop through the activity and prep for
                                                        //  displaying in select list
                                                        $grouped = array();
                                                        foreach($type_activities as $activity) {
                                                            $grouped[$activity->course_id][] = $activity;
                                                        }
                                                        //  Now loop through the grouped activites
                                                        //  and display in course categories
                                                        foreach($grouped as $activities) {
                                                            echo "<optgroup label=\"{$activities[0]->course_name}\" class=\"optgroup\">\n";
                                                                foreach($activities as $activity) {
                                                                    echo "<option class=\"opt\" value=\"{$activity->cmid}\">{$activity->name}</option>\n";
                                                                }
                                                            echo "</optgroup>\n";
                                                        }

                                                    ?>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php
                                }
                                $activities = array(
                                                'Forum'   => $_SESSION['loadtesting_data']['forum'],
                                                'Quiz'    => $_SESSION['loadtesting_data']['quiz']
                                            );
                                foreach($activities as $type => $type_activities) {
                                    if(!empty($type_activities)) {
                                        create_selector_row($type, $type_activities);
                                    }
                                }
                            ?>
                        </table>
                    </div>
                    <div><input type="submit" value="generate tests" /></div>
                </form>
            </div>
        <?php
    } else {
        //  Make the form
        ?>
            <form name="cat_selector" method="POST">
                <select name="cat">
                    <option value="">Please Select</option>
                    <?php
                        $cat_rs = $DB->get_recordset('course_categories');
                        foreach($cat_rs as $rec) {
                            echo "<option value=\"$rec->id\">$rec->name</option>\n";
                        }
                    ?>
                </select>
                <input type="hidden" name="action" value="cat" />
                <input type="submit" value="select category" />
            </form>
        <?php
    }

    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();

?>
