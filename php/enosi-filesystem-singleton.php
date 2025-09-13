<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class EnosiFileSystemSingleton {
    private static $instance = null;
    private $wpFilesystem;
    
    private function __construct() {
        if ( ! function_exists('WP_Filesystem') ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;
        $this->wpFilesystem = $wp_filesystem;
    }
    
    public static function getInstance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __call($method, $args) {
        return call_user_func_array([$this->wpFilesystem, $method], $args);
    }
    
    /**
    * Renames a file or directory, handling case-only name changes on case-insensitive filesystems.
    *
    * On some filesystems (e.g., Windows, macOS), renaming a file where only the letter case changes
    * (e.g., "myfile.txt" to "MyFile.txt") may not be recognized as a valid rename. In that case,
    * this method performs a temporary rename to ensure the case change is applied.
    *
    * @param string $source      Absolute path to the source file or directory.
    * @param string $destination Absolute path to the destination file or directory.
    * @param bool   $overwrite   Whether to overwrite the destination if it exists.
    *
    * @return bool True on success, false on failure.
    */
    public function rename($source, $destination, $overwrite = true) {
        // Normalized
        $source      = untrailingslashit($source);
        $destination = untrailingslashit($destination);
        
        // If same path (just different case), we make a temporary move
        if (strcasecmp($source, $destination) === 0) {
            $tempPath = $destination . '_tmp_' . wp_generate_password(6, false);
            if (! $this->wpFilesystem->move($source, $tempPath, $overwrite)) {
                return false;
            }
            return $this->wpFilesystem->move($tempPath, $destination, $overwrite);
        }
        
        // Normal case: direct move
        return $this->wpFilesystem->move($source, $destination, $overwrite);
    }
    
    public function getWpFilesystem() {
        return $this->wpFilesystem;
    }
}
