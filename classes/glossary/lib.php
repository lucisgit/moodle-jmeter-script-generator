<?php

class glossary_test extends master_test {
    private $viewurl;
    private $viewparams;
    private $viewregex;

    private $addurl;
    private $addparams;
    private $addargs;
    private $addargstoget;
    private $addregex;

    private $editformargs;
    private $editargs;

    private $deleteurl ;
    private $deleteparams;
    private $confirmdeleteparams;

    var $entries = 5;

    function __construct($glossary) {
        global $DB;

        parent::__construct();
        //  Get course modules record
        $cm                 = $DB->get_record('course_modules', array('id' => $glossary->cmid));
        $this->name         = "[GLOSSARY {$glossary->cmid}:{$glossary->name}]";

        // Various URLs and base params
        $this->viewurl      = 'mod/glossary/view.php';
        $this->viewparams   = array('id'=>$cm->id);

        $this->addurl       = 'mod/glossary/edit.php';
        $this->addparams    = array('cmid'=>$cm->id);

        $this->deleteurl    = 'mod/glossary/deleteentry.php';
        $this->deleteparams = array(
            'mode'      => 'delete',
            'id'        => $cm->id,
            'entry'     => '${entryid}',
            'prevmode'  => '',
            'hook'      => 'ALL',
        );
        $this->confirmdeleteparams = $this->deleteparams;
        $this->confirmdeleteparams['confirm'] = 1;

        $search  = array('[', ']');
        $replace = array('\[', '\]');

        // Regex for adding/editing the glossary entry
        $this->addargstoget = array('id', 'sesskey', '_qf__mod_glossary_entry_form', 'definition_editor[itemid]');
        $addregex = array();

        foreach($this->addargstoget as $name) {
            $regex_name = str_replace($search, $replace, $name);
            $addregex[] = new regex("$this->name Get $name", $name, '<input.*?name="'.$regex_name.'".*?value="(.*?)".*?\/>');
        }
        $this->addregex = $addregex;

        $this->courseid    = $cm->course;

        //  Prepare the vars to post to the site
        $this->addargs = array(
            'cmid'                  => $cm->id,
            'MAX_FILE_SIZE'         => 512000,
            'definition_editor[text]'      => $this->wordgenerator->getContent(50, 'html'),
            'definition_editor[format]'    => 1,
            'usedynalink'           => 1,
            'submitbutton'          => 'Save changes'
        );

        // Now add the regex to the add variables
        foreach($this->addargstoget as $name) {
            $this->addargs[$name] = '${'.$name.'}';
        }
        $this->addargs['definition[itemid]'] = '${definition_editor[itemid]}';

        // The edit variables
        $this->editformargs = array(
            'cmid'  => $cm->id,
            'id'    => '${entryid}',
        );

        $this->editargs = $this->addargs;
        $this->editargs['id'] = '${entryid}';
        $this->editargs['definition_editor[text]'] = $this->wordgenerator->getContent(50, 'html');

        // Regex to get the glossary entry id from the returned URL
        $this->viewregex = array();
        $this->viewregex[] = new regex("$this->name Get entryid", 'entryid', '^.*hook=(.*)[^0-9]?$', false, '$1$', false, 'URL');

        $this->glossary_startup();
    }

    function glossary_startup() {
        $this->test_startup();

        $this->threadgroup_hashtree->add_child(new randomvariable('threaduser', '0000', '9999', 'threaduser_0000'));

        //  View glossary page
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' View Glossary', $this->viewurl, $this->viewparams));

        //  Make a post (to insure there is at least one post), find a random discussion, find a reply, make a post
        for($i=1; $i <= $this->entries; $i++) {
            // The concept name
            $concept = 'concept_${threaduser}';

            // View glossary add page
            $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' Create new entry form', $this->addurl, $this->addparams, false, $this->addregex));

            $this->addargs['concept'] = $concept;
            $this->addargs['aliases'] = 'keyword_${threaduser}';

            // Create new entry
            $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' Create new entry (submitted)', $this->addurl, $this->addargs, (object) array('method' => 'POST'), $this->viewregex));

            // Edit it
            $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' Editing Entry (form)', $this->addurl, $this->editformargs, false, $this->addregex));
            $this->editargs['concept'] = $concept;
            $this->editargs['aliases'] = 'keyword_${threaduser}';
            $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' Editing Entry (submitted)', $this->addurl, $this->editargs, (object) array('method' => 'POST'), $this->viewregex));

            // Delete it
            $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' Deleting Entry (form)', $this->deleteurl, $this->deleteparams));
            $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' Deleting Entry (submitted)', $this->deleteurl, $this->confirmdeleteparams));
        }

        $this->test_finish();

        $this->convert_to_xml_element();
    }
}

class glossary_test_setup extends test_setup {
}
