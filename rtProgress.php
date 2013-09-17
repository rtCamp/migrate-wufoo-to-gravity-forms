<?php

/**
 * a basic class to create progress-bar UIs
 *
 * @author Saurabh Shukla <saurabh.shukla@rtcamp.com>
 */
class rtProgress {

    /**
     *
     * @param number $progress Completed percentage of progressive activity
     * @param boolean $echo Whether the ui has to be echoed. Default true
     * @return string The html for the ui
     */
    function progress_ui($progress, $echo = true) {
        $progress_ui = '
			<div id="rtprogressbar">
				<div style="width:' . $progress . '%"></div>
			</div>
			';
        if ($echo)
            echo $progress_ui;
        else
            return $progress_ui;
    }

    /**
     *
     * @param number $progress A number representing the quantity of completed progress
     * @param number $total A number representaing the total quantity
     * @return int The progress in percentage
     */
    function progress($progress, $total) {
        if ($total < 1)
            return 100;
        return ($progress / $total) * 100;
    }

}

?>
