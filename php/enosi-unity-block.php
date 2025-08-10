<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once __DIR__ . '/enosi-utils.php';

/**
* Returns an array of strings to translate for the JS interface.
*/
function wpunityGetTranslatableStrings(): array {
    return [
        'buildChoose' => __('Choose a unity build', 'enosi-embedder-unity') . ' : ',
        'buildSelectionne' => __('Selected build', 'enosi-embedder-unity'),
        'warnExpectedRatio' => '⚠️ ' . __("Expected format: number/number (4/3) \nIf the format is invalid, the default value will be 4/3.", 'enosi-embedder-unity'),
        'showOptions' => __('Display options', 'enosi-embedder-unity'),
        'showOnMobile' => __('Display game on mobile', 'enosi-embedder-unity'),
        'showLogs' => __('Display logs in the console', 'enosi-embedder-unity')
    ];
}

/**
* Registers the Unity WebGL block and enqueues the necessary editor script.
*/
function unityEnqueueBlock(): void
{
    // Register the editor JavaScript file
    wp_register_script(
        'enosi-unity-block',
        plugins_url('../js/editor-unity-block.js', __FILE__),
        ['wp-blocks', 'wp-element', 'wp-editor', 'wp-i18n'],
        filemtime(plugin_dir_path(__FILE__) . '../js/editor-unity-block.js'),
        true
    );
    
    // Ajout des trad dans le script
    wp_localize_script('enosi-unity-block', 'WP_I18N', wpunityGetTranslatableStrings());
    
    // Pass global plugin data to JS
    wp_localize_script('enosi-unity-block', 'EnosiUnityData', [
        'urlAdmin' => admin_url('/admin.php'),
    ]);
    
    // Register the block type
    register_block_type('wpunity/unity-webgl', [
        'editor_script' => 'enosi-unity-block',
        'editor_style' => 'enosi-unity-block-style',
        'style' => 'enosi-unity-block-style',
    ]);
}

// enqueue_block_editor_assets ne s'exécute que dans l'éditeur de blocs (page/post avec Gutenberg).
add_action('enqueue_block_editor_assets', 'unityEnqueueBlock');

/*
Cette fonction crée un tableau builds des dossiers présents dans uploads/unity_webgl.
Elle le passe à JS sous le nom global unityBuildsData.
JS peut ensuite lire unityBuildsData.builds pour afficher la liste.
*/
function unityWebglLocalizeBuilds(): void
{
    $upload_dir = wp_upload_dir();
    $builds_dir = $upload_dir['basedir'] . '/unity_webgl';
    
    $builds = EnosiUtils::listBuilds($builds_dir);
    
    wp_localize_script('enosi-unity-block', 'unityBuildsData', ['builds' => $builds]);
}
add_action('enqueue_block_editor_assets', 'unityWebglLocalizeBuilds');
