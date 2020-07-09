<?php
/**
 * @author Alex Pandre
 * @copyright (c) 2009-current, Alex Pandre
 * @license MIT
 * @version 1.08
 * @link https://github.com/apandre/execProfiler PHP Code Execution Profiler and variable watcher.
 * @uses    For executions and variable watching of your choosing.
 *          Its accumulate all information in property array,
 *          and then you can log output all of it into many different ways.
 * @package execProfiler
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

global $execProfilerEnabled;

if (!isset($execProfilerEnabled) || empty($execProfilerEnabled)) {
    /**
     * class with empty methods
     */
    class execProfiler {
        public static $enable;
        public static $execTiming = array();
        public static $backTraceEnabled = 0;
        public static $fractionalTimeMethod;
        public static $defaultLogLevel = 0;
        public static function startTimer() {}
        public static function stopTimer() {}
        public static function addVarToWatch() {}
        public static function outputExecStat() {}
        public static function debugLog() {}
        public static function getExecMarkerAsFileAndLineNum() {}
        private static function is_file_line() {}
        public static function pdo_debugStrParams() {}
    }
} else {
    /**
     * Execution Profiler class
     */
    class execProfiler {

        public static $enable;

        /**
         * @var array $execTiming Property represent a container that accumulate all profiling information
         * and variable watching by storing it based on execution marker as a key.
         * @static
         */
        public static $execTiming = array();

        /**
         * BackTrace enabling flag
         */
        public static $backTraceEnabled = 0;

        /**
         * This property define how we set fractional time.
         *      'php_microtime_true' for microtime(true) way,
         *      'php_microtime' for microtime() way,
         *      'system_tsn' for exec('date +%s.%N') - timestamp with nanoseconds
         */
        public static $fractionalTimeMethod = 'php_microtime_true';

        /**
         * Default Logging Level
         */
        public static $defaultLogLevel = 0;


        /**
         * Obtain fractional time
         */
        public static function getFractionalTime()
        {
            if (self::$fractionalTimeMethod == 'php_microtime') {
                // slow
                $mt = explode(' ', microtime());
                $microtime = $mt[1] . '.' . $mt[0];
                $date = DateTime::createFromFormat('U.u', $microtime += 0.000000001);
                $date_time_ms = $date->format('Y-m-d H:i:s.u');
            }

            if (self::$fractionalTimeMethod == 'php_microtime_true') {
                // fast
                $microtime = microtime(TRUE);
                /*
                // OO style doesn't works properly in PHP 7.0.x
                $date = DateTime::createFromFormat('U.u', number_format($microtime, 6, '.', ''));
                $date_time_ms = $date->format('Y-m-d H:i:s.u');
                */
                $date_time_ms = date_create_from_format(
                    'U.u',
                    number_format($microtime, 6, '.', '')
                )->setTimezone(
                    (new \DateTimeZone('America/New_York'))
                )->format('Y-m-d H:i:s.u');
            }

            if (self::$fractionalTimeMethod == 'system_tsn') {
                // fast
                $microtime = exec('date +%s.%N');
                $date_time_ms = exec("date -d @$microtime +'%F %T.%N'");
            }

            return (object) [
                'microtime' => $microtime,
                'date_time_ms' => $date_time_ms
            ];
        }


        /**
         * Method start execution timing for specified marker
         *
         * @uses usage  Following code example should be used to start profiling:
         *                  $execMarker = execProfiler::startTimer("My Execution Marker");
         *              Make sure that variable name for $execMarker is unique within same scope
         *
         * @param string $marker
         * @static integer self::$backTraceEnabled  If equal 1, then result of debug_backtrace() will be included
         *                      the same way as watched variable.
         *
         * @return string Constructed execution marker, that made up from execution start time
         *                and provided parameter.
         */
        public static function startTimer( $marker ) {
            $backtrace = debug_backtrace();
            $execStartMarker = $backtrace[0]['file'].' # '.$backtrace[0]['line'];

            $mtObj = self::getFractionalTime();

            if (empty($marker)) {
                $execMarker = $mtObj->date_time_ms;   //self::getExecMarkerAsFileAndLineNum();
            } else {
                $execMarker = $marker;
            }
            self::$execTiming[$execMarker]['start time'] = $mtObj->date_time_ms;
            self::$execTiming[$execMarker]['  start in'] = $execStartMarker;
            self::$execTiming[$execMarker]['started'] = $mtObj->microtime;
            /*
            if ( self::$backTraceEnabled == 1 ) {
                self::$execTiming[$execMarker]['backtrace'] = $backtrace;
            }
            */
            return $execMarker;
        }


        /**
         * Method stop execution timing for specified marker
         *
         * @uses usage  Following code example should be used to stop profiling:
         *                  execProfiler::stopTimer($execMarker);
         *              Where $execMarker is a string marker
         *              defined during method execProfiler::startTimer() call.
         *
         * @param string $marker
         */
        public static function stopTimer( $marker, $varname = '', $var = null, $logLevel = 7 ) {
            $backtrace = debug_backtrace();
            $execEndMarker = $backtrace[0]['file'].' # '.$backtrace[0]['line'];

            $mtObj = self::getFractionalTime();

            if (!empty($varname) && !empty($var)) {
                self::addVarToWatch($marker, $varname, $var, $logLevel, $backtrace);
            }

            self::$execTiming[$marker][' stop time'] = $mtObj->date_time_ms;
            self::$execTiming[$marker]['    end in'] = $execEndMarker;

            self::$execTiming[$marker]['ended'] = $mtObj->microtime;
            $total = self::$execTiming[$marker]['ended'] - self::$execTiming[$marker]['started'];
            unset(
                self::$execTiming[$marker]['started'],
                self::$execTiming[$marker]['ended']
            );
            self::$execTiming[$marker][' exec time'] = sprintf("%01.16f", $total);
            if ( self::$backTraceEnabled == 1 ) {
                self::$execTiming[$marker]['backtrace'] = $backtrace;
            }
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
         * @param mix $var
         */
        public static function addVarToWatch(
            $mark,
            $varname,
            $var,
            $logLevel = 7,
            $stopTimerBackTrace = null
        )
        {
            if (isset($logLevel) && $logLevel > self::$defaultLogLevel) {
                return;
            }

            if (empty($stopTimerBackTrace)) {
                $backtrace = debug_backtrace();
                $file = $backtrace[0]['file'];
                $line = $backtrace[0]['line'];
            } else {
                $file = $stopTimerBackTrace[0]['file'];
                $line = $stopTimerBackTrace[0]['line'];
            }
            $varName = sprintf("%s # %d -- %s", basename($file), $line, $varname);
            $varName = rtrim($varName, "- ");
            self::$execTiming[$mark]['watching vars'][$varName] = $var;
        }


        /**
         * @todo Later try this:
         *          use function outputExecStat as log;
         */


        /**
         * Method output information accumulated within self::$execTiming container
         *
         * @uses usage  Following code example should be used to output profiling and variables watching:
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
                            $value[' exec_time']
                        );
                    }
                    break;
                }
                default:
                case 'print': {   // variable watching included
                    if ($sortOutputBy == 1) {
                        $copy = array();
                        foreach (self::$execTiming as $key => $element) {
                            $copy[$element[' stop time'].' -- '.$key] = $element;
                        }
                        self::$execTiming = $copy;
                        unset($copy);
                        ksort(self::$execTiming);
                    }
                    if ($longMarkersOnly > 0) {
                        $copy = array();
                        foreach (self::$execTiming as $key => $element) {
                            if ((float)self::$execTiming[$key][' exec time'] > (float)$longMarkersOnly) {
                                $copy[$key] = $element;
                            }
                        }
                        self::$execTiming = $copy;
                        unset($copy);
                    }
                    print(
                        "\n####### Begining of execProfiler output $outputLabel:\n"
                        . json_encode(
                            self::$execTiming,
                            JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES+JSON_UNESCAPED_UNICODE
                        )
                        . "\n####### Ending of execProfiler output.\n\n"
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


        /**
         * Debug Logger
         * This setting use to set logging level:
         *      0 - Silent (silent)
         *      1 - Emergency (emerg)
         *      2 - Alerts (alert)
         *      3 - Critical (crit)
         *      4 - Errors (err)
         *      5 - Warnings (warn)
         *      6 - Notification (notice)
         *      7 - Information (info)
         *      8 - Debug (debug)
         *      9 - Debug + sql
         *      ...
         */
        public static function debugLog($param, $logLevel = 8, $label = "")
        {
            global $execProfilerEnabled;

            if (!isset(self::$defaultLogLevel) || self::$defaultLogLevel === 0) {
                return;
            }
            if (isset($logLevel) && $logLevel > self::$defaultLogLevel) {
                return;
            }
            $backtrace = debug_backtrace();
            $file = $backtrace[0]['file'];
            $line = $backtrace[0]['line'];
            if (!empty($execProfilerEnabled)) {
                if (is_array($param)) {
                    printf(
                        "\n\n%s : # %d: %s\n%s\n",
                        $file,
                        $line,
                        $label,
                        json_encode(
                            $param,
                            JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES+JSON_UNESCAPED_UNICODE
                        )
                    );
                } else {
                    printf("\n%s : # %d: %s\n%s\n", $file, $line, $label, $param);
                }

            }
        }


        /**
         * Method obtain output of PDO->debugDumpParams() as a string
         */
        public static function pdo_debugStrParams(&$stmt)
        {
            if (empty($stmt)) {
                return false;
            }
            ob_start();
            $stmt->debugDumpParams();
            $r = ob_get_contents();
            ob_end_clean();
            return $r;
        }

    }   // End of class execProfiler
}
