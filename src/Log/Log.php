<?php
namespace Log;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class Log {

    const MDIRECTOR_OPTIONS_KEY = 'options';
    const MDIRECTOR_SECRET_KEY = 'mdirector_secret';

    protected static $instance;

    /**
     * Method to return the Monolog instance
     *
     * @return Logger
     */
    static public function getLogger()
    {
        if (! self::$instance) {
            self::configureInstance();
        }

        return self::$instance;
    }

    /**
     * Configure Monolog to use a rotating files system.
     *
     */
    protected static function configureInstance()
    {
        $dir = MDIRECTOR_LOGS_PATH;

        if (!file_exists($dir)){
            mkdir($dir, 0777, true);
        }

        $logger = new Logger(MDIRECTOR_NEWSLETTER);
        $logger->pushHandler(new RotatingFileHandler($dir . DIRECTORY_SEPARATOR . 'main.log', 5));

        self::$instance = $logger;
    }

    protected static function getDefaultContext() {
        return  [
            'v' => MDIRECTOR_NEWSLETTER_VERSION
        ];
    }

    protected static function buildOptions($context) {
        if (isset($context[self::MDIRECTOR_OPTIONS_KEY])) {
            $context[self::MDIRECTOR_OPTIONS_KEY][self::MDIRECTOR_SECRET_KEY]
                = str_repeat('*', 10);
        }

        return array_merge(self::getDefaultContext(), $context);
    }

    public static function debug($message, array $context = []){
        self::getLogger()->addDebug($message, self::buildOptions($context));
    }

    public static function info($message, array $context = []){
        self::getLogger()->addInfo($message, self::buildOptions($context));
    }

    public static function notice($message, array $context = []){
        self::getLogger()->addNotice($message, self::buildOptions($context));
    }

    public static function warning($message, array $context = []){
        self::getLogger()->addWarning($message, self::buildOptions($context));
    }

    public static function error($message, array $context = []){
        self::getLogger()->addError($message, self::buildOptions($context));
    }

    public static function critical($message, array $context = []){
        self::getLogger()->addCritical($message, self::buildOptions($context));
    }

    public static function alert($message, array $context = []){
        self::getLogger()->addAlert($message, self::buildOptions($context));
    }

    public static function emergency($message, array $context = []){
        self::getLogger()->addEmergency($message, self::buildOptions($context));
    }
}
