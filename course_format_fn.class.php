<?php

/**
 * course_format is the base class for all course formats
 *
 * This class provides all the functionality for a course format
 */
define('COMPLETION_WAITFORGRADE_FN', 5);
define('COMPLETION_SAVED_FN', 4);

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Standard base class for all course formats
 */
class course_format_fn extends course_format {

    /**
     * Contructor
     *
     * @param $course object The pre-defined course object. Passed by reference, so that extended info can be added.
     *
     */
    public $tdselectedclass;

    function course_format_fn(&$course) {
        global $mods, $modnames, $modnamesplural, $modnamesused, $sections, $DB;

        parent::course_format($course);

        $this->mods = &$mods;
        $this->modnames = &$modnames;
        $this->modnamesplural = &$modnamesplural;
        $this->modnamesused = &$modnamesused;
        $this->sections = &$sections;
    }

    /*
     * Add extra config options in course object
     * 
     * * */

    function get_course($course=null) {
        global $DB;

        if (!empty($course->id)) {
            $extradata = $DB->get_records('course_config_fn', array('courseid' => $course->id));
        } else if (!empty($this->course->id)) {
            $extradata = $DB->get_records('course_config_fn', array('courseid' => $this->course->id));
        } else {
            $extradata = false;
        }

        if (is_null($course)) {
            $course = new Object();
        }

        if ($extradata) {
            foreach ($extradata as $extra) {
                $this->course->{$extra->variable} = $extra->value;
                $course->{$extra->variable} = $extra->value;
            }
        }

        return $course;
    }

    /** Custom functions * */
    function handle_extra_actions() {
        global $DB;

        if (isset($_POST['sec0title'])) {
            if (!$course = $DB->get_record('course', array('id' => $_POST['id']))) {
                print_error('This course doesn\'t exist.');
            }
            FN_get_course($course);
            $course->sec0title = $_POST['sec0title'];
            FN_update_course($course);
            $cm->course = $course->id;
        }
    }

    // oncampus function get_week_info($tabrange, $week) {
	function get_week_info($tabrange, $week, $chapter = 0) {
        global $SESSION, $DB, $USER;
		$c = $this->get_chapter($chapter); // added by oncampus
		$lections = $c['lections']; // added by oncampus
        //$fnmaxtab = $DB->get_field('course_config_fn', 'value', array('courseid' => $this->course->id, 'variable' => 'maxtabs'));

        /* if ($fnmaxtab) {
            $maximumtabs = $fnmaxtab;
        } else {
            $maximumtabs = 12;
        } */
		$maximumtabs = 6;
		if ($lections < 6) {
			$maximumtabs = $lections;
		}
		$last_lection = $this->get_last_lection(); // oncampus
		
		if ($USER->username == 'rieger') {
			//echo 'range: '.$tabrange.', week: '.$week.', chapter: '.$chapter.', last_section: '.$last_lection.'<br />';
		}
		
        if ($last_lection == $maximumtabs) { // oncampus
            //if ($USER->username == 'rieger') { echo '1<br />';}
			$tablow = 1;
            $tabhigh = $maximumtabs;
        } else if ($maximumtabs < 6) {
			//if ($USER->username == 'rieger') { echo '1.5<br />';}
			$tablow = 1;
            $tabhigh = $maximumtabs;
		} else if ($tabrange > 1000) {
			//if ($USER->username == 'rieger') { echo '2<br />';}
            $tablow = (int) ($tabrange / 1000);
            $tabhigh = (int) ($tablow + $maximumtabs - 1);
        } else if (($tabrange == 0) && ($week == 0)) {
			//if ($USER->username == 'rieger') { echo '3<br />';}
            $tablow = ((int) ((int) ($last_lection - 1) / (int) $maximumtabs) * $maximumtabs) + 1;
            $tabhigh = $tablow + $maximumtabs - 1;
        } else if ($tabrange == 0) {
            $tablow = $week;//((int) ((int) $week / (int) $maximumtabs) * $maximumtabs) + 1;
            $tabhigh = $tablow + $maximumtabs - 1;
			//if ($USER->username == 'rieger') { echo 'nr. 4 ('.$tablow.' '.$tabhigh.')<br />';}
			//echo '('.$tablow.' '.$tabhigh.')';
        } else {
			//if ($USER->username == 'rieger') { echo '5<br />';}
            $tablow = 1;
            $tabhigh = $maximumtabs;
        }
        $tabhigh = MIN($tabhigh, $last_lection);

        /// Normalize the tabs to always display FNMAXTABS...
        if (($tabhigh - $tablow + 1) < $maximumtabs) {
            $tablow = $tabhigh - $maximumtabs + 1;
        }

        /// Save the low and high week in SESSION variables... If they already exist, and the selected
        /// week is in their range, leave them as is.
        if (($tabrange >= 1000) || !isset($SESSION->FN_tablow[$this->course->id]) || !isset($SESSION->FN_tabhigh[$this->course->id]) ||
                ($week < $SESSION->FN_tablow[$this->course->id]) || ($week > $SESSION->FN_tabhigh[$this->course->id])) {
            $SESSION->FN_tablow[$this->course->id] = $tablow;
            $SESSION->FN_tabhigh[$this->course->id] = $tabhigh;
        } else {
            $tablow = $SESSION->FN_tablow[$this->course->id];
            $tabhigh = $SESSION->FN_tabhigh[$this->course->id];
        }
        $tablow = MAX($tablow, 1);
        $tabhigh = MIN($tabhigh, $last_lection);

		// oncampus
		/* $low = $c['first_lection'] - ((int)(($maximumtabs - $lections) / 2));
		
		if (($low + $maximumtabs - 1) > $last_lection) {
			$low = $last_lection - $maximumtabs + 1;
		}
		$high = $low + $maximumtabs - 1;
		if ($chapter != 0 and (($tabrange > $low and $tabrange <= $high) or $tabrange == 0 )) {
			$tablow = $low;
            $tabhigh = $high;
		} */
		// oncampus ende
		
        unset($maximumtabs);
        return array($tablow, $tabhigh, $week);
    }

    function print_weekly_activities_bar($week=0, $tabrange=0, $resubmission=false) {
        global $FULLME, $CFG, $course, $DB, $USER, $PAGE;
		
		// added by oncampus
		$last_visible_lection = 31;
		$active_chapter = optional_param('chapter', 0, PARAM_INT);
		$c = $this->get_chapter($active_chapter); // added by oncampus
		$lections = $c['lections']; // added by oncampus
		// oncampus end

        $selectedcolour   = $DB->get_field('course_config_fn', 'value', array('courseid' => $this->course->id, 'variable' => 'selectedcolour'));
        $activelinkcolour = $DB->get_field('course_config_fn', 'value', array('courseid' => $this->course->id, 'variable' => 'activelinkcolour'));
        $activecolour     = $DB->get_field('course_config_fn', 'value', array('courseid' => $this->course->id, 'variable' => 'activecolour'));

        $inactivelinkcolour = $DB->get_field('course_config_fn', 'value', array('courseid' => $this->course->id, 'variable' => 'inactivelinkcolour'));
        $inactivecolour     = $DB->get_field('course_config_fn', 'value', array('courseid' => $this->course->id, 'variable' => 'inactivecolour'));

        $selectedcolour = $selectedcolour ? $selectedcolour : 'FFFF33';
        $activelinkcolour   = $activelinkcolour ? $activelinkcolour : '000000';
        $inactivelinkcolour   = $inactivelinkcolour ? $inactivelinkcolour : '000000';
        $activecolour   = $activecolour ? $activecolour : 'DBE6C4';
        $inactivecolour = $inactivecolour ? $inactivecolour : 'BDBBBB';

        /* $fnmaxtab       = $DB->get_field('course_config_fn', 'value', array('courseid' => $this->course->id, 'variable' => 'maxtabs'));

        if ($fnmaxtab) {
            $maximumtabs = $fnmaxtab;
        } else {
            $maximumtabs = 12;
        } */
		$maximumtabs = 6;
		if ($lections < 6) {
			$maximumtabs = $lections;
		}

        echo "
        <style>
        .fnweeklynavselected {
            background-color: #$selectedcolour;
			color: #666666;
        }
        .fnweeklynavnorm,
        .fnweeklynavnorm a:active {
            background-color: #$activecolour;
        }
        .fnweeklynavdisabled {
            color: #$inactivelinkcolour;
            background-color: #$inactivecolour;
        }
        .fnweeklynav .tooltip {
            color: #$activelinkcolour;
        }
        </style>";


        $completioninfo = new completion_info($course);

        // oncampus list($tablow, $tabhigh, $week) = $this->get_week_info($tabrange, $week);
		list($tablow, $tabhigh, $week) = $this->get_week_info($tabrange, $week, $active_chapter); // added by oncampus

		// oncampus tablow und high korrigieren bei klick auf nächste Lektion im Footer !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		if ($USER->username == 'rieger') {
			//echo '0 high: '.$tabhigh.'<br />low: '.$tablow;
		}
		if ($tabhigh >= ($c['first_lection'] + $c['lections'])) {
			$tabhigh = $c['first_lection'] + $c['lections'] - 1;
			//$tablow = $tabhigh - 5;
			if ($USER->username == 'rieger') {
				//echo '<br />1 high: '.$tabhigh.'<br />low: '.$tablow;
			}
		}
		
		$lecs = 6;
		if ($c['lections'] < $lecs) {
			$lecs = $c['lections'];
		}
		if ($tabhigh - $tablow + 1 < $lecs) {
			$tablow = $tabhigh - $lecs + 1;
			if ($USER->username == 'rieger') {
				//echo '<br />2 high: '.$tabhigh.'<br />low: '.$tablow;
			}
		}
		
		if ($tablow < $c['first_lection']) {
			$tablow = $c['first_lection'];
			$tabhigh = $tablow + $lecs - 1;
			//$tabhigh = $c['first_lection'] + $c['lections'] - 1;
			if ($USER->username == 'rieger') {
				//echo '<br />3 high: '.$tabhigh.'<br />low: '.$tablow.', lecs: '.$lecs;
			}
		}
		
		
		//echo $tabrange.' '.$week.' '.$active_chapter.' '.$tablow;
        $timenow = time();
        $weekdate = $this->course->startdate;    // this should be 0:00 Monday of that week
        $weekdate += 7200;                 // Add two hours to avoid possible DST problems
        $weekofseconds = 604800;
		
		$last_lection = $this->get_last_lection(); // oncampus
		
        if ($last_lection > 20) { // oncampus
            $extraclassfortab = "tab-greaterthan5";
        } else {
            $extraclassfortab = "tab-lessthan5";
        }
		$extraclassfortab = 'tab-oc-mooin';

        if (isset($this->course->topicheading) && !empty($this->course->topicheading)) {
            $strtopicheading = $this->course->topicheading;
        } else {
            $strtopicheading = 'Week';
        }
        $context = get_context_instance(CONTEXT_COURSE, $this->course->id);
        $isteacher = has_capability('moodle/course:update', $this->context);
        $iseditingteacher = has_capability('gradereport/grader:view', $this->context);
        $url = preg_replace('/(^.*)(&selected_week\=\d+)(.*)/', '$1$3', $FULLME);

        $actbar = '';
        $actbar .= '<table cellpadding="0" cellspacing="0"><tr><td>';
        $actbar .= '<table cellpadding="0" cellspacing="0"  class="fnweeklynav"><tr>';
        $width = (int) (100 / ($tabhigh - $tablow + 3));
        $actbar .= '<td width="4" align="center" height=""></td>';

		//$actbar .= $tablow.' '.$c['first_lection'];
        if ($tablow <= $c['first_lection']) {
            //$actbar .= '<td height="25" class="tab-heading"><strong>' . $strtopicheading . ':&nbsp;</strong></td>';
			//$actbar .= '<td height="" class="tab-heading">&nbsp;&nbsp;&nbsp;</td>'; // oncampus
			$actbar .= '<td id="fn_tab_previous" height=""><div class = "prev-lection-inactive"></div></td>'; // oncampus
        } else {
			$n = $tablow - 6;
			if ($n < $c['first_lection']) {
				$n = $c['first_lection'];
			}
            $prv = $n * 1000;
            // oncampus $actbar .= '<td id="fn_tab_previous" height="25"><a href="' . $url . '&selected_week=' . $prv . '">Previous</a></td>';
            $actbar .= '<td id="fn_tab_previous" height=""><a href="' . $url . '&selected_week=' . $prv . '" class="prev-lection"><div class = "oc-lection-btn"></div></a></td>'; // oncampus
        }

        $tdselectedclass = array();

        $currentweek = ($timenow > $course->startdate) ?
                (int) ((($timenow - $course->startdate) / $weekofseconds) + 1) : 0;

        $currentweek = min($currentweek, $last_lection); // oncampus

        for ($i = $tablow; $i <= $tabhigh; $i++) {
            //if (empty($this->sections[$i]->visible) || ($timenow < $weekdate)) {
            // oncampus if (empty($this->sections[$i]->visible) || $i > $currentweek) {
            $i_chapter = $this->get_chapter_for_lection($i); // if ($i == $week) {
			if (($i < $c['first_lection']) or ($i >= $c['first_lection'] + $c['lections'])) { // added by oncampus Alle Lektionen, die nicht zum aktiven Kapitel gehören 
				if ($i_chapter['enabled'] == false) { // Diese Lektionen sind noch nicht verfügbar
                    $css = 'fnweeklynavdisabledselected';
                } else { // verfügbare Lektionen
                    $css = 'fnweeklynavdisabled';
                }
                $tdselectedclass[$i] = $css;
				
				$newchapter = $this->get_chapter_for_lection($i);
				$newurl = preg_replace('/(^.*)(&chapter\=\d+)(.*)/', '$1$3', $url);
				$newurl = $newurl.'&chapter='.$newchapter['number'];
                if ($isteacher) {
                    // oncampus $f = '<a href="' . $url . '&selected_week=' . $i . '" ><span class="' . $css . '">&nbsp;' .
					$f = '<a href="' . $newurl . '&selected_week=' . $i . '" ><span class="' . $css . '">&nbsp;' .
                            $i . '&nbsp;</span></a>';
				} 
				else { // Nutzer ist Student
					if ($i_chapter['enabled'] == false) {// Diese Lektionen sind noch nicht verfügbar
						$f = ' ' . $i . ' ';
					}
					else { // verfügbare Lektionen
						$f = '<a href="' . $newurl . '&selected_week=' .$i. '" ><span class="' . $css . '">&nbsp;' .
								$i . '&nbsp;</span></a>';
					}
                }
				if (!$isteacher && !is_siteadmin() && !empty($completioninfo) && !$iseditingteacher) {
                    if ($completioninfo->is_enabled() && $CFG->enablecompletion) {
						$oc_f = $this->quizzes_finished($this->sections[$i]) ? 'green-tab' : 'red-tab';
                    } else {
                        $oc_f = '';
                    }
                } else {
                    $oc_f = ''; // oncampus $f = ' ' . $i . ' ';
                }
                // oncampus $actbar .= '<td class="' . $css . ' ' . $extraclassfortab . '" height="25" width="" alt="Upcoming sections" title="Upcoming sections">' . $f . '</td>';
				$actbar .= '<td class="' . $css . ' ' . $extraclassfortab . ' ' . $oc_f . '" height="" width="" alt="Upcoming sections" title="Upcoming sections">' . $f . '</td>';
            } else if ($i == $week) { // die aktuell angezeigte Lektion
                if (!$isteacher && !is_siteadmin() && !empty($completioninfo) && !$iseditingteacher) {
                    if ($completioninfo->is_enabled() && $CFG->enablecompletion) {
                        // oncampus $f = $this->is_section_finished($this->sections[$i], $this->mods) ? 'green-tab' : 'red-tab';
						//$f = $this->quizzes_finished($this->sections[$i]) ? 'green-tab' : 'red-tab';
                    } else {
                        $f = '';
                    }
                } else {
                    $f = '';
                }
                $tdselectedclass[$i] = 'fnweeklynavselected';
				$show = '<div class="oc-nav-lektion-text">Lektion</div><div class="oc-nav-lektion-nr">'.($i - $this->count_previous_chapter_lections($i)).'</div>'; //'('. $i.') </td>';
				
				$ocp = $this->get_section_grades($this->sections[$i]);
				$ocp = round($ocp);
				if ($ocp != -1) {
					$show .= '<br />'.$this->get_progress_bar($ocp, 100, $this->sections[$i]->id);
				}
				else {
					$tdselectedclass[$i] = 'fnweeklynavselected noprogress';
				}
				$actbar .= '<td class="'.$tdselectedclass[$i].' ' . $f . ' ' . $extraclassfortab . '" id=fnweeklynav' . $i . ' width="" height=""><div class="tab-oc-seclected-content">'.$show.'</div></td>';
            } else { // alle Lektionen, die zum Kapitel gehören
                if (!$isteacher && !is_siteadmin() && !$iseditingteacher) {
                    if ($completioninfo->is_enabled() && $CFG->enablecompletion) {
                        // oncampus $f = $this->is_section_finished($this->sections[$i], $this->mods) ? 'green-tab' : 'red-tab';
						//$f = $this->quizzes_finished($this->sections[$i]) ? 'green-tab' : 'red-tab';
                        $w = $i;
                        $sectionid = $i;
                        $section = $DB->get_record("course_sections", array("section" => $sectionid, "course" => $course->id));
                        $activitiesstatusarray = get_activities_status($course, $section, $resubmission);
                        $compl = $activitiesstatusarray['complete'];
                        $incompl = $activitiesstatusarray['incomplete'];
                        $svd = $activitiesstatusarray['saved'];
                        $notattemptd = $activitiesstatusarray['notattempted'];
                        $waitforgrade = $activitiesstatusarray['waitngforgrade'];
                    } else {
                        $f = '';
                    }
                } else {
                    $f = '';
                }
                $tdselectedclass[$i] = 'fnweeklynavnorm';
                // oncampus $tooltipclass = ($i >= ($tabhigh / 2)) ? '-right' : '';
				$tooltipclass = 'tooltip'; // added by oncampus
				$real_lection = '';
				if ($USER->username == 'rieger') {
					//$real_lection = '('.$i.')';
				}
				$show = '<div class="oc-nav-lektion-text">Lektion</div><div class="oc-nav-lektion-nr">'.($i - $this->count_previous_chapter_lections($i)).$real_lection.'</div>'; //('. $i.')&nbsp;</a>';
				$ocp = $this->get_section_grades($this->sections[$i]);
				$ocp = round($ocp);
				if ($ocp != -1) {
					$show .= '<br />'.$this->get_progress_bar($ocp, 100, $this->sections[$i]->id);
				}
				else {
					$tdselectedclass[$i] = 'fnweeklynavnorm noprogress';
				}
				//$show .= '<br />'.$this->get_progress_bar($this->get_section_grades($this->sections[$i]), 90, $this->sections[$i]->id);
				$actbar .= '<td class="'.$tdselectedclass[$i].' ' . $f . ' ' . $extraclassfortab . '" id=fnweeklynav' . $i . ' width="100px" height="">'.
							'<a class="'.$tooltipclass.'" href="' . $url . '&selected_week=' . $i . '">'.$show.'</a>';
                // oncampus $actbar .= '</a>' . '</td>';
				$actbar .= '</td>';
            }
           // $weekdate += ( $weekofseconds);
            // oncampus $actbar .= '<td align="center" height="25" style="width: 2px;">' .
			$actbar .= '<td align="center" height="" style="width: 2px;">' .
                    '<img src="' . $CFG->wwwroot . '/pix/spacer.gif" height="1" width="1" alt="" /></td>';
        }

        /* oncampus if (($week == 0) && ($tabhigh >= $this->course->numsections)) {
            $actbar .= '<td class="fnweeklynavselected ' . $extraclassfortab . '"  width="" height="25">All</td>';
        } else if ($tabhigh >= $this->course->numsections) {
            $actbar .= '<td class="fnweeklynavnorm ' . $extraclassfortab . '" width="" height="25">' .
                    '<a href="' . $url . '&selected_week=0">All</a></td>';
        } else */
		if ($tabhigh < $c['first_lection'] + $c['lections'] - 1) { // oncampus
			$n = $tablow + 6;
			if ($c['lections'] + $c['first_lection'] - $n < 6) {
				$n = $c['lections'] + $c['first_lection'] - 6;
			}
			$nxt = $n * 1000;
            $actbar .= '<td id="fn_tab_next" height=""><a href="' . $url . '&selected_week=' . $nxt . '" class="next-lection"><div class = "oc-lection-btn"></div></a></td>'; // oncampus
        }
		else {
			$actbar .= '<td id="fn_tab_next" height=""><div class = "next-lection-inactive"></div></td>'; // oncampus
		}
        $settingIcon='';
        if ($PAGE->user_is_editing() && has_capability('moodle/course:update', $context)) {
            $settingIcon = '<a href="' . $CFG->wwwroot . '/course/format/' . $this->course->format . '/tabsettings.php?id='.$this->course->id.'" > 
                                <img style="margin: 3px 1px 1px 5px;" src="' . $CFG->wwwroot . '/course/format/' . $this->course->format . '/pix/cog.png" /></a>';
        }
        //$actbar .= '<td width="1" align="center" height="25">'.$settingIcon.'</td>';
		$actbar .= '<td width="1" align="center" height="">'.$settingIcon.'</td>';
        $actbar .= '</tr>';
        /* $actbar .= '<tr>';
        $actbar .= '<td height="3" colspan="2"></td>';


        $this->tdselectedclass = $tdselectedclass;

        for ($i = $tablow; $i <= $tabhigh; $i++) {
            if ($i == $week) {
                $actbar .= '<td height="3" class="' . $tdselectedclass[$i] . '"></td>';
            } else {
                $actbar .= '<td height="3"></td>';
            }
            $actbar .= '<td height="3"></td>';
        }
        $actbar .= '<td height="3" colspan="2"></td>';
        $actbar .= '</tr>'; */
        $actbar .= '</table>';
        $actbar .= '</td></tr></table>';
        rebuild_course_cache($this->course->id);
        unset($maximumtabs);

        return $actbar;
    }

    /*     * LIBRARY REPLACEMENT* */

    /**
     * Prints a section full of activity modules
     */
    function print_section_fn($course, $section, $mods, $modnamesused, $absolute=false, $width="100%", $hidecompletion=false, $resubmission=false) {
        global $CFG, $USER, $DB, $PAGE, $OUTPUT;

        static $initialised;
        static $groupbuttons;
        static $groupbuttonslink;
        static $isediting;
        static $ismoving;
        static $strmovehere;
        static $strmovefull;
        static $strunreadpostsone;
        static $groupings;
        static $modulenames;
		
		// oncampus
		$selected_week = $section->__get('section');
		if (!$oc_chapter = $this->get_chapter_for_lection($selected_week)) {
			echo $selected_week."Fehler beim Laden des Kapitels";
			return;
		}
		if (!has_capability('moodle/course:update', $this->context) and $oc_chapter['enabled'] == 'false') {
			echo '<div class="inactive-chapter">'.$oc_chapter['name'].' - Dieses Kapitel ist noch nicht freigegeben!</div>';
			return;
		}
		if (!has_capability('moodle/course:update', $this->context) and $oc_chapter['enabled'] == 'hidden') {
			echo '<div class="inactive-chapter">'.$oc_chapter['name'].' - Du besitzt nicht die Rechte um dieses Kapitel zu sehen!</div>';
			return;
		}
		// oncampus ende

        if (!isset($initialised)) {
            $groupbuttons = ($course->groupmode or (!$course->groupmodeforce));
            $groupbuttonslink = (!$course->groupmodeforce);
            $isediting = $PAGE->user_is_editing();
            $ismoving = $isediting && ismoving($course->id);
            if ($ismoving) {
                $strmovehere = get_string("movehere");
                $strmovefull = strip_tags(get_string("movefull", "", "'$USER->activitycopyname'"));
            }
            $modulenames = array();
            $initialised = true;
        }

        $modinfo = get_fast_modinfo($course);

        $completioninfo = new completion_info($course);

        //Accessibility: replace table with list <ul>, but don't output empty list.
        if (!empty($section->sequence)) {

            // Fix bug #5027, don't want style=\"width:$width\".
            echo "<ul class=\"section img-text\">\n";
            $sectionmods = explode(",", $section->sequence);

            foreach ($sectionmods as $modnumber) {
                if (empty($mods[$modnumber])) {
                    continue;
                }

                /**
                 * @var cm_info
                 */
                $mod = $mods[$modnumber];  

                if ($ismoving and $mod->id == $USER->activitycopy) {
                    // do not display moving mod
                    continue;
                }

                if (isset($modinfo->cms[$modnumber])) {
                    // We can continue (because it will not be displayed at all)
                    // if:
                    // 1) The activity is not visible to users
                    // and
                    // 2a) The 'showavailability' option is not set (if that is set,
                    //     we need to display the activity so we can show
                    //     availability info)
                    // or
                    // 2b) The 'availableinfo' is empty, i.e. the activity was
                    //     hidden in a way that leaves no info, such as using the
                    //     eye icon.
                    if (!$modinfo->cms[$modnumber]->uservisible &&
                            (empty($modinfo->cms[$modnumber]->showavailability) ||
                            empty($modinfo->cms[$modnumber]->availableinfo))) {
                        // visibility shortcut
                        continue;
                    }
                } else {
                    if (!file_exists("$CFG->dirroot/mod/$mod->modname/lib.php")) {
                        // module not installed
                        continue;
                    }
                    if (!coursemodule_visible_for_user($mod) &&
                            empty($mod->showavailability)) {
                        // full visibility check
                        continue;
                    }
                }

                if (!isset($modulenames[$mod->modname])) {
                    $modulenames[$mod->modname] = get_string('modulename', $mod->modname);
                }
                $modulename = $modulenames[$mod->modname];

                // In some cases the activity is visible to user, but it is
                // dimmed. This is done if viewhiddenactivities is true and if:
                // 1. the activity is not visible, or
                // 2. the activity has dates set which do not include current, or
                // 3. the activity has any other conditions set (regardless of whether
                //    current user meets them)
                $canviewhidden = has_capability(
                        'moodle/course:viewhiddenactivities', get_context_instance(CONTEXT_MODULE, $mod->id));
                $accessiblebutdim = false;
                if ($canviewhidden) {
                    $accessiblebutdim = !$mod->visible;
                    if (!empty($CFG->enableavailability)) {
                        $accessiblebutdim = $accessiblebutdim ||
                                $mod->availablefrom > time() ||
                                ($mod->availableuntil && $mod->availableuntil < time()) ||
                                count($mod->conditionsgrade) > 0 ||
                                count($mod->conditionscompletion) > 0;
                    }
                }

                $liclasses = array();
                $liclasses[] = 'activity';
                $liclasses[] = $mod->modname;
                $liclasses[] = 'modtype_' . $mod->modname;
                $extraclasses = $mod->get_extra_classes();
                if ($extraclasses) {
                    $liclasses = array_merge($liclasses, explode(' ', $extraclasses));
                }
                echo html_writer::start_tag('li', array('class' => join(' ', $liclasses), 'id' => 'module-' . $modnumber));
                if ($ismoving) {
                    echo '<a title="' . $strmovefull . '"' .
                    ' href="' . $CFG->wwwroot . '/course/mod.php?moveto=' . $mod->id . '&amp;sesskey=' . sesskey() . '">' .
                    '<img class="movetarget" src="' . $OUTPUT->pix_url('movehere') . '" ' .
                    ' alt="' . $strmovehere . '" /></a><br />
                     ';
                }

                $classes = array('mod-indent');
                if (!empty($mod->indent)) {
                    $classes[] = 'mod-indent-' . $mod->indent;
                    if ($mod->indent > 15) {
                        $classes[] = 'mod-indent-huge';
                    }
                }
                echo html_writer::start_tag('div', array('class' => join(' ', $classes)));

                // Get data about this course-module
                list($content, $instancename) = array($modinfo->cms[$modnumber]->get_formatted_content(array('overflowdiv' => true, 'noclean' => true)), $modinfo->cms[$modnumber]->get_formatted_name());
                        //=get_print_section_cm_text($modinfo->cms[$modnumber], $course);

                //Accessibility: for files get description via icon, this is very ugly hack!
                $altname = '';
                $altname = $mod->modfullname;
                if (!empty($customicon)) {
                    $archetype = plugin_supports('mod', $mod->modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER);
                    if ($archetype == MOD_ARCHETYPE_RESOURCE) {
                        $mimetype = mimeinfo_from_icon('type', $customicon);
                        $altname = get_mimetype_description($mimetype);
                    }
                }
                // Avoid unnecessary duplication: if e.g. a forum name already
                // includes the word forum (or Forum, etc) then it is unhelpful
                // to include that in the accessible description that is added.
                if (false !== strpos(textlib::strtolower($instancename), textlib::strtolower($altname))) {
                    $altname = '';
                }
                // File type after name, for alphabetic lists (screen reader).
                if ($altname) {
                    $altname = get_accesshide(' ' . $altname);
                }

                // We may be displaying this just in order to show information
                // about visibility, without the actual link
                $contentpart = '';
                if ($mod->uservisible) {
                    // Nope - in this case the link is fully working for user
                    $linkclasses = '';
                    $textclasses = '';
                    if ($accessiblebutdim) {
                        $linkclasses .= ' dimmed';
                        $textclasses .= ' dimmed_text';
                        $accesstext = '<span class="accesshide">' .
                                get_string('hiddenfromstudents') . ': </span>';
                    } else {
                        $accesstext = '';
                    }
                    if ($linkclasses) {
                        $linkcss = 'class="' . trim($linkclasses) . '" ';
                    } else {
                        $linkcss = '';
                    }
                    if ($textclasses) {
                        $textcss = 'class="' . trim($textclasses) . '" ';
                    } else {
                        $textcss = '';
                    }

                    // Get on-click attribute value if specified
                    $onclick = $mod->get_on_click();
                    if ($onclick) {
                        $onclick = ' onclick="' . $onclick . '"';
                    }

                    if ($url = $mod->get_url()) {
                        // Display link itself
                        echo '<a ' . $linkcss . $mod->extra . $onclick .
                        ' href="' . $url . '"><img src="' . $mod->get_icon_url() .
                        '" class="activityicon" alt="' .
                        $modulename . '" /> ' .
                        $accesstext . '<span class="instancename">' .
                        $instancename . $altname . '</span></a>';

                        // If specified, display extra content after link
                        if ($content) {
                            $contentpart = '<div class="contentafterlink' .
                                    trim($textclasses) . '">' . $content . '</div>';
                        }
                    } else {
                        // No link, so display only content
                        $contentpart = '<div ' . $textcss . $mod->extra . '>' .
                                $accesstext . $content . '</div>';
                    }

                    if (!empty($mod->groupingid) && has_capability('moodle/course:managegroups', get_context_instance(CONTEXT_COURSE, $course->id))) {
                        if (!isset($groupings)) {
                            $groupings = groups_get_all_groupings($course->id);
                        }
                        echo " <span class=\"groupinglabel\">(" . format_string($groupings[$mod->groupingid]->name) . ')</span>';
                    }
                } else {
                    $textclasses = $extraclasses;
                    $textclasses .= ' dimmed_text';
                    if ($textclasses) {
                        $textcss = 'class="' . trim($textclasses) . '" ';
                    } else {
                        $textcss = '';
                    }
                    $accesstext = '<span class="accesshide">' .
                            get_string('notavailableyet', 'condition') .
                            ': </span>';

                    if ($url = $mod->get_url()) {
                        // Display greyed-out text of link
                        echo '<div ' . $textcss . $mod->extra .
                        ' >' . '<img src="' . $mod->get_icon_url() .
                        '" class="activityicon" alt="' .
                        $modulename .
                        '" /> <span>' . $instancename . $altname .
                        '</span></div>';

                        // Do not display content after link when it is greyed out like this.
                    } else {
                        // No link, so display only content (also greyed)
                        $contentpart = '<div ' . $textcss . $mod->extra . '>' .
                                $accesstext . $content . '</div>';
                    }
                }

                // Module can put text after the link (e.g. forum unread)
                echo $mod->get_after_link();

                // If there is content but NO link (eg label), then display the
                // content here (BEFORE any icons). In this case cons must be
                // displayed after the content so that it makes more sense visually
                // and for accessibility reasons, e.g. if you have a one-line label
                // it should work similarly (at least in terms of ordering) to an
                // activity.
                if (empty($url)) {
                    echo $contentpart;
                }

                if ($isediting) {
                    if ($groupbuttons and plugin_supports('mod', $mod->modname, FEATURE_GROUPS, 0)) {
                        if (!$mod->groupmodelink = $groupbuttonslink) {
                            $mod->groupmode = $course->groupmode;
                        }
                    } else {
                        $mod->groupmode = false;
                    }
                    echo '&nbsp;&nbsp;';
                    //echo make_editing_buttons($mod, $absolute, true, $mod->indent, $section->section);
                    
                 
                        if (!($mod instanceof cm_info)) {
                            $modinfo = get_fast_modinfo($mod->course);
                            $mod = $modinfo->get_cm($mod->id);
                        }
                        $actions = course_get_cm_edit_actions($mod, $mod->indent, $section->section);

                        $courserenderer = $PAGE->get_renderer('core', 'course');
                        // The space added before the <span> is a ugly hack but required to set the CSS property white-space: nowrap
                        // and having it to work without attaching the preceding text along with it. Hopefully the refactoring of
                        // the course page HTML will allow this to be removed.
                        echo ' ' . $courserenderer->course_section_cm_edit_actions($actions);
                                       
                    
                    
                    echo $mod->get_after_edit_icons();
                }

                // Completion
                require_once('modulelib.php');

                $completion = $hidecompletion ? COMPLETION_TRACKING_NONE : $completioninfo->is_enabled($mod);
                if ($completion != COMPLETION_TRACKING_NONE && isloggedin() &&
                        !isguestuser() && $mod->uservisible) {
                    $completiondata = $completioninfo->get_data($mod, true);
                    $completionicon = '';
                    if ($isediting) {
                        switch ($completion) {
                            case COMPLETION_TRACKING_MANUAL :
                                $completionicon = 'manual-enabled';
                                break;
                            case COMPLETION_TRACKING_AUTOMATIC :
                                $completionicon = 'auto-enabled';
                                break;
                            default: // wtf
                        }
                    }
                    ///this condition is added by sudhanshu                    
                    else if (is_siteadmin() || !has_capability('mod/assignment:submit', get_context_instance(CONTEXT_COURSE, $course->id))) {
                        switch ($completion) {
                            case COMPLETION_TRACKING_MANUAL :
                                $completionicon = 'manual-enabled';
                                break;
                            case COMPLETION_TRACKING_AUTOMATIC :
                                $completionicon = 'auto-enabled';
                                break;
                            default: // wtf
                        }
                    } else if ($completion == COMPLETION_TRACKING_MANUAL) {
                        switch ($completiondata->completionstate) {
                            case COMPLETION_INCOMPLETE:
                                $completionicon = 'manual-n';
                                break;
                            case COMPLETION_COMPLETE:
                                $completionicon = 'manual-y';
                                break;
                        }
                    } else { // Automatic                      
                        if (($mod->modname == 'assignment' || $mod->modname == 'assign') && isset($mod->completiongradeitemnumber)) {                           
                            $act_compl = is_saved_or_submitted($mod, $USER->id, $resubmission);
                            if ($act_compl == 'submitted') {
                               // $completiondata->completionstate = COMPLETION_WAITFORGRADE_FN;
                            } else if ($act_compl == 'waitinggrade') {
                               $completiondata->completionstate = COMPLETION_WAITFORGRADE_FN;
                            } else if ($act_compl == 'saved') {
                                $completiondata->completionstate = COMPLETION_SAVED_FN;
                            }
                        }

                        switch ($completiondata->completionstate) {
                            case COMPLETION_INCOMPLETE:
                                $completionicon = 'auto-n';
                                break;
                            case COMPLETION_COMPLETE:
                                $completionicon = 'auto-y';
                                break;
                            case COMPLETION_COMPLETE_PASS:
                                $completionicon = 'auto-pass';
                                break;
                            case COMPLETION_COMPLETE_FAIL:
                                $completionicon = 'auto-fail';
                                break;
                            case COMPLETION_WAITFORGRADE_FN:
                                $completionicon = 'submitted';
                                break;
                            case COMPLETION_SAVED_FN:
                                $completionicon = 'saved';
                                break;
                        }
                    }
                    if ($completionicon) {
                        $imgsrc = '' . $CFG->wwwroot . '/course/format/' . $this->course->format . '/pix/completion-' . $completionicon . '.gif';
                        $imgalt = s(get_string('completion-alt-' . $completionicon, 'format_octabs'));
                        if ($completion == COMPLETION_TRACKING_MANUAL && !$isediting && has_capability('mod/assignment:submit', get_context_instance(CONTEXT_COURSE, $course->id)) && !is_primary_admin($USER->id)) {
                            $imgtitle = s(get_string('completion-title-' . $completionicon, 'format_octabs'));
                            $newstate =
                                    $completiondata->completionstate == COMPLETION_COMPLETE ? COMPLETION_INCOMPLETE : COMPLETION_COMPLETE;

                            // In manual mode the icon is a toggle form...
                            // If this completion state is used by the
                            // conditional activities system, we need to turn
                            // off the JS.i
                            /* oncampus 
							if (!empty($CFG->enableavailability) &&
                                    condition_info::completion_value_used_as_condition($course, $mod)) {
                                $extraclass = ' preventjs';
                            } else {
                                $extraclass = '';
                            }
                            echo "
                                    <form class='togglecompletion$extraclass' method='post' action='" . $CFG->wwwroot . "/course/togglecompletion.php'><div>
                                    <input type='hidden' name='id' value='{$mod->id}' />
                                    <input type='hidden' name='sesskey' value='" . sesskey() . "' />
                                    <input type='hidden' name='completionstate' value='$newstate' />
                                    <input type='image' src='$imgsrc' alt='$imgalt' title='$imgtitle' />
                                    </div></form>"; 
							*/
                        } else {
                            // In auto mode, or when editing, the icon is just an image
                            /* echo "<span class='autocompletion'>";
                            echo "<img src='$imgsrc' alt='$imgalt' title='$imgalt' /></span>"; */
                        }
                    }
                }

                // If there is content AND a link, then display the content here
                // (AFTER any icons). Otherwise it was displayed before
                if (!empty($url)) {
                    echo $contentpart;
                }

                // Show availability information (for someone who isn't allowed to
                // see the activity itself, or for staff)
                if (!$mod->uservisible) {
                    echo '<div class="availabilityinfo">' . $mod->availableinfo . '</div>';
                } else if ($canviewhidden && !empty($CFG->enableavailability)) {
                    $ci = new condition_info($mod);
                    $fullinfo = $ci->get_full_information();
                    if ($fullinfo) {
                        echo '<div class="availabilityinfo">' . get_string($mod->showavailability ? 'userrestriction_visible' : 'userrestriction_hidden', 'condition', $fullinfo) . '</div>';
                    }
                }

                echo html_writer::end_tag('div');
                echo html_writer::end_tag('li') . "\n";
            }
        } elseif ($ismoving) {
            echo "<ul class=\"section\">\n";
        }

        if ($ismoving) {
            echo '<li><a title="' . $strmovefull . '"' .
            ' href="' . $CFG->wwwroot . '/course/mod.php?movetosection=' . $section->id . '&amp;sesskey=' . sesskey() . '">' .
            '<img class="movetarget" src="' . $OUTPUT->pix_url('movehere') . '" ' .
            ' alt="' . $strmovehere . '" /></a></li>
             ';
        }
        if (!empty($section->sequence) || $ismoving) {
            echo "</ul><!--class='section'-->\n\n";
        }
    }

    /**
     * If used, this will just call the library function (for now). Replace this with your own to make it
     * do what you want.
     *
     */
    function print_section_add_menus($course, $section, $modnames, $vertical=false, $return=false) {
        //return print_section_add_menus($course, $section, $modnames, $vertical, $return);
        global $PAGE;
        
        $output = '';
        $courserenderer = $PAGE->get_renderer('core', 'course');
        $output = $courserenderer->course_section_add_cm_control($course, $section, null,
                array('inblock' => $vertical));
        if ($return) {
            return $output;
        } else {
            echo $output;
            return !empty($output);
        }        
    }
	
	function get_progress_bar($p, $width, $sectionid = 0) {
		//$p_width = $width / 100 * $p;
		$result = 
				html_writer::tag('div',
				  html_writer::tag('div', 
					html_writer::tag('div', 
						'', 
						array('style' => 'width: '.$p.'%; height: 15px; border: 0px; background: #9ADC00; text-align: center; float: left;', 'id' => 'oc-progress-'.$sectionid)
					), 
					array('style' => 'width: '.$width.'%; height: 15px; border: 1px; background: #aaa; solid #aaa; margin: 0; padding: 0; float: left; position: absolute;')
				  ).
				  html_writer::tag('div', $p.'%', array('style' => 'float: right; padding: 0; position: absolute; color: #555; width: 100%; text-align: center;', 'id' => 'oc-progress-text-'.$sectionid)).
				  html_writer::tag('div', '', array('style' => 'clear: both;')),
				array('class' => 'oc-progress-div', 'style' => 'float: left; position: relative;'));
		return $result;
	}
	
	function get_section_grades(&$section) {
		global $CFG, $USER, $course;
		require_once($CFG->dirroot . '/mod/occapira/locallib.php');
		
		$mods = get_course_section_mods($course->id, $section->id);//print_object($mods);
		$modules = array();
		foreach ($mods as $m) {
			if ($m->modname == 'occapira') {
				$modules[] = $m;
			}
		}
		$count = count($modules);
		if ($count == 0) {
			return -1;
		}
		$percentage = 0;
		foreach ($modules as $modu) {//print_object($modu);
			$result = occapira_get_percentage($modu->instance, $USER->id);
			$percentage += $result['percentage'] / $count;
		}
		return $percentage;
	}

	function quizzes_finished(&$section) {
		global $course;
		$quizzes_finished = 0;
		$quizzes = 0;
		$modules = get_course_section_mods($course->id, $section->id);
		if (count($modules) >= 1) {
			foreach ($modules as $modu) {
				if ($modu->modname == 'quiz') {
					$quizzes++;
					if ($this->is_quiz_finished($modu->id)) {
						$quizzes_finished++;
					}
				}
			}
		}
		if ($quizzes != 0 and $quizzes_finished == $quizzes) {
			return true;
		}
		return false;
	}
	
	function is_quiz_finished($cmid) {
		global $USER, $CFG;
		require_once($CFG->dirroot . '/mod/quiz/locallib.php');
		require_once($CFG->dirroot . '/mod/quiz/lib.php');
		
		$cm = get_coursemodule_from_id('quiz', $cmid);
		$quizobj = quiz::create($cm->instance, $USER->id);
		$attempts = quiz_get_user_attempts($quizobj->get_quizid(), $USER->id, 'all', true);
		foreach ($attempts as $attempt) {
			if ($attempt && ($attempt->state == quiz_attempt::FINISHED) && ($attempt->sumgrades == 1)) {
				return true;
			}
		}
		return false;
	}
	
    function is_section_finished(&$section, $mods) {
        global $USER, $course;
        $completioninfo = new completion_info($course);
        $modules = get_course_section_mods($course->id, $section->id);
        $count = 0;
        if (count($modules) >= 1) {
            foreach ($modules as $modu) {
                $completiondata = $completioninfo->get_data($modu, true);
                // oncampus if ($completiondata->completionstate == 1 || $completiondata->completionstate == 2) {
				if ($modu->id == 15) {
					//print_object($modu);
					//echo 'completionstate: '.$completiondata->completionstate;
				}
				if ($completiondata->completionstate == 2) {
                    $count++;
                }
            }
            if ($count == count($modules)) {
                return true;
            } else {
                return false;
            }
        }
    }

    function first_unfinished_section() {
        if (is_array($this->sections) && is_array($this->mods)) {
            foreach ($this->sections as $section) {
                if ($section->section > 0) {
                    if (!$this->is_section_finished($section, $this->mods)) {
                        return $section->section;
                    }
                }
            }
        }
        return false;
    }

	function get_last_lection() {
		$chapters = $this->get_chapters();
		//print_object($chapters);
		$i = count($chapters) - 1;
		return $chapters[$i]['first_lection'] + $chapters[$i]['lections'] - 1;
	}
	
	function count_previous_chapter_lections($lection_number) {
		$chapter = $this->get_chapter_for_lection($lection_number);
		$lections = 0;
		$chapters = $this->get_chapters();
		foreach ($chapters as $c) {
			if ($c['number'] == $chapter['number']) {
				return $lections;
			}
			else {
				$lections += $c['lections'];
			}
		}
		return $lections;
	}
	
	function get_chapters() {
		global $CFG, $DB;
		require_once($CFG->dirroot.'/lib/blocklib.php');

		$coursecontext = context_course::instance($this->course->id);
		$blockrecord = $DB->get_record('block_instances', array('blockname' => 'oc_mooc_nav', 'parentcontextid' => $coursecontext->id), '*', MUST_EXIST);
		$blockinstance = block_instance('oc_mooc_nav', $blockrecord);
		$chapter_configtext = $blockinstance->config->chapter_configtext;
		
		$lines = preg_split( "/[\r\n]+/", trim($chapter_configtext));
		$chapters = array();
		$number = 0;
		$first = 1;
		foreach ($lines as $line) {
			$elements = explode(';', $line);
			$chapter = array();
			$chapter['number'] = $number;
			$number++;
			$chapter['first_lection'] = $first;
			foreach ($elements as $element) {
				$ex = explode('=', $element);
				$chapter[$ex[0]] = $ex[1];
			}
			$first += $chapter['lections'];
			$chapters[] = $chapter;
		}
		return $chapters;
	}
	
	function get_chapter($number) {
		$result = array();
		$chapters = $this->get_chapters();
		foreach ($chapters as $chapter) {
			if ($chapter['number'] == $number) {
				$result = $chapter;
			}
		}
		return $result;
	}
	
	function get_chapter_for_lection($lection) {
		global $CFG;
		$sections = 0;
		$chapters = $this->get_chapters();
		foreach ($chapters as $chapter) {
			$sections = $sections + $chapter['lections'];
			if ($sections >= $lection) {
				return $chapter;
			}
		}
		return false;
	}

}