<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once __DIR__ . '/enosi-utils.php';
require_once __DIR__ . '/enosi-filesystem-singleton.php';
require_once __DIR__ . '/enosi-build-extractor.php';

/**
* Ce fichier contient toutes les méthodes nécessaires à la gestion
* de la page d'administration de l'extension WordPress.
*
* Cette séparation facilite la maintenance en isolant la logique
* liée uniquement à l'interface admin du reste du plugin.
*/

const STR_TITLE = "Unity Embedder";

// Fonction WORDPRESS pour l'ajout de l'extention
add_action('admin_menu', function (): void {
    add_menu_page(
        STR_TITLE,           // Titre de la page
        STR_TITLE,           // Titre du menu
        'manage_options',        // Capability
        'unity_webgl_admin',     // Slug
        'enosi_unity_admin_page',   // Callback
        '',                      // Icon (vide pour l’instant)
        6                        // Position
    );
});

// Add of main_admin.css for admin pages
add_action('admin_enqueue_scripts', fn() =>
wp_enqueue_style(
    'enosi-admin-page',
    plugins_url('../css/enosi-admin-page.css', __FILE__),
    [],
    filemtime(plugin_dir_path(__FILE__) . '../css/enosi-admin-page.css')
    )
);

// Défini la page d'administration des jeux téléversé sur wordpress.
function enosi_unity_admin_page(): void
{
    ?>
    <div class="wrap">
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px;">
    <div style="display: flex; align-items: center;">
    <img style="width: 32px; height: 32px; margin-right: 10px;" src="<?php echo esc_url( plugins_url('../res/unity_icon.svg', __FILE__) ); ?>" alt="Logo" class="logo" />
    <span style="font-size: 18px; color: #333; margin-right: 20px;">Embedder For Unity</span>
    </div>
    <a href="https://coff.ee/EnosiStudio" target="_blank" style="text-decoration: none; font-size: 16px; color: #0073aa; white-space: nowrap;">
    ☕ Support me
    </a>
    </div>
    
    <?php
    
    // @keep _e('Current language', 'enosi-embedder-unity');
    
    unityWebglAdminServerConfig();
    
    echo "<div class='simpleblock'>";
    echo '<h2>' . esc_html__('Build Manager', 'enosi-embedder-unity') . '</h2>';
    echo '<p>' . esc_html__('Use this page to add your Unity project by uploading the', 'enosi-embedder-unity') . ' <strong>.zip</strong> ' . esc_html__('folder of your project and manage it easily within the admin dashboard.', 'enosi-embedder-unity') . '</p>';
    ?>
    
    <form method="post" enctype="multipart/form-data">
    <input type="file" name="unity_zip" accept=".zip" required>
    <?php wp_nonce_field('upload_unity_zip_action', 'upload_unity_zip_nonce'); ?>
    <?php submit_button(__('Upload and Extract', 'enosi-embedder-unity')); ?>
    </form>
    <?php
    
    unityWebglHandleUpload();
    
    $upload_dir = wp_upload_dir();
    $builds_dir = $upload_dir['basedir'] . '/unity_webgl';
    
    // Creation of the folder
    $wpFS = EnosiFileSystemSingleton::getInstance()->getWpFilesystem();
    if ( ! $wpFS->is_dir( $builds_dir ) ) {
        $wpFS->mkdir( $builds_dir, 0755 );
    }
    
    // Supprimer un build si demandé
    if (EnosiUtils::isPostActionValid('delete_build', 'delete_build_nonce', 'delete_build_action')) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if(isset($_POST['build_name'])){
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $build_to_delete = basename(sanitize_text_field(wp_unslash($_POST['build_name'])));
            $full_path = $builds_dir . '/' . $build_to_delete;
            EnosiUtils::deleteFolder($full_path);
        }
    }
    
    $builds = EnosiUtils::listBuilds($builds_dir);
    
    // Delete all builds if requested.
    if (EnosiUtils::isPostActionValid('delete_all_builds', 'delete_all_builds_nonce', 'delete_all_builds_action')) {        
        if (!current_user_can('manage_options')) {
            wp_die('You are not allowed to do this action.');
        }
        
        foreach ($builds as $build) {
            $path = $builds_dir . '/' . $build;
            EnosiUtils::deleteFolder($path);
        }
        $builds = EnosiUtils::listBuilds($builds_dir);
        echo '<div class="notice notice-success"><p>' . esc_html__('All builds have been deleted.', 'enosi-embedder-unity') . '</p></div>';
    }
    
    echo '<table style="width: 100%; border-collapse: collapse;">';
    echo '<tr>
    <th style="text-align:left; border-bottom: 1px solid #ccc;">' . esc_html__('Name', 'enosi-embedder-unity') . '</th>
    <th style="text-align:left; border-bottom: 1px solid #ccc;">' . esc_html__('Path', 'enosi-embedder-unity') . '</th>
    <th style="text-align:center; border-bottom: 1px solid #ccc;">' . esc_html__('Size (MB)', 'enosi-embedder-unity') . '</th>
    <th style="text-align:right; border-bottom: 1px solid #ccc;"></th>
</tr>';
    
    foreach ($builds as $build) {
        $build_path = $builds_dir . '/' . $build;
        $size_bytes = EnosiUtils::getSize($build_path);
        $size_mb = round($size_bytes / 1048576, 2);
        
        echo '<tr>';
        echo '<td style="padding: 8px 0;">' . esc_html($build) . '</td>';
        echo '<td style="padding: 8px 0;">' . esc_html($build_path) . '</td>';
        echo '<td style="padding: 8px 8px; text-align:right;">' . esc_html( $size_mb) . '</td>';
        echo '<td style="padding: 8px 0; text-align:right;">';
        echo '<form method="post" onsubmit="return confirm(\'❌ ' . esc_html__('Permanently delete build:', 'enosi-embedder-unity') . ' ' . esc_js($build) . ' ?\');" style="margin:0;">';
        echo '<input type="hidden" name="build_name" value="' . esc_attr($build) . '">';
        wp_nonce_field('delete_build_action', 'delete_build_nonce');
        submit_button('Delete', 'delete', 'delete_build', false);
        echo '</form></td></tr>';
    }
    echo '</table>';
    
    // No build or create a delete all builds btn.
    if (empty($builds)) {
        echo '<p>' . esc_html__('No build found.', 'enosi-embedder-unity') . '</p>';
    }else {
        echo '<form method="post" onsubmit="return confirm(\'❌ ' . esc_js(__('Delete ALL builds?', 'enosi-embedder-unity')) . '\');" style="margin-bottom: 16px;">';
        echo '<input type="hidden" name="delete_all_builds" value="1">';
        wp_nonce_field('delete_all_builds_action', 'delete_all_builds_nonce');
        submit_button('🧨 ' . __('Delete all builds', 'enosi-embedder-unity'), 'delete');
        echo '</form>';
    }
    
    echo '</div></div>';
    echo '<div class="footer">';
    /* translators: %s is the link to Enosi Studio */
    echo '<p>' . sprintf(esc_html__('Plugin developed by %s.', 'enosi-embedder-unity'),
    '<a href="https://enosistudio.com/" target="_blank" rel="noopener noreferrer">Enosi Studio</a>') . '</p>';
    echo '</div>';
}

/**
* Extracted server configuration block to reduce cognitive complexity.
*/
function unityWebglAdminServerConfig(): void
{
    $serverType = EnosiUtils::detectServer();
    echo "<div class='simpleblock'>";
    switch($serverType) {
        case 'apache': {
            echo '<h2>' . esc_html__( 'Server configuration: Apache detected.', 'enosi-embedder-unity' ) . '</h2>';
            if (EnosiUtils::isPostActionValid('add_wasm_mime', 'add_wasm_mime_nonce', 'add_wasm_mime_action')) {
                EnosiUtils::setupWasmMime();
            } elseif (EnosiUtils::isPostActionValid('del_wasm_mime', 'del_wasm_mime_nonce', 'del_wasm_mime_action')) {
                EnosiUtils::removeWasmMimeSetup();
            }
            
            // Check htaccess pour le type MIME
            if(EnosiUtils::isWasmMimeConfigured()){
                echo '<form method="post" style="display: flex; align-items: center; gap: 10px;">';
                wp_nonce_field('del_wasm_mime_action', 'del_wasm_mime_nonce');
                submit_button(__('Delete the MIME type for .wasm', 'enosi-embedder-unity'), 
                'primary', 
                'del_wasm_mime');
                echo '<span style="color:green;">✅ ' . esc_html__('The MIME type for .wasm files is already configured in the .htaccess.', 'enosi-embedder-unity') . '</span>';
                echo '</form>';
            }else{
                echo '<form method="post" style="display: flex; align-items: center; gap: 10px;">';
                wp_nonce_field('add_wasm_mime_action', 'add_wasm_mime_nonce');
                submit_button(
                    esc_html__('Configure the MIME type for .wasm', 'enosi-embedder-unity'),
                    'primary',
                    'add_wasm_mime'
                );
                echo '<span style="color:orange;">⚠️ ' . esc_html__('The MIME type for .wasm files is not configured in the .htaccess. A warning will be shown in the console at each build launch.', 'enosi-embedder-unity') . '</span>';
                echo '</form>';
            }
            echo '<p>' . esc_html__('The attempt to add or remove may fail for security reasons.', 'enosi-embedder-unity') . '<br />' .
            esc_html__('In that case, the configuration must be done manually in the .htaccess file.', 'enosi-embedder-unity') . '<br />' .
            esc_html__('Any server configuration change requires a manual server restart.', 'enosi-embedder-unity') . '</p>';
            break;
        }
        case 'nginx': {
            echo '<h2>' . esc_html__('Server configuration: Nginx detected.', 'enosi-embedder-unity') . '</h2>';
            echo '<p>' . esc_html__('Please configure the MIME type for .wasm files in your Nginx configuration.', 'enosi-embedder-unity') . '<br />' .
            esc_html__('Automatic detection and configuration of the MIME type for .wasm files is only supported on Apache servers.', 'enosi-embedder-unity') . '</p>';
            break;
        }
        default:{
            // translators: %s is the detected server configuration type.
            echo '<h2>' . sprintf(esc_html__('Server configuration: unknown(%s) detected.', 'enosi-embedder-unity'),esc_html($serverType)) . '</h2>';
            echo '<p>' . esc_html__('Automatic detection and configuration of the MIME type for .wasm files is only supported on Apache servers.', 'enosi-embedder-unity') . '</p>';
        }
    }
    echo "</div>";
}

// Handles the upload of a Unity WebGL project ZIP file
function unityWebglHandleUpload(): void
{
    // Verify nonce and user permissions
    if (!EnosiUtils::isPostNonceValid('upload_unity_zip_nonce', 'upload_unity_zip_action') 
        || !current_user_can('manage_options')) {
        return;
    }
    
    // Retrieve uploaded file info
    // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $file = $_FILES['unity_zip'] ?? null;
    if (!$file || empty($file['tmp_name']) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        EnosiUtils::error(__('No valid file uploaded.', 'enosi-embedder-unity'));
        return;
    }
    
    $tmpName = $file['tmp_name'];
    $originalName = $file['name'];
    $buildName = pathinfo($originalName, PATHINFO_FILENAME);
    $buildNameLower = strtolower($buildName);
    
    // Validate MIME type and file extension
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmpName);
    finfo_close($finfo);
    
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($mime !== 'application/zip' || $ext !== 'zip') {
        EnosiUtils::error(__('Only ZIP files are allowed.', 'enosi-embedder-unity'));
        return;
    }
    
    // Prepare target directories
    $uploadDir = wp_upload_dir();
    if (empty($uploadDir['basedir'])) {
        EnosiUtils::error(__('Unable to retrieve the upload directory.', 'enosi-embedder-unity'));
        return;
    }
    
    $unityWebFolder = $uploadDir['basedir'] . '/unity_webgl/';
    $targetDir = $unityWebFolder . sanitize_title($buildName);
    
    // Ensure the main Unity WebGL folder exists
    if (!file_exists($unityWebFolder) && !wp_mkdir_p($unityWebFolder)) {
        EnosiUtils::error(__('Unable to create the unity_webgl folder.', 'enosi-embedder-unity'));
        return;
    }
    
    // Initialize the WordPress filesystem
    if (!EnosiFileSystemSingleton::getInstance()->getWpFilesystem()) {
        EnosiUtils::error(__('Unable to initialize WordPress filesystem.', 'enosi-embedder-unity'));
        return;
    }
    
    global $wp_filesystem;
    
    // Delete any existing build folder and prepare the target directory
    if ((file_exists($targetDir) && !$wp_filesystem->delete($targetDir, true)) 
        || (!$wp_filesystem->is_dir($targetDir) && !wp_mkdir_p($targetDir))) {
        EnosiUtils::error(__('Unable to prepare target directory: ', 'enosi-embedder-unity') . $targetDir);
        return;
    }
    
    // Extract the required Unity build files
    $extractor = new EnosiBuildExtractor($tmpName, $targetDir, [
        $buildNameLower . '.data',
        $buildNameLower . '.wasm',
        $buildNameLower . '.framework.js',
        $buildNameLower . '.loader.js'
    ]);
    
    if ($extractor->extract()) {
        echo '<p style="color:green;">✅ Success: Build extracted and validated.</p>';
    } else {
        // Clean up target folder if extraction fails
        EnosiUtils::deleteFolder($targetDir);
    }
}
?>
