<?php namespace Igormatkovic\Livelogger;

use Illuminate\Events\Dispatcher;
use Illuminate\Log\Writer;
use Monolog\Logger as MonologLogger;
use Config;


class Livelogger extends Writer
{


    /**
     * @var
     */
    protected $pusher;

    /**
     * @var array
     */
    protected $levels = array(
        'debug',
        'info',
        'notice',
        'warning',
        'error',
        'critical',
        'alert',
        'emergency',
        'live'
    );

    /**
     * Construct the logger
     * @param MonologLogger $monolog
     * @param Dispatcher $dispatcher
     */
    public function __construct(MonologLogger $monolog, Dispatcher $dispatcher = null)
    {
        $this->monolog = $monolog;

        if (isset($dispatcher)) {
            $this->dispatcher = $dispatcher;
        }

        Config::package('igormatkovic/laravel-livelogger', 'laravel-livelogger');

        $this->loadPusher();


    }


    /**
     * Fires a log event.
     *
     * @param  string $level
     * @param  string $message
     * @param  array $context
     * @return void
     */
    protected function fireLogEvent($level, $message, array $context = array())
    {

        $message = compact('level', 'message', 'context');

        self::writeLog($message);


        // If the event dispatcher is set, we will pass along the parameters to the
        // log listeners. These are useful for building profilers or other tools
        // that aggregate all of the log messages for a given "request" cycle.
        if (isset($this->dispatcher) && $level != 'live') {
            $this->dispatcher->fire('illuminate.log', $message);
        }
    }

    /**
     * Write a message in LiveLogger
     * @param $message
     *
     * @return bool
     */
    private function writeLog($message)
    {

        if (self::isLoggable($message['level'])) {


            $message['date'] = date(Config::get('laravel-livelogger::dateformat'));

            return $this->pusher->trigger(Config::get('laravel-livelogger::channel_name', 'livelogger'), 'log', $message);
        }
    }


    /**
     * Load Pusher class and config
     */
    private function loadPusher()
    {

        if (!$this->pusher) {

            $app_id = Config::get('laravel-livelogger::pusher_app_id', false);
            $app_key = Config::get('laravel-livelogger::pusher_api_key', false);
            $app_secret = Config::get('laravel-livelogger::pusher_api_secret', false);

            if (Config::get('laravel-livelogger::pusher_use_ssl', false)) {
                $this->pusher = new \Pusher($app_key, $app_secret, $app_id, false, 'https://api.pusherapp.com', 443);
            } else {
                $this->pusher = new \Pusher($app_key, $app_secret, $app_id);
            }
        }

    }

    /**
     * Parse the string level into a Monolog constant.
     *
     * @param  string $level
     * @return int
     *
     * @throws \InvalidArgumentException
     */
    protected function parseLevel($level)
    {
        switch ($level) {
            case 'debug':
                return MonologLogger::DEBUG;

            case 'info':
                return MonologLogger::INFO;

            case 'notice':
                return MonologLogger::NOTICE;

            case 'warning':
                return MonologLogger::WARNING;

            case 'error':
                return MonologLogger::ERROR;

            case 'critical':
                return MonologLogger::CRITICAL;

            case 'alert':
                return MonologLogger::ALERT;

            case 'emergency':
                return MonologLogger::EMERGENCY;

            case 'live':
                return 650;

            default:
                throw new \InvalidArgumentException("Invalid log level.");
        }
    }


    /**
     * Check if the provided log level can be logged or not
     * @param $level
     * @return bool
     */
    private function isLoggable($level)
    {
        $log_level = Config::get('laravel-livelogger::log_level', 'info');

        if (self::parseLevel($level) >= self::parseLevel($log_level)) {

            return true;
        }

        return false;

    }


    /**
     * Call Monolog with the given method and parameters.
     *
     * @param  string  $method
     * @param  mixed   $parameters
     * @return mixed
     */
    protected function callMonolog($method, $parameters)
    {
        if($method != 'addLive') {
            if (is_array($parameters[0])) {
                $parameters[0] = json_encode($parameters[0]);
            }

            return call_user_func_array(array($this->monolog, $method), $parameters);
        }
    }

}
