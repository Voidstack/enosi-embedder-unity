<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once __DIR__ . '/enosi-utils.php';
require_once __DIR__ . '/enosi-filesystem-singleton.php';

class EnosiBuildExtractor {
    // Path to the ZIP archive
    private string $zipPath;
    // Target directory for the extracted build
    private string $targetDir;
    // List of expected files in the build
    private array $expectedFiles;
    // Temporary directory used for extraction
    private string $tmpDir;
    
    public function __construct(string $zipPath, string $targetDir, array $expectedFiles) {
        $this->zipPath = $zipPath;
        $this->targetDir = $targetDir;
        $this->expectedFiles = array_map('strtolower', $expectedFiles);
        $this->tmpDir = $targetDir . '_tmp_' . wp_generate_password(8, false);
    }
    
    public function extract(): bool {
        EnosiUtils::info(__('Starting extraction', 'enosi-embedder-unity'));
        $result = true;
        
        if (!$this->createTempDirectory() || !$this->extractZipToTemp() || !$this->processExtractedFiles() || !$this->moveToTargetDirectory())
            {
            $result = false;
        }
        
        return $result;
    }
    
    // Create the temporary directory
    private function createTempDirectory(): bool {
        if (!wp_mkdir_p($this->tmpDir)) {
            EnosiUtils::error(__('Unable to create temporary extraction folder.', 'enosi-embedder-unity'));
            return false;
        }
        return true;
    }
    
    // Extract the ZIP file to the temporary directory
    private function extractZipToTemp(): bool {
        $zip = new ZipArchive;
        if ($zip->open($this->zipPath) !== true) {
            // translators: %s is the path to the zip file that couldn't be opened.
            EnosiUtils::error(sprintf(__('Unable to open the .zip file (%s)', 'enosi-embedder-unity'), $this->zipPath));
            return false;
        }
        if (!$zip->extractTo($this->tmpDir)) {
            $zip->close();
            EnosiUtils::deleteFolder($this->tmpDir);
            EnosiUtils::error(__('Extraction failed to temporary folder.', 'enosi-embedder-unity'));
            return false;
        }
        $zip->close();
        return true;
    }
    
    // Rename files/folders to lowercase and verify expected files
    private function processExtractedFiles(): bool {
        $this->lowercaseAllFilenames($this->tmpDir);
        
        if (!$this->verifyExtractedFiles($this->tmpDir)) {
            EnosiUtils::deleteFolder($this->tmpDir);
            EnosiUtils::error(
                __('Missing expected build files. ', 'enosi-embedder-unity') .
                EnosiUtils::arrayToString($this->expectedFiles) . '</br>' .
                __('The .zip file MUST have the same name as the files it contains.', 'enosi-embedder-unity')
            );
            return false;
        }
        return true;
    }
    
    // Move the temporary directory to the target location
    private function moveToTargetDirectory(): bool {
        if (file_exists($this->targetDir)) {
            EnosiUtils::deleteFolder($this->targetDir);
        }
        $fsSingleton = EnosiFileSystemSingleton::getInstance();
        if (!$fsSingleton->move($this->tmpDir, $this->targetDir, true)) {
            EnosiUtils::deleteFolder($this->tmpDir);
            EnosiUtils::error(__('Failed to move build to target directory.', 'enosi-embedder-unity'));
            return false;
        }
        return true;
    }
    
    // Verify that all expected files are present in the extracted archive
    private function verifyExtractedFiles(): bool {
        $foundFiles = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->tmpDir));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                // Add the file name in lowercase for comparison
                $foundFiles[] = strtolower($file->getFilename());
            }
        }
        // Display found files for debugging
        EnosiUtils::info(__('Files found: ', 'enosi-embedder-unity') . EnosiUtils::arrayToString($foundFiles));
        foreach ($this->expectedFiles as $expected) {
            if (!in_array($expected, $foundFiles)) {
                return false;
            }
        }
        return true;
    }
    
    // Recursively rename all files and folders to lowercase
    private function lowercaseAllFilenames(string $dir): void {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $item) {
            $oldPath = $item->getPathname();
            $dirPath = $item->getPath();
            $lowerName = strtolower($item->getFilename());
            $newPath = $dirPath . DIRECTORY_SEPARATOR . $lowerName;
            
            // If the name changes only in casing, some systems (e.g. Windows) may not recognize the change
            // Workaround: rename to a temporary name, then to the final lowercase name
            if ($oldPath !== $newPath) {
                $fs = EnosiFileSystemSingleton::getInstance();
                $fs->rename($oldPath, $newPath, true);
            }
        }
    }
}
