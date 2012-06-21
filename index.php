<?php

    /**
     * Load Testing Setup
     *
     * @copyright &copy; 2007 The Open University
     * @author j.e.c.brisland@open.ac.uk
     * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
     * @package loadtesting
     */

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
        } else {
            $this->attributes['name'] = 'NotSet';
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
        $defaults->domain            = preg_replace('_https?://_', '', $CFG->wwwroot);

        //  This is for dev only, won't do anything on live!
        //  Find the first /
        if(($pos = strpos($defaults->domain, '/')) !== false) {
            $defaults->domain = substr($defaults->domain, 0, $pos);
        }

        $defaults->port              = false;
        $defaults->connect_timeout   = false;
        $defaults->response_timeout  = false;
        $defaults->protocol          = false;
        $defaults->contentEncoding   = false;
        $defaults->path              = false;

        if ($defaults->domain != $CFG->wwwroot) {
            $defaults->protocol = preg_replace('_^(https?)://.*$_', '$1', $CFG->wwwroot);
        }

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
        $defaults->name              = 'SomeName';
        $defaults->follow_redirects  = 'true';
        $defaults->use_keepalive     = 'false';
        $defaults->auto_redirects    = 'true';
        $defaults->do_multipart_post = 'false';
        $defaults->file_name         = false;
        $defaults->file_field        = false;
        $defaults->mimetype          = false;
        $defaults->monitor           = 'false';
        $defaults->embedded_url_re   = false;
        $defaults->image_parser      = IMAGE_PARSER ? 'true' : 'false';
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
        $this->add_child(new stringprop("{$htstr}.name",              'SomeName'));
        $this->add_child(new   boolprop("{$htstr}.image_parser",      $properties->image_parser));
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

class randomvariable extends main_element {
    function __construct($name, $min, $max, $outputformat) {
        $cmstr = 'RandomVariableConfig';
        //  Create the CookieManager
        parent::__construct($cmstr, 'TestBeanGUI', $cmstr, 'Random Variable');

        //  Create it's child elements
        $this->add_child(new stringprop("variableName", $name));
        $this->add_child(new stringprop("minimumValue", $min));
        $this->add_child(new stringprop("maximumValue", $max));
        $this->add_child(new stringprop("outputFormat", $outputformat));
        $this->add_child(new boolprop("perThread", 'true'));

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

class moodle_logout extends xml_element {
    function __construct($name_part) {
        $arguments = array('sesskey'=>'${sesskey}');

        //  Create a new HTTPSampler
        $sampler = new httpsampler($name_part.' Logout from site', 'login/index.php', $arguments, (object) array('method'=>'GET'));
        $this->copy_to_this($sampler);
    }
}

class master_test extends xml_element {
    var $threadgroup;
    var $threadgroup_hashtree;
    var $name;
    var $courseid;
    var $wordgenerator;

    function  __construct() {
        $this->wordgenerator = new LoremIpsumGenerator;
    }

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

        //  Add in the URLRewrite Modifier which automatically adds in sesskey to all post or get forms
        $this->threadgroup_hashtree->add_child(new url_rewrite($this->name));

        //  View course page
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' View Course', 'course/view.php', array('id'=>$this->courseid)));

    }

    protected function test_finish() {
        // Perform logout
        $this->threadgroup_hashtree->add_child(new moodle_logout($this->name));

        //  Now add in random timer element
        $this->threadgroup_hashtree->add_child(new random_timer($this->name));
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

class summary_report extends results_collector {
    function __construct() {
        parent::__construct('Summary Report', 'SummaryReport');
    }
}

class aggregate_report extends results_collector {
    function __construct() {
        parent::__construct('Aggregate Report', 'StatVisualizer');
    }
}

class aggregate_graph extends results_collector {
    function __construct() {
        parent::__construct('Aggregate Graph', 'StatGraphVisualizer');
    }
}

class graph_results extends results_collector {
    function __construct() {
        parent::__construct('Graph Results', 'GraphVisualizer');
    }
}

abstract class test_setup {

    /**
     * Store of the course_modules found for this test class
     */
    public $cms = array();

    /**
     * The name of the test class
     */
    private $name;

    public $count = 0;

    /**
     * Constructor for the test_setup class
     */
    public function __construct() {
        $this->name = preg_replace('/_test_setup$/', '', get_class($this));
    }

    /**
     * Retrieve a list of the course module instances for the specified course
     *
     * @param integer $course The course ID number
     * @return array The list of course module instances
     */
    public function get_cms($course) {
        global $DB;

        $cms = $DB->get_records_sql('
            SELECT
                cm.id           AS cmid,
                mod.name        AS name,
                mod.id          AS id,
                course.fullname AS course_name,
                course.id       AS course_id
            FROM {course_modules} cm
            JOIN {' . $this->get_table_name() . '} mod
            ON mod.id = cm.instance
            JOIN {course} course
            ON course.id = cm.course
            JOIN {modules} modules
            ON cm.module = modules.id
            WHERE cm.visible = 1
            AND cm.course = ?
            AND modules.name = ?
            ', array($course, $this->get_name()));
        return $cms;
    }

    /**
     * The name of the test class
     *
     * @return String the name of the course module
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * The name of the table in the moodle database for this course module.
     * This is used in the get_cms() function
     *
     * @return String the name of the table for the course module
     */
    public function get_table_name() {
        return $this->get_name();
    }

    /**
     * The test class name
     *
     * @return String the name of the test class to use
     */
    public function get_test_name() {
        return $this->get_name() . '_test';
    }

    /**
     * Any optional settings
     *
     * @return String HTML to output any optional settings
     */
    public function optional_settings() {
        return '';
    }

    public function process_optional_settings($data) {
    }

    /**
     * Create a test plan for the specified activities
     *
     * @param object $jmeter The jmeter class
     * @param array $activities The list of activities
     * @return object The completed test plan
     */
    public function create_testplan($jmeter, $activities) {
        $class = $this->get_test_name();
        foreach($activities as $activity) {
            $jmeter->testplan_hashtree_constructor->add_child(new $class($activity));
        }
    }

}

class jmeter {
    var $xml_element_pointer;
    var $main_hashtree_element_pointer;
    var $testplan_hashtree_element_pointer;
    var $constructor;

    function __construct($jmeter_data) {
        global $CFG, $testclasses;

        //  Define the moodle path
        $site = preg_replace('_https?://_', '', $CFG->wwwroot);
        list($site, $path) = explode('/', $site, 2);

        define('MOODLE_PATH', $path);
        define('MOODLE_SITE', $site);
        define('USERS',  $jmeter_data['users']);
        define('LOOPS',  $jmeter_data['loops']);
        define('IMAGE_PARSER', $jmeter_data['image_parser']);

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

        foreach ($jmeter_data['activities'] as $type => $activities) {
            $testclasses[$type]->create_testplan($this, $activities);
        }

        //  Now add in the listeners
        $this->testplan_hashtree_constructor->add_child(new tree_results());
        $this->testplan_hashtree_constructor->add_child(new summary_report());
        $this->testplan_hashtree_constructor->add_child(new aggregate_report());
        $this->testplan_hashtree_constructor->add_child(new aggregate_graph());
        $this->testplan_hashtree_constructor->add_child(new graph_results());
    }

    function add_hashTree_to_xml_dom($xml_parent_pointer) {
        return $xml_parent_pointer->addChild('hashTree');
    }

    function get_xml() {
        return $this->xml_element_pointer;
    }
}

    require_once(dirname(__FILE__) . '/../../config.php');
    require_once($CFG->dirroot.'/report/loadtesting/LoremIpsum.class.php');
    require_once($CFG->libdir.'/adminlib.php');
    require_once($CFG->libdir.'/enrollib.php');
    require_once($CFG->dirroot . '/enrol/self/lib.php');

    // The list of available test classes
    $testclasses = array();
    // Include all of the possible test classes
    $classdir = dirname(__FILE__) . '/classes/';
    $dirlist = scandir($classdir);
    foreach ($dirlist as $class) {
        $testclassdir = $classdir . $class;
        if (is_dir($testclassdir)) {
            if (file_exists($testclassdir . '/lib.php')) {
                require_once($testclassdir . '/lib.php');
                $classname = $class . '_test_setup';
                if (class_exists($classname)) {
                    $testclasses[$class] = new $classname();
                }
            }
        }
    }

    //  Check to see if we have had the form posted, if so we want to send the zip file!
    if (!empty($_POST['action']) && $_POST['action'] == 'users') {

        $user_count = intval($_POST['users']);
        $userids = array();

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
                if($existinguser = $DB->get_record('user', array('email' => $user->email))) {
                    $userids[] = $existinguser->id;
                } else {
                    $userids[] = $DB->insert_record('user', $user);
                }
            }

            $csv .= "{$username},{$password}\n";
        }

        $jmeter_data = array();

        //  Now we need to add the user and loop info into the jmeter data
        $jmeter_data['users'] = $user_count;
        $jmeter_data['loops'] = intval($_POST['loops']);
        $jmeter_data['image_parser'] = $_POST['image_parser'];
        $jmeter_data['courses'] = array();
        $jmeter_data['activities'] = array();
        //$jmeter_data['login'] = $_POST['user_type'];

        //  Now we need to work out which activities we are doing
        foreach($_POST['data'] as $type => $data) {
            //  Lazyness!!!!!
            $data = (object) $data;

            //  Now we need to check what the user has requested
            if(!empty($data->all)) {
                //  The user wants to do all of this type!
                $jmeter_data['activities'][$type] = $_SESSION['loadtesting_data'][$type];
            } else if(!empty($data->count)) {
                //  The user has selected to do x random selection of the type
                $count = $_SESSION['loadtesting_data']["{$type}_count"];

                //  We need to do some bound checking to make sure they haven't
                //  requested to do more activities than there is
                if($data->count >= $count) {
                    //  We actally want to do all activities of this type
                    $jmeter_data['activities'][$type] = $_SESSION['loadtesting_data'][$type];
                } else {
                    //  We want to select a random $count of the activity type
                    $selected = array();
                    for($i=1; $i<=$data->count; $i++) {
                        $rand  = rand(0, $count-1);
                        while(in_array($rand, $selected)) {
                            $rand  = rand(0, $count-1);
                        }
                        $jmeter_data['activities'][$type][] = $_SESSION['loadtesting_data'][$type][$rand];
                        $selected[] = $rand;
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
                                $jmeter_data['activities'][$type][] = $activity;
                            }
                        }
                    }
                }
            }

            $testclasses[$type]->process_optional_settings($data);

            // Build the complete list of courses
            if (isset($jmeter_data['activities'][$type])) {
                foreach ($jmeter_data['activities'][$type] as $element) {
                    $jmeter_data['courses'][$element->course_id] = $element->course_id;
                }
            }
        }


        if (isset($_POST['enrol']) && $_POST['enrol'] == 1) {
            $selfplugin = enrol_get_plugin('self');
            $role = $DB->get_record('role', array('shortname' => 'student'));
            foreach ($jmeter_data['courses'] as $courseid) {
                $course = $DB->get_record('course', array('id' => $courseid));
                // Check whether the loadtesting enrolment mechanism exists
                $instances = enrol_get_instances($course->id, false);
                $enrolinstance = null;

                foreach ($instances as $instance) {
                    if ($instance->enrol === 'self' && $instance->name === 'loadtesting') {
                        $enrolinstance = $instance;
                    }
                }

                if ($enrolinstance === null) {
                    // Create a new enrol instance
                    $fields = array(
                        'status'        => 1,
                        'name'          => 'loadtesting',
                    );
                    $enrolinstance = $selfplugin->add_instance($course, $fields);
                    $enrolinstance = $DB->get_record('enrol', array('id' => $enrolinstance));
                }

                // Ensure that it's enabled
                $selfplugin->update_status($enrolinstance, ENROL_INSTANCE_ENABLED);

                // Assign all of the test users to the instance
                foreach ($userids as $userid) {
                    $selfplugin->enrol_user($enrolinstance, $userid, $role->id);
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
    require_capability('moodle/site:viewreports', context_system::instance());

    //  Check to see if we've had a form posted
    if(!empty($_POST['action']) && $_POST['action'] == 'cat') {
        $cat_id = intval($_POST['cat']);

        //  Now we have a category we want to loop through all the courses in the cat
        //  and get any activities
        $course_rs = $DB->get_recordset('course', array('category' => $cat_id));
        $totalcount = 0;
        foreach($course_rs as $rec) {
            foreach ($testclasses as $testclass) {
                $cms = $testclass->get_cms($rec->id);
                $testclass->cms = array_merge($testclass->cms, $cms);
                $count = count($cms);
                $testclass->count += $count;
                $totalcount += $count;
            }
        }

        //  Check if we have any activities if not redirect back to selection screen
        if(!$totalcount >= 1) {
            redirect($_SERVER['PHP_SELF'], 'This category has no activies. Redirect to selection screen', 3);
            exit();
        }

        $_SESSION['loadtesting_data'] = array();

        echo '<div style="border:1px solid #ccc;padding:5px; min-width:500px;margin-bottom:5px">';
        foreach ($testclasses as $testclass) {
            echo "Total " . $testclass->get_name() . ": " . $testclass->count . "<br/>";
            $_SESSION['loadtesting_data'][$testclass->get_name()] = $testclass->cms;
            $_SESSION['loadtesting_data'][$testclass->get_name() . '_count'] = $testclass->count;
        }
        echo '</div>';

        //  Now ask the admin if they would like to test all activities
        //  If this is the case we will need to generate a user in the CSV
        //  for which ever has the most.
        $_SESSION['loadtesting_data']['category'] = $cat_id;

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
                    <div style="float:left;padding-right:5px;"><input type="checkbox" name="generate_users" id="generate_users" value="1"/></div>
                    <div style="padding-bottom:15px;"><label for="generate_users">Generate user accounts</label></div>

                    <div style="float:left;padding-right:5px;"><input type="checkbox" name="enrol" id="enrol" value="1"/></div>
                    <div style="padding-bottom:15px;"><label for="enrol">Automatically enrol created users in relevant courses</label></div>

                    <div style="float:left;padding-right:5px;"><input class="input border" size="2" type="input" name="users" value="1"/></div>
                    <div style="padding-bottom:15px;">How many users to test with</div>

                    <div style="float:left;padding-right:5px;"><input class="input border" size="2" type="input" name="loops" value="1"/></div>
                    <div style="padding-bottom:15px;">How many times to loop the tests</div>

                    <div style="float:left;padding-right:5px;"><input type="checkbox" name="image_parser" id="image_parser" value="1"/></div>
                    <div style="padding-bottom:15px;"><label for="image_parser">Retrieve embedded resources from HTML</label></div>

                    <div style="padding-bottom:15px;">
                        <table cellpadding="5" cellspacing="0" class="border" style="width:100%">
                            <tr>
                                <td class="center small bot_border">Activity Type</td>
                                <td class="center small bot_border">Test All</td>
                                <td class="center small bot_border">Test x random activities</td>
                                <td class="center small bot_border">Activities to Test</td>
                                <td class="center small bot_border">Options</td>
                            </tr>
                            <?php
                                function create_selector_row($type, $type_activities) {
                                    global $testclasses;
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
                                            <td class="small">
                                                <?php
                                                    echo $testclasses[$type]->optional_settings();
                                                ?>
                                            </td>
                                        </tr>
                                    <?php
                                }
                                foreach ($testclasses as $testclass) {
                                    if ($testclass->count) {
                                        create_selector_row($testclass->get_name(), $testclass->cms);
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
