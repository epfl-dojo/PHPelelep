<?php
/**
 * This file is a mini controller which include authorized pages
**/

// Get the current called file through the uri params of url_rewriting
$uri = (isset($_GET['uri']) && !empty($_GET['uri']))?$_GET['uri']:'home';
// Limit the authorized pages to theses only
$authorized_pages = array('home','about','api','test');

if (in_array($uri, $authorized_pages)) {
    if ($uri !== 'api') { // avoid too much html in api
        // Include the first elements of the page
        require_once('./tpl/head.tpl');
        require_once('./tpl/nav.tpl');
    }
    require_once('./' . $uri . '.php');
    if ($uri !== 'api') {  // avoid too much html in api
        // Include the latest elements of the page
        require_once('./tpl/foot.tpl');
    }
} else {
    echo "Sorry, something went wrong...";
}
