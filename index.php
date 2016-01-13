<?php

/*
  Plugin Name: Migrate Wufoo to Gravity Forms
  Plugin URI: http://rtcamp.com/
  Description: Import Wufoo data to Gravity Forms
  Version: 1.0
  Author: rtCamp
  Author URI: http://rtcamp.com
 */
if (!defined('RT_WUFOO_IMPORT_PAGE_SIZE'))
    define('RT_WUFOO_IMPORT_PAGE_SIZE', 25);
require_once('lib/Wufoo-API/WufooApiWrapper.php');
require_once('rtWufooAPI.php');
require_once('rtProgress.php');
require_once('rtWufoo.php');
new rtWufoo();


// require_once('lib/parsecsv.lib.php');
// require_once('rtCSV.php');
// new rtCSV();
?>
