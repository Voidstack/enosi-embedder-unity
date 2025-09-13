<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once __DIR__ . '/enosi-filesystem-singleton.php';

class EnosiUtils {
    public static function generateUuid(): string {
        return uniqid('unity_', true);
    }
    
    /**
    * Deletes a folder and its contents in a WordPress-friendly way.
    * Uses EnosiFileSystemSingleton for directories and wp_delete_file for files.
    *
    * @param string $dir Path to the directory to delete.
    * @return bool True on success, false on failure.
    */
    public static function deleteFolder(string $dir): bool {
        if (!is_dir($dir)) {
            EnosiUtils::error(esc_html__( 'Not a valid directory', 'enosi-embedder-unity' ) . ' : ' . $dir);
            return false;
        }
        
        $fs = EnosiFileSystemSingleton::getInstance();
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        
        foreach ($files as $file) {
            $path = $file->getRealPath();
            if ($file->isDir()) {
                $fs->rmdir($path);
            } else {
                wp_delete_file($path);
            }
        }
        
        $success = $fs->rmdir($dir, true);
        if (!$success) {
            EnosiUtils::error(esc_html__( 'Failed to delete directory', 'enosi-embedder-unity' ) . ' : ' . $dir);
        }
        return $success;
    }
    
    public static function arrayToString(array $arr): string {
        return implode(', ', $arr);
    }
    
    /**
     * Sanitizes and validates a POST nonce.
     * @param string $nonceKey The key in the $_POST array containing the nonce.
     * @param string $action The action name used to create the nonce.
     * @return bool True if the nonce is valid, false otherwise.
     */
    public static function isPostNonceValid(string $nonceKey, string $action): bool {
        return !empty($_POST[$nonceKey])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonceKey])), $action);
    }
    
    /**
    * Sanitizes and validates a POST action using a nonce.
    * @param string $postKey The key in the $_POST array to check.
    * @param string $nonceKey The key in the $_POST array containing the nonce.
    * @param string $action The action name used to create the nonce.
    * @return bool True if the action is valid, false otherwise.
    */
    public static function isPostActionValid(string $postKey, string $nonceKey, string $action): bool {
        return !empty($_POST[$postKey]) && !empty($_POST[$nonceKey])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonceKey])), $action);
    }
    
    // Returns the list of subfolders present in $builds_dir.
    public static function listBuilds($builds_dir) {
        $builds = [];
        foreach (scandir($builds_dir) as $entry) {
            if ($entry !== '.' && $entry !== '..' && is_dir($builds_dir . '/' . $entry)) {
                $builds[] = $entry;
            }
        }
        return $builds;
    }
    
    /**
    * Calculates the size in bytes of a file or directory (recursively).
    *
    * @param string $path Path to the file or directory.
    * @return int|float Size in bytes, 0 if the path does not exist.
    */
    public static function getSize(string $path): int|float {
        if (is_file($path)) {
            return filesize($path);
        }
        if (!is_dir($path)) {
            return 0;
        }
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }
    
    // Detects the web server used (Apache or Nginx).
    public static function detectServer(): string { $serverSoftware = isset($_SERVER['SERVER_SOFTWARE'])? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'])): '';
        
        $serverSoftware = sanitize_text_field($serverSoftware); 
        
        if (stripos($serverSoftware, 'apache') !== false) {
            return 'apache';
        }
        if (stripos($serverSoftware, 'nginx') !== false) {
            return 'nginx';
        }
        return $serverSoftware ?: 'unknown';
    }
    
    
    /**
    * Checks if the MIME type for .wasm files is configured in .htaccess.
    * @return bool True if the directive is present, false otherwise.
    */
    public static function isWasmMimeConfigured(): bool {
        $server = self::detectServer();
        if ($server === 'apache') {
            $htaccessPath = isset($_SERVER['DOCUMENT_ROOT']) ? sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) . '/.htaccess': '';
            $directive = 'AddType application/wasm .wasm';
            return file_exists($htaccessPath) && strpos(file_get_contents($htaccessPath), $directive) !== false;
        }
        return false;
    }
    
    /*
    * Configures the MIME type for .wasm files in the .htaccess or Nginx configuration file.
    * @return bool True if the configuration was successful, false otherwise.
    */
    public static function setupWasmMime(): bool {
        $server = self::detectServer();
        
        if ($server === 'apache') {
            $htaccessPath = '';
            if ( isset($_SERVER['DOCUMENT_ROOT']) ) {
                $htaccessPath = sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) . '/.htaccess';
            }
            $directive = "AddType application/wasm .wasm\n";
            
            if (!file_exists($htaccessPath)) {
                return file_put_contents($htaccessPath, $directive) !== false;
            }
            
            $content = file_get_contents($htaccessPath);
            if (strpos($content, trim($directive)) !== false)
                {
                return true;
            }
            
            $content .= $directive;
            return file_put_contents($htaccessPath, $content) !== false;
        }
        return false;
    }
    
    /**
    * Removes the MIME directive for .wasm files from the .htaccess or Nginx configuration file.
    * @return bool True if the removal was successful, false otherwise.
    */
    public static function removeWasmMimeSetup(): bool {
        $server = self::detectServer();
        
        if ($server === 'apache') {
            $htaccessPath = '';
            if ( isset($_SERVER['DOCUMENT_ROOT']) ) {
                $htaccessPath = sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) . '/.htaccess';
            }            $directive = "AddType application/wasm .wasm";
            
            if (!file_exists($htaccessPath)){
                return true;
            }
            
            $content = file_get_contents($htaccessPath);
            $newContent = str_replace($directive . "\n", '', $content);
            $newContent = str_replace($directive, '', $newContent);
            
            return file_put_contents($htaccessPath, $newContent) !== false;
            
        }
        return false;
    }
    
    /**
    * Renames all extracted files and folders to lowercase, recursively.
    */
    public static function lowercaseRecursive(string $dir): void {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $oldPath = $dir . DIRECTORY_SEPARATOR . $item;
            $newName = mb_strtolower($item);
            $newPath = $dir . DIRECTORY_SEPARATOR . $newName;
            
            // If the name changes, rename
            if ($newPath !== $oldPath) {
                $fs = EnosiFileSystemSingleton::getInstance();
                $fs->rename($oldPath, $newPath, true);
            }
            
            if (is_dir($newPath)) {
                self::lowercase_recursive($newPath);
            }
        }
    }
    
    // Display an error message in the WordPress admin interface
    public static function error(string $message): void {
        echo "<p style='color:red;'>❌ " . esc_html__( 'Error: ', 'enosi-embedder-unity' ) . wp_kses_post( $message ) . "</p>";
    }
    
    // Display an informational message in the WordPress admin interface
    public static function info(string $message): void {
        echo "<p style='color:black;'>ℹ️ " . esc_html__( 'Info: ', 'enosi-embedder-unity' ) . wp_kses_post( $message ) . "</p>";
    }
    
    // Display a validation message in the WordPress admin interface
    public static function valid(string $message): void {
        echo "<p style='color:green;'>✅ " . esc_html__( 'Success: ', 'enosi-embedder-unity' ) . wp_kses_post( $message ) . "</p>";
    }
}
