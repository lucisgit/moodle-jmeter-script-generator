<?php

class forum_test extends master_test {
    var $forum_view;
    var $view_params;
    var $forum_post;
    var $posts;
    var $replys;
    var $post_regex;

    function __construct($forum) {
        global $DB;

        parent::__construct();
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
            'message[text]'   => $this->wordgenerator->getContent(50, 'html'),
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

        $this->test_finish();

        $this->convert_to_xml_element();
    }
}

class forum_test_setup extends test_setup {
}
