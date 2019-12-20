<?php
/**
 * @author Alex Pandre
 * @copyright (c) 2009-current, Alex Pandre
 * @license MIT
 * @version 1.02s
 * @link https://github.com/apandre/execProfiler PHP Code Execution Profiler and variable watcher.
 * @uses    For executions and variable watching of your choosing.
 *          Its accumulate all information in property array,
 *          and then you can output all of it into many different ways.
 * @package execProfiler
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

/**
 *
 */
class execProfiler {
    /**
     * @var array $execTiming Property represent a container that accumulate all profiling information
     * and variable watching by storing it based on execution marker as a key.
     * @static
     */
    public static $execTiming = array();

    /**
     * Method start execution timing for specified marker
     *
     * @uses usage  Following code example should be used to start profiling:
     *                  $execMarker = execProfiler::startTimer("My Execution Marker");
     *              Make sure that variable name for $execMarker is unique within same scope
     *
     * @param string $marker
     * @param int $bkTrace  If equal 1, then result of debug_backtrace() will be included
     *                      the same way as watched variable.
     *
     * @return string Constructed execution marker, that made up from execution start time
     *                and provided parameter.
     */
    public static function startTimer( $marker, $bkTrace = 0 ) {
        $backtrace = debug_backtrace();
        $execStartMarker = $backtrace[0]['file'].' # '.$backtrace[0]['line'];
        $microtime = exec('date +%s.%N'); // microtime(TRUE);
        //$date = DateTime::createFromFormat('U.u', $microtime += 0.001);
        //$date_time_ms = $date->format('Y-m-d H:i:s.u');
        $date_time_ms = exec("date -d @$microtime +'%F %T.%N'");
        if (empty($marker)) {
            $execMarker = $date_time_ms;   //self::getExecMarkerAsFileAndLineNum();
        } else {
            $execMarker = $marker;
        }
        self::$execTiming[$execMarker]['   start time'] = $date_time_ms;
        self::$execTiming[$execMarker]['exec start in'] = $execStartMarker;
        self::$execTiming[$execMarker]['exec_started'] = $microtime;
        if ( $bkTrace == 1 ) {
            self::$execTiming[$execMarker]['backtrace'] = $backtrace;
        }
        return $execMarker;
    }

    /**
     * Method stop execution timing for specified marker
     *
     * @uses usage Following code example should be used to stop profiling:
     *                  execProfiler::stopTimer($execMarker);
     *
     * @param string $startMarker
     */
    public static function stopTimer( $startMarker ) {
        $backtrace = debug_backtrace();
        $execEndMarker = $backtrace[0]['file'].' # '.$backtrace[0]['line'];
        $microtime = exec('date +%s.%N'); // microtime(TRUE);
        //$date = DateTime::createFromFormat('U.u', $microtime += 0.001);
        //$date_time_ms = $date->format('Y-m-d H:i:s.u');
        $date_time_ms = exec("date -d @$microtime +'%F %T.%N'");
        self::$execTiming[$startMarker]['    stop time'] = $date_time_ms;
        self::$execTiming[$startMarker]['  exec end in'] = $execEndMarker;
        self::$execTiming[$startMarker]['exec_ended'] = $microtime;
        $total = self::$execTiming[$startMarker]['exec_ended'] - self::$execTiming[$startMarker]['exec_started'];
        unset(
            self::$execTiming[$startMarker]['exec_started'],
            self::$execTiming[$startMarker]['exec_ended']
        );
        self::$execTiming[$startMarker]['    exec time'] = sprintf("%01.16f", $total);
    }

    /**
     * Method add variable/object for watching to appropriate place in accumulated container.
     *
     * @uses usage Following code example should be used to add variable
     *             to variables watching list for specific execution marker:
     *                  execProfiler::addVarToWatch($execMarker, 'var_name', $var);
     *
     * @param string $mark
     * @param string $varname
     * @param any-type $var
     */
    public static function addVarToWatch( $mark, $varname, $var ) {
        self::$execTiming[$mark][' watched vars'][$varname] = $var;
    }


    /**
     * Method output information accumulated within self::$execTiming container
     *
     * @uses usage  Following code example should be used to output profiling and watched variables data:
     *                  execProfiler::outputExecStat(1);
     *              Usually, it is last peace of code within test script,
     *              unless developer wish to start another profiling accumulation session.
     *              Accumulation container will be emptied
     *
     * @param int $sortOutputBy Contain identification of order by which accumulated content
     *                          of self::$execTiming will be output.
     *                          Elements for each execution marker created by start time.
     *                          That is why it is default order.
     *                          If order property is set to 1, then elements of self::$execTiming
     *                          must be output by $element['    stop time'] value.
     *        0 - output order is by start time (default)
     *        1 - output order is by stop time
     *
     * @param float $longMarkersOnly
     * @param type $output
     * @param type $fullFilePath
     */
    public static function outputExecStat(
        $sortOutputBy = 0,
        $longMarkersOnly = 0,
        $outputLabel = '',
        $output = 'print',
        $fullFilePath = FALSE
    ) {
        switch ($output)
        {
            // variable watching included
            case 'var_export_output': {
                var_export(self::$execTiming);
                break;
            }
            // variable watching not included yet
            // in progress
            case 'tab_table': {
                print(
                    "\nTested Marker\t"
                    ."Execution start point\t"
                    ."Execution end point\t"
                    ."Execution time (seconds)"
                );
                foreach ( self::$execTiming as $key => $value ) {
                    if ( !$fullFilePath ) {
                        list($fileStart, $lineStart) = explode('#', $value['exec_start_marker']);
                        list($fileEnd, $lineEnd) = explode('#', $value['exec_end_marker']);
                        printf(
                            "\n$key\t"
                            .basename($fileStart).'#'.$lineStart."\t"
                            .basename($fileEnd).'#'.$lineEnd."\t%01.13f",
                            $value['exec_time']
                        );
                    } else {
                        printf(
                            "\n$key\t"
                            .$value['exec_start_marker']."\t"
                            .$value['exec_end_marker']."\t%01.13f",
                            $value['exec_time']
                        );
                    }
                }
                break;
            }
            // variable watching not included yet
            // in progress
            case 'printf': {
                foreach ( self::$execTiming as $key => $value ) {
                    printf(
                        "\nTested Marker:         $key"
                        ."\nExecution start point: ".$value['exec_start_marker']
                        ."\nExecution time:        %01.13f seconds."
                        ."\nExecution end point:   ".$value['exec_end_marker']."\n",
                        $value['exec_time']
                    );
                }
                break;
            }
            default:
            case 'print': {   // variable watching included
                if ($sortOutputBy == 1) {
                    $copy = array();
                    foreach (self::$execTiming as $key => $element) {
                        $copy[$element['    stop time'].' -- '.$key] = $element;
                    }
                    self::$execTiming = $copy;
                    unset($copy);
                    ksort(self::$execTiming);
                }
                if ($longMarkersOnly > 0) {
                    $copy = array();
                    foreach (self::$execTiming as $key => $element) {
                        if ((float)self::$execTiming[$key]['    exec time'] > (float)$longMarkersOnly) {
                            $copy[$key] = $element;
                        }
                    }
                    self::$execTiming = $copy;
                    unset($copy);
                }
                print(
                    "\n####### Begining of execProfiler output $outputLabel:\n".
                    json_encode(
                        self::$execTiming,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                    ).
                    "\n####### Ending of execProfiler output.\n"
                );
                break;
            }
        }
        self::$execTiming = array();
    }

    /**
     * Method generate execution marker based on file path and line #
     *
     * @return string
     */
    public static function getExecMarkerAsFileAndLineNum() {
        $backtrace = debug_backtrace();
        $execMarker = $backtrace[0]['file'].' # '.$backtrace[0]['line'];
        return $execMarker;
    }

    /**
     * work in progress
     *
     * @param type $execMarker
     * @return boolean
     */
    private static function is_file_line( $execMarker ) {
        if ( isset($execMarker) and !empty($execMarker) ) {
            if ( stripos($execMarker, '#') == strripos($execMarker, '#') ) {
                list($file, $line) = explode('#', $execMarker);
                if ( file_exists(realpath($file)) and is_numeric($line) ) {
                    return TRUE;
                } else {
                    return FALSE;
                }
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

}   // End of class execProfiler
