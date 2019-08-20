<?php
/**
 * @author Alex Pandre
 * @copyright (c) 2009-current, Alex Pandre
 * @license MIT
 * @version 1.00s
 * @link git@github.com:apandre/execProfiler.git Code Profiler and variables watch
 *          for executions and variable watching of your choosing.
 *          Its accumulate all information in one array,
 *          and then you can output all of it into many different ways.
 */

ini_set('display_errors', 1);

/**
 *
 *
 */
class execProfiler {
    /**
     * Property represent a container that accumulate all profiling information
     * and variable watching by storing it based on execution marker as a key.
     *
     * @static array
     */
    public static $execTiming;

    /**
     * Contain identification of order by which accumulated content of self::$execTiming will be output.
     * Elements for each execution marker created by start time.
     * That is why it is default order.
     * If order property is set to 1, then elements of self::$execTiming must be output
     * by $element['    stop time'] value.
     *
     * @static
     * @var int 0 - output order is by start time (default)
     *          1 - output order is by stop time
     */
    public static $sortOutputBy = 0;

    /**
     * Method start execution timing for specified marker
     *
     * @param string $marker
     */
    public static function startTimer( $marker ) {
        $backtrace = debug_backtrace();
        $execStartMarker = $backtrace[0]['file'].' # '.$backtrace[0]['line'];
        $microtime = microtime(TRUE);
        $date = DateTime::createFromFormat('U.u', $microtime);
        $date_time_ms = $date->format('Y-m-d H:i:s.u');
        if (empty($marker)) {
            $execMarker = self::execMarker();
        } else {
            $execMarker = $marker;
        }
        self::$execTiming[$execMarker]['   start time'] = $date_time_ms;
        self::$execTiming[$execMarker]['exec start in'] = $execStartMarker;
        self::$execTiming[$execMarker]['exec_started'] = $microtime;
        return $execMarker;
    }

    /**
     * Method stop execution timing for specified marker
     *
     * @param string $startMarker
     */
    public static function stopTimer( $startMarker ) {
        $backtrace = debug_backtrace();
        $execEndMarker = $backtrace[0]['file'].' # '.$backtrace[0]['line'];
        $microtime = microtime(TRUE);
        $date = DateTime::createFromFormat('U.u', $microtime);
        $date_time_ms = $date->format('Y-m-d H:i:s.u');
        self::$execTiming[$startMarker]['    stop time'] = $date_time_ms;
        self::$execTiming[$startMarker]['  exec end in'] = $execEndMarker;
        self::$execTiming[$startMarker]['exec_ended'] = $microtime;
        $total = self::$execTiming[$startMarker]['exec_ended'] - self::$execTiming[$startMarker]['exec_started'];
        unset(
            self::$execTiming[$startMarker]['exec_started'],
            self::$execTiming[$startMarker]['exec_ended']
        );
        self::$execTiming[$startMarker]['    exec time'] = $total;
    }

    /**
     * Method add variable/object for watching to appropriate place in accumulated container.
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
     * @param int $sortOutputBy Contain identification of order by which accumulated content
     *                          of self::$execTiming will be output.
     *                          Elements for each execution marker created by start time.
     *                          That is why it is default order.
     *                          If order property is set to 1, then elements of self::$execTiming
     *                          must be output by $element['    stop time'] value.
     *            0 - output order is by start time (default)
     *            1 - output order is by stop time
     *
     * @param type $output
     * @param type $fullFilePath
     */
    public static function outputExecStat( $sortOutputBy = 0, $output = 'print', $fullFilePath = FALSE ) {
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
                print(
                    "\n".
                    json_encode(
                        self::$execTiming,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                    )
                );
                unset(self::$execTiming);
            }
        }
    }

    /**
     * Method generate execution marker based on file path and line #
     *
     * @return string
     */
    public static function execMarker() {
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
