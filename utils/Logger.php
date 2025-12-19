<?php
/**
 * Logging Utility
 * 
 * Provides structured logging with automatic rotation and multiple severity levels.
 */

class Logger
{
    private const LOG_DIR = __DIR__ . '/../logs';
    private const MAX_LOG_DAYS = 30;
    
    // Log levels
    private const LEVEL_INFO = 'INFO';
    private const LEVEL_WARNING = 'WARNING';
    private const LEVEL_ERROR = 'ERROR';
    private const LEVEL_SECURITY = 'SECURITY';
    
    /**
     * Log an informational message
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log a warning message
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log an error message
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Log a security event
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public static function security(string $message, array $context = []): void
    {
        self::log(self::LEVEL_SECURITY, $message, $context);
    }
    
    /**
     * Write log entry to file
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    private static function log(string $level, string $message, array $context): void
    {
        try {
            // Ensure log directory exists
            if (!is_dir(self::LOG_DIR)) {
                mkdir(self::LOG_DIR, 0755, true);
            }
            
            // Determine log file based on level
            $filename = $level === self::LEVEL_SECURITY ? 'security.log' : 'app.log';
            $filepath = self::LOG_DIR . '/' . $filename;
            
            // Build log entry
            $entry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_id' => $_SESSION['id'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null
            ];
            
            // Write as JSON for easy parsing
            $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            
            // Append to log file
            file_put_contents($filepath, $line, FILE_APPEND | LOCK_EX);
            
            // Rotate logs if needed
            self::rotateLogs();
            
        } catch (Exception $e) {
            // Fallback to error_log if logging fails
            error_log("Logger failed: " . $e->getMessage());
            error_log("Original message: [$level] $message");
        }
    }
    
    /**
     * Rotate old log files
     * 
     * @return void
     */
    private static function rotateLogs(): void
    {
        try {
            $files = glob(self::LOG_DIR . '/*.log');
            $cutoffTime = time() - (self::MAX_LOG_DAYS * 24 * 60 * 60);
            
            foreach ($files as $file) {
                // Check if file is older than MAX_LOG_DAYS
                if (filemtime($file) < $cutoffTime) {
                    unlink($file);
                }
            }
        } catch (Exception $e) {
            // Ignore rotation errors
            error_log("Log rotation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get recent log entries
     * 
     * @param int $limit Number of entries to retrieve
     * @param string|null $level Filter by level (optional)
     * @return array Array of log entries
     */
    public static function getRecent(int $limit = 100, ?string $level = null): array
    {
        try {
            $filepath = self::LOG_DIR . '/app.log';
            
            if (!file_exists($filepath)) {
                return [];
            }
            
            // Read file in reverse
            $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_reverse($lines);
            
            $entries = [];
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                
                if ($entry === null) {
                    continue;
                }
                
                // Filter by level if specified
                if ($level !== null && $entry['level'] !== $level) {
                    continue;
                }
                
                $entries[] = $entry;
                
                if (count($entries) >= $limit) {
                    break;
                }
            }
            
            return $entries;
            
        } catch (Exception $e) {
            error_log("Failed to read logs: " . $e->getMessage());
            return [];
        }
    }
}
