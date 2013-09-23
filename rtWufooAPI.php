<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of rtWufooAPI
 *
 * @author sourabh
 */
class rtWufooAPI extends WufooApiWrapper {

    public function __construct($apiKey, $subdomain, $domain = 'wufoo.com') {
        parent::__construct($apiKey, $subdomain, $domain);
    }

    public function getCommentCount($formIdentifier) {
        if (!$formIdentifier)
            return;

        $url = $this->getFullUrl('forms/' . $formIdentifier . '/comments/count');
        $this->curl = new WufooCurl();
        $response = $this->curl->getAuthenticated($url, $this->apiKey);
        $response = json_decode($response);
        return $response;
    }

    public function getPagedComments($formIdentifier, $pageSize = 25, $pageStart = 0, $entryId = null) {

        if ($entryId) {
            $url = $this->getFullUrl('forms/' . $formIdentifier . '/comments/' . $entryId);
        } else {
            $url = $this->getFullUrl('forms/' . $formIdentifier . '/comments');
        }
        $url .= '?pageStart=' . $pageStart . '&pageSize=' . $pageSize;
        return $this->getHelper($url, 'Comment', 'Comments', 'CommentId');
    }

}

?>
