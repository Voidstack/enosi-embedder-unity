<?php

// empêche l'accès direct au fichier PHP via l'URL
defined('ABSPATH') or die;

/**
* Plugin Name: ENOSI Embedder For Unity
* Plugin URI:  https://enosistudio.com/
* Description: Displays a Unity WebGL game inside your page.
* Version: 0.1
* Author: MARTIN Baptiste / Voidstack
* Author URI: https://www.linkedin.com/in/baptiste-martin56/
* License: GPL-3.0-or-later
* License URI: https://www.gnu.org/licenses/gpl-3.0.html
* Tested up to: 6.8.2
* Stable tag: 0.1
* Text Domain: enosi-embedder-unity
* Domain Path: /languages
*/

require_once plugin_dir_path(__FILE__) . 'php/enosi-utils.php';

// Allows to load the administration page script, only for administration (optimization)
if(is_admin()){
    require_once plugin_dir_path(__FILE__) . 'php/enosi-admin-page.php';
    require_once plugin_dir_path(__FILE__) . 'php/enosi-unity-block.php'; // ne s'exécute que dans l'éditeur de blocs (page/post avec Gutenberg)
}

// Add of main.css
add_action('wp_enqueue_scripts', fn() =>
    wp_enqueue_style(
        'enosi-main-css',
        plugins_url('css/enosi-main.css', __FILE__),
        [],
        filemtime(plugin_dir_path(__FILE__) . 'css/enosi-main.css')
    )
);

// Add of main_admin.css for admin pages
add_action('admin_enqueue_scripts', fn() =>
    wp_enqueue_style(
        'enosi-admin-main-css',
        plugins_url('css/enosi-main-admin.css', __FILE__),
        [],
        filemtime(plugin_dir_path(__FILE__) . 'css/enosi-main-admin.css')
    )
);

// Filtre pour charger le script comme module
add_filter('script_loader_tag', fn($tag,$handle)=>$handle==='unity-webgl'?str_replace('<script ','<script type="module" ',$tag):$tag,10,2);

/**
 * Shortcode [unity_webgl build="mygame" showOptions="true" showOnMobile="false" showLogs="false" sizemode="fixed-height" fixedheight="500" aspectratio="4/3"]
 */
function unity_webgl_shortcode($atts): string {
    static $loader_cache=[];
    $a = shortcode_atts([
        'build'=>'','showoptions'=>'true','showonmobile'=>'false','showlogs'=>'false',
        'sizemode'=>'fixed-height','fixedheight'=>500,'aspectratio'=>'4/3'
    ], array_change_key_case($atts, CASE_LOWER));

    $args=[
        'build'=>sanitize_title($a['build']),
        'showOptions'=>filter_var($a['showoptions'],FILTER_VALIDATE_BOOLEAN),
        'showOnMobile'=>filter_var($a['showonmobile'],FILTER_VALIDATE_BOOLEAN),
        'showLogs'=>filter_var($a['showlogs'],FILTER_VALIDATE_BOOLEAN),
        'sizeMode'=>sanitize_text_field($a['sizemode']),
        'fixedHeight'=>(int)$a['fixedheight'],
        'aspectRatio'=>sanitize_text_field($a['aspectratio']),
    ];

    $up = wp_upload_dir();
    $build_path = "{$up['basedir']}/unity_webgl/{$args['build']}";
    $build_url  = trailingslashit("{$up['baseurl']}/unity_webgl/{$args['build']}");
    $loader_file = "$build_path/{$args['build']}.loader.js";

    if(!isset($loader_cache[$args['build']])) {
        $loader_cache[$args['build']] = file_exists($loader_file);
    }
    if(!$loader_cache[$args['build']]) return "<p style='color:red'>".esc_html__("Unity build not found:","enosi-embedder-unity")." ".esc_html($loader_file)."</p>";
    if(wp_is_mobile() && !$args['showOnMobile']) return "<p>🚫 ".esc_html__("Not available on mobile","enosi-embedder-unity")."</p>";

    $style = match($args['sizeMode']){
        'fixed-height'=>"height:{$args['fixedHeight']}px;",
        'aspect-ratio'=>"aspect-ratio:{$args['aspectRatio']};",
        default=>''
    };

    $uuid = EnosiUtils::generateUuid();

    if(!wp_script_is('unity-webgl','enqueued')){
        $script_file = plugin_dir_path(__FILE__).'js/client-unity-block.js';
        wp_enqueue_script('unity-webgl',plugins_url('js/client-unity-block.js',__FILE__),[],filemtime($script_file),true);
    }

    wp_localize_script('unity-webgl','EnosiUnityData',array_merge($args,[
        'buildUrl'=>$build_url,'loaderName'=>basename($loader_file,'.loader.js'),
        'uuid'=>$uuid,'urlAdmin'=>admin_url('/wp-admin/admin.php'),
        'currentUserIsAdmin'=>current_user_can('administrator'),
        'admMessage'=>__('TempMsg','enosi-embedder-unity')
    ]));

    ob_start(); ?>
    <div id="<?php echo esc_attr($uuid)?>-error" class="unity-error"></div>
    <div id="<?php echo esc_attr($uuid)?>-container" class="unity-container" style="<?php echo esc_attr($style)?>">
        <canvas id="<?php echo esc_attr($uuid)?>-canvas" class="unity-canvas"
            data-build-url="<?php echo esc_attr($build_url)?>"
            data-loader-name="<?php echo esc_attr(basename($loader_file,'.loader.js'))?>"
            data-show-options="<?php echo $args['showOptions']?'true':'false'?>"
            data-show-logs="<?php echo $args['showLogs']?'true':'false'?>"
            data-size-mode="<?php echo esc_attr($args['sizeMode'])?>"
            data-fixed-height="<?php echo esc_attr($args['fixedHeight']); ?>"
            data-aspect-ratio="<?php echo esc_attr($args['aspectRatio'])?>">
        </canvas>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('unity_webgl','unity_webgl_shortcode');