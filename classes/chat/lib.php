<?php

class chat_test extends master_test {
    var $chat_view;
    var $view_params;
    var $chat_post;
    var $post_params;
    var $posts;
    var $newonly;
    var $post_regex;

    function __construct($chat) {
        global $DB;

        parent::__construct();
        //  Get course modules record
        $cm                = $DB->get_record('course_modules', array('id' => $chat->cmid));
        //  Get course modules record
        $this->name        = "[CHAT {$chat->cmid}:{$chat->name}]";
        $this->chat_view   = 'mod/chat/view.php';
        $this->view_params = array('id'=>$chat->cmid);

        if (!isset($chat->ajax) || !$chat->ajax) {
            $this->chat_post   = 'mod/chat/gui_basic/index.php';
            $this->post_params = array('id'=>$chat->id);
            $this->posts       = 5;
            $this->newonly     = 0;
            $this->courseid    = $cm->course;

            //  Prepare all the regex's for insert into the post page hashtree
            $this->post_to_get = array('last', 'groupid', 'sesskey');
            $post_regex = array();

            foreach($this->post_to_get as $name) {
                $post_regex[] = new regex("$this->name Get $name", $name, '<input.*?name="'.$name.'".*?value="(.*?)".*?\/>');
            }
            $this->post_regex = $post_regex;

            //  Prepare the vars to post to the site
            $this->post_arguments = array(
                'message'   => $this->wordgenerator->getContent(10, 'txt'),
                'id'        => $chat->id,
            );

            foreach($this->post_to_get as $name) {
                $this->post_arguments[$name] = '${'.$name.'}';
            }

            $this->chat_basic_startup();
        }
        else {
            $this->chat_window   = 'mod/chat/gui_ajax/index.php';
            $this->window_params = array('id'=>$chat->id);
            $this->window_regex  = array(new regex("$this->name Get SID", 'chat_sid', '"sid":"(\w+)"'));

            // chat init
            $this->chat_init     = 'mod/chat/chat_ajax.php';
            $this->init_params   = array('action'=>'init', 'chat_init'=>1, 'chat_sid'=>'${chat_sid}', 'theme'=>'undefined');
            // post chat message
            $this->chat_post     = 'mod/chat/chat_ajax.php?action=chat';
            $this->post_params = array('chat_message'=>$this->wordgenerator->getContent(10, 'txt'), 'chat_sid'=>'${chat_sid}', 'theme'=>'compact');
            // update chat
            $this->chat_update   = 'mod/chat/chat_ajax.php?action=update';
            $this->update_params = array('chat_lastrow'=>'false', 'chat_lasttime'=>0, 'chat_sid'=>'${chat_sid}', 'theme'=>'compact');

            $this->posts       = 5;
            $this->courseid    = $cm->course;

            //  Prepare all the regex's for insert into the post page hashtree
            $this->post_to_get = array('lasttime', 'lastrow');
            $post_regex = array();

            foreach($this->post_to_get as $name) {
                $post_regex[] = new regex("$this->name Get $name", 'chat_' . $name, '"'.$name.'":"(\w+)"');
            }
            $this->post_regex = $post_regex;

            //  Prepare the vars to post to the site
            $this->post_update = array(
                'chat_sid'  => '${chat_sid}',
                'theme'     => 'compact',
            );

            foreach($this->post_to_get as $name) {
                $this->post_update['chat_'.$name] = '${chat_'.$name.'}';
            }

            $this->chat_ajax_startup();
        }
    }

    function chat_basic_startup() {
        $this->test_startup();

        //  View chat page
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' View Chat page', $this->chat_view, $this->view_params));

         //  Open chat window and regex's to get data
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' View Chat window', $this->chat_post, $this->post_params, false, $this->post_regex));

        //  Add in the loopcontroller
        $this->threadgroup_hashtree->add_child(new loopcontroller($this->name, $this->posts, $loop_forever='false'));

        //  Add in the loopconroller hashtree
        $loopcontroller_hashtree = new hashtree();

        //  Post to random reply
        $loopcontroller_hashtree->add_child(new httpsampler($this->name.' Post Chat Message', $this->chat_post, $this->post_arguments, (object) array('method' => 'POST')));

        $this->post_params['newonly'] = $this->newonly;
        $this->post_params['last'] = '${last}';

        //  Refesh chat window and regex's to get data
        $loopcontroller_hashtree->add_child(new httpsampler($this->name.' View Chat window', $this->chat_post, $this->post_params, false, $this->post_regex));

        // Add loopcontroller
        $this->threadgroup_hashtree->add_child($loopcontroller_hashtree);

        $this->test_finish();

        $this->convert_to_xml_element();
    }

    function chat_ajax_startup() {
        $this->test_startup();

        //  View chat page
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' View Chat page', $this->chat_view, $this->view_params));

        //  Open chat window and regex's to get data
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' View Chat window', $this->chat_window, $this->window_params, false, $this->window_regex));

        //  Chat init
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' Init Chat', $this->chat_init, $this->init_params, (object) array('method' => 'POST')));

        //  Chat initial update
        $this->threadgroup_hashtree->add_child(new httpsampler($this->name.' Init Initial Update', $this->chat_update, $this->update_params, (object) array('method' => 'POST'), $this->post_regex));

        //  Add in the loopcontroller
        $this->threadgroup_hashtree->add_child(new loopcontroller($this->name, $this->posts, $loop_forever='false'));

        //  Add in the loopconroller hashtree
        $loopcontroller_hashtree = new hashtree();

        //  Post to random reply
        $loopcontroller_hashtree->add_child(new httpsampler($this->name.' Post Chat Message', $this->chat_post, $this->post_params, (object) array('method' => 'POST')));

        //  Refesh chat window and regex's to get data
        $loopcontroller_hashtree->add_child(new httpsampler($this->name.' Init Update', $this->chat_update, $this->post_update, (object) array('method' => 'POST'), $this->post_regex));

        // Add loopcontroller
        $this->threadgroup_hashtree->add_child($loopcontroller_hashtree);

        //  Now add in random timer element
        $this->threadgroup_hashtree->add_child(new random_timer($this->name));

        $this->convert_to_xml_element();
    }
}

class chat_test_setup extends test_setup {

    public function optional_settings() {
        return "<input class=\"input border\" type=\"checkbox\" name=\"data[" . $this->get_name() . "][ajax]\" value=\"1\"/> Use AJAX chat box";
    }

    public function process_optional_settings($data) {
        global $_SESSION;

        foreach($_SESSION['loadtesting_data'][$this->get_name()] as &$activity) {
            $activity->ajax = (isset($data->ajax)) ? $data->ajax : 0;
        }
    }
}
