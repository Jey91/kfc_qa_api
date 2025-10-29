<?php
namespace Utils;

/**
 * Simple logging utility for the application
 */
class Logger
{
    /**
     * Log levels
     */
    const ERROR = 'ERROR';
    const WARNING = 'WARNING';
    const INFO = 'INFO';
    const DEBUG = 'DEBUG';

    /**
     * @var string Path to the log file
     */
    private $logFile;

    /**
     * @var string Minimum log level to record
     */
    private $minLevel;

    /**
     * @var bool Whether to also log to PHP error log
     */
    private $useErrorLog;

    /**
     * Constructor
     * 
     * @param string $logFile Path to log file (optional)
     * @param string $minLevel Minimum log level (optional)
     * @param bool $useErrorLog Whether to also log to PHP error_log (optional)
     */
    public function __construct($logFile = null, $minLevel = self::INFO, $useErrorLog = true)
    {
        // If no log file specified, create one in the logs directory
        if ($logFile === null) {
            $logsDir = dirname(__DIR__) . '/logs';

            // Create logs directory if it doesn't exist
            if (!is_dir($logsDir)) {
                mkdir($logsDir, 0755, true);
            }

            $this->logFile = $logsDir . '/app_' . date('Y-m-d') . '.log';
        } else {
            $this->logFile = $logFile;
        }

        $this->minLevel = $minLevel;
        $this->useErrorLog = $useErrorLog;

        // Check if log file is writable
        $this->checkLogFile();
    }

    /**
     * Log an error message
     * 
     * @param string $message Message to log
     * @param array $context Additional context data (optional)
     * @return bool Success status
     */
    public function error($message, array $context = [])
    {
        return $this->log(self::ERROR, $message, $context);
    }

    /**
     * Log a warning message
     * 
     * @param string $message Message to log
     * @param array $context Additional context data (optional)
     * @return bool Success status
     */
    public function warning($message, array $context = [])
    {
        return $this->log(self::WARNING, $message, $context);
    }

    /**
     * Log an info message
     * 
     * @param string $message Message to log
     * @param array $context Additional context data (optional)
     * @return bool Success status
     */
    public function info($message, array $context = [])
    {
        return $this->log(self::INFO, $message, $context);
    }

    /**
     * Log a debug message
     * 
     * @param string $message Message to log
     * @param array $context Additional context data (optional)
     * @return bool Success status
     */
    public function debug($message, array $context = [])
    {
        return $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Log a message with the specified level
     * 
     * @param string $level Log level
     * @param string $message Message to log
     * @param array $context Additional context data (optional)
     * @return bool Success status
     */
    public function log($level, $message, array $context = [])
    {
        // Check if we should log this level
        if (!$this->shouldLog($level)) {
            return false;
        }

        // Format the log entry
        $logEntry = $this->formatLogEntry($level, $message, $context);

        // Write to log file
        $result = file_put_contents($this->logFile, $logEntry . PHP_EOL, FILE_APPEND);

        // Also log to PHP error_log if enabled
        if ($this->useErrorLog) {
            // Specify the destination as 0 to use the system logger
            // or specify a file path explicitly
            error_log($logEntry, 0);
            // Alternative: error_log($logEntry, 3, 'path/to/error.log');
        }

        return ($result !== false);
    }

    /**
     * Format a log entry
     * 
     * @param string $level Log level
     * @param string $message Message to log
     * @param array $context Additional context data
     * @return string Formatted log entry
     */
    private function formatLogEntry($level, $message, array $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $requestId = $this->getRequestId();

        // Replace placeholders in message with context values
        $interpolatedMessage = $this->interpolate($message, $context);

        // Format context as JSON if not empty
        $contextString = !empty($context) ? ' ' . json_encode($context) : '';

        return "[{$timestamp}] [{$level}] [{$requestId}] [{$ipAddress}] {$interpolatedMessage}{$contextString}";
    }

    /**
     * Replace placeholders in the message with context values
     * 
     * @param string $message Message with placeholders
     * @param array $context Values to replace placeholders
     * @return string Interpolated message
     */
    private function interpolate($message, array $context = [])
    {
        // Build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            // Handle different value types appropriately
            if (is_array($val)) {
                $replace['{' . $key . '}'] = json_encode($val);
            } elseif (is_object($val)) {
                if (method_exists($val, '__toString')) {
                    $replace['{' . $key . '}'] = (string) $val;
                } else {
                    $replace['{' . $key . '}'] = json_encode($val);
                }
            } elseif (is_resource($val)) {
                $replace['{' . $key . '}'] = 'resource';
            } else {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }

        // Interpolate replacement values into the message
        return strtr($message, $replace);
    }

    /**
     * Check if the given log level should be logged
     * 
     * @param string $level Log level to check
     * @return bool Whether to log this level
     */
    private function shouldLog($level)
    {
        $levels = [
            self::DEBUG => 1,
            self::INFO => 2,
            self::WARNING => 3,
            self::ERROR => 4
        ];

        // If level is not recognized, default to logging it
        if (!isset($levels[$level]) || !isset($levels[$this->minLevel])) {
            return true;
        }

        // Log if level is at or above minimum level
        return $levels[$level] >= $levels[$this->minLevel];
    }

    /**
     * Get or generate a unique ID for the current request
     * 
     * @return string Request ID
     */
    private function getRequestId()
    {
        if (!isset($_SERVER['REQUEST_ID'])) {
            $_SERVER['REQUEST_ID'] = substr(md5(uniqid(mt_rand(), true)), 0, 10);
        }

        return $_SERVER['REQUEST_ID'];
    }

    /**
     * Check if the log file is writable
     * 
     * @throws \RuntimeException If log file is not writable
     */
    private function checkLogFile()
    {
        // Create the file if it doesn't exist
        if (!file_exists($this->logFile)) {
            $directory = dirname($this->logFile);

            // Create directory if it doesn't exist
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0755, true)) {
                    throw new \RuntimeException("Failed to create log directory: {$directory}");
                }
            }

            // Touch the file to create it
            if (!touch($this->logFile)) {
                throw new \RuntimeException("Failed to create log file: {$this->logFile}");
            }

            // Set permissions
            chmod($this->logFile, 0644);
        }

        // Check if file is writable
        if (!is_writable($this->logFile)) {
            throw new \RuntimeException("Log file is not writable: {$this->logFile}");
        }
    }

    /**
     * Set the minimum log level
     * 
     * @param string $level Minimum log level
     * @return $this For method chaining
     */
    public function setMinLevel($level)
    {
        $this->minLevel = $level;
        return $this;
    }

    /**
     * Set whether to use PHP error_log
     * 
     * @param bool $use Whether to use error_log
     * @return $this For method chaining
     */
    public function setUseErrorLog($use)
    {
        $this->useErrorLog = (bool) $use;
        return $this;
    }

    /**
     * Set the log file path
     * 
     * @param string $filePath Path to log file
     * @return $this For method chaining
     */
    public function setLogFile($filePath)
    {
        $this->logFile = $filePath;
        $this->checkLogFile();
        return $this;
    }
}