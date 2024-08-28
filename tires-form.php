<?php
/*
Plugin Name: Formulario Llantas
Description: Formulario para hacer busqueda de llantas por vehículos o medidas.
Version: 1.0
Author: Danilo Zuluaga
*/

add_action('admin_menu', 'tires_plugin_menu');

function tires_plugin_menu()
{
    add_menu_page(
        'Formulario Llantas',
        'Llantas',
        'manage_options',
        'tires-form-menu',
        'tires_plugin_pagina_contenido',
        'dashicons-admin-generic',
        20
    );
}

function tires_plugin_pagina_contenido()
{
    if (isset($_POST['gemini_api_key'])) {
        if (check_admin_referer('gemini_api_key_nonce', 'tires_plugin_nonce_field')) {
            $token = sanitize_text_field($_POST['tires_plugin_token']);
            update_option('tires_plugin_token', $token);
            echo '<div class="updated"><p>¡Token guardado correctamente!</p></div>';
        } else {
            echo '<div class="error"><p>Fallo de validación de seguridad. Inténtalo de nuevo.</p></div>';
        }
    }

    // Obtener el token guardado de la base de datos
    $token = get_option('tires_plugin_token', '');

?>
    <div class="wrap">
        <h1><?php _e('Configuración del Token', 'tires-plugin-textdomain'); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field('gemini_api_key_nonce', 'tires_plugin_nonce_field'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Token de API', 'tires-plugin-textdomain'); ?></th>
                    <td><input type="text" name="tires_plugin_token" value="<?php echo esc_attr($token); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button('Guardar Token'); ?>
        </form>
    </div>
<?php
}

function tires_form_enqueue_assets()
{
    wp_enqueue_style('tires-form-styles', plugins_url('style.css', __FILE__));
    wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
    wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true);
    wp_enqueue_script('tires-form-script', plugins_url('script.js', __FILE__), array('jquery', 'select2'), null, true);

    wp_localize_script('tires-form-script', 'tiresFormData', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'tires_form_enqueue_assets');

function tires_form_shortcode()
{
    ob_start();
?>
    <form id="tires-form" method="get" action="<?php echo esc_url(home_url('/llantas')); ?>">
        <div class="tires-switch">
            <p>Vehículo</p>
            <?php echo do_shortcode('[toggle_switch label="vehicle"]'); ?>
            <p>Dimensión</p>
        </div>
        <div class="form-row">
            <div id="form-vehicle">
                <select id="mark" name="mark" class="select2-input" placeholder="Marca"></select>
                <select id="year" name="year" class="select2-input" placeholder="Año"></select>
                <select id="line" name="line" class="select2-input" placeholder="Línea"></select>
                <select id="version" name="version" class="select2-input" placeholder="Versión"></select>
            </div>
            <div id="form-dimension">
                <select id="width" name="width" class="select2-input" placeholder="Ancho"></select>
                <select id="ratio" name="ratio" class="select2-input" placeholder="Perfil"></select>
                <select id="rim" name="rim" class="select2-input" placeholder="Rin"></select>
            </div>
            <button type="submit" class="tire-search-button">
                <i class="fa fa-search"></i>
            </button>
        </div>
    </form>
<?php
    return ob_get_clean();
}
add_shortcode('tires_form', 'tires_form_shortcode');

function tires_form_ajax_search()
{
    global $wpdb;
    $option = sanitize_text_field($_GET['option']);
    $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';

    // Define results based on action (replace with actual logic)
    $results = array();

    switch ($option) {
        case 'mark_search':
            $results = $wpdb->get_results("SELECT DISTINCT marca as id, marca as text FROM vehiculos WHERE marca LIKE '%" . $term . "%' ORDER BY marca ASC LIMIT 20");
            break;
        case 'year_search':
            $mark = isset($_GET['mark']) ? sanitize_text_field($_GET['mark']) : '';
            $results = $wpdb->get_results("SELECT DISTINCT anio as id, anio as text FROM vehiculos WHERE marca = '" . $mark . "' AND anio LIKE '%" . $term . "%' ORDER BY anio DESC LIMIT 20");
            break;
        case 'line_search':
            $mark = isset($_GET['mark']) ? sanitize_text_field($_GET['mark']) : '';
            $year = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : '';
            $results = $wpdb->get_results("SELECT DISTINCT linea as id, linea as text FROM vehiculos WHERE marca = '" . $mark . "' AND anio = '" . $year . "' AND linea LIKE '%" . $term . "%' ORDER BY linea ASC LIMIT 20");
            break;
        case 'version_search':
            $mark = isset($_GET['mark']) ? sanitize_text_field($_GET['mark']) : '';
            $year = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : '';
            $line = isset($_GET['line']) ? sanitize_text_field($_GET['line']) : '';
            $results = $wpdb->get_results("SELECT DISTINCT `version` as id, `version` as text FROM vehiculos WHERE marca = '" . $mark . "' AND anio = '" . $year . "' AND linea = '" . $line . "' AND `version` LIKE '%" . $term . "%' ORDER BY version ASC LIMIT 20");
            break;
        case 'width_search':
            $results = $wpdb->get_results("SELECT DISTINCT ancho as id, ancho as text FROM dimensiones_llantas WHERE ancho LIKE '%" . $term . "%' ORDER BY ancho ASC LIMIT 20");
            break;
        case 'ratio_search':
            $width = isset($_GET['width']) ? sanitize_text_field($_GET['width']) : '';
            $results = $wpdb->get_results("SELECT DISTINCT perfil as id, perfil as text FROM dimensiones_llantas WHERE ancho = '" . $width . "' AND perfil LIKE '%" . $term . "%' ORDER BY perfil ASC LIMIT 20");
            break;
        case 'rim_search':
            $width = isset($_GET['width']) ? sanitize_text_field($_GET['width']) : '';
            $ratio = isset($_GET['ratio']) ? sanitize_text_field($_GET['ratio']) : '';
            $results = $wpdb->get_results("SELECT DISTINCT rin as id, rin as text FROM dimensiones_llantas WHERE ancho = '" . $width . "' AND perfil = '" . $ratio . "' AND rin LIKE '%" . $term . "%' ORDER BY rin ASC LIMIT 20");
            break;
    }

    wp_send_json($results);
}
add_action('wp_ajax_tires_form_search', 'tires_form_ajax_search');
add_action('wp_ajax_nopriv_tires_form_search', 'tires_form_ajax_search');

function tires_form_process_redirect()
{
    $dimension = null;
    $validate = false;
    if (isset($_GET['switch_vehicle']) && $_GET['switch_vehicle'] == 'on' && !empty($_GET['width'] ?? '') && !empty($_GET['rim'] ?? '')) {
        $validate = true;
        $ancho = sanitize_text_field($_GET['width'] ?? '');
        $perfil = sanitize_text_field($_GET['ratio'] ?? '');
        $rin = sanitize_text_field($_GET['rim'] ?? '');

        if ($perfil == '-') {
            $dimension = "{$ancho}R{$rin}";
        } else {
            $dimension = "{$ancho}/{$perfil}R{$rin}";
        }
    }
    if (isset($_GET['mark']) && isset($_GET['year']) && isset($_GET['line'])) {
        $validate = true;
        global $wpdb;
        $dimension = $wpdb->get_var($wpdb->prepare(
            "SELECT llanta_dimension FROM vehiculos WHERE marca = %s AND anio = %d AND linea = %s",
            $_GET['mark'],
            $_GET['year'],
            $_GET['line']
        ));
    }

    if ($validate) {
        $url = add_query_arg('dimension', $dimension ? urlencode($dimension) : '', home_url('/resultados'));
        wp_redirect($url);
        exit;
    }
}
add_action('template_redirect', 'tires_form_process_redirect');

function tires_result_shortcode()
{
    ob_start();
    if (isset($_GET['dimension']) && !empty($_GET['dimension'])) {
        $dimension = sanitize_text_field($_GET['dimension']);

        echo '<div class="tires-result-container">';

        echo '<h3 class="titleFind">DIMENSIÓN RECOMENDADA: <span class="tyrecommended">' . esc_html($dimension) . '</span></h3>';

        $shortcode = sprintf('[products limit="12" attribute="pa_dimension" terms="%s"]', $dimension);
        echo do_shortcode($shortcode);

        $similar_terms = encontrar_equivalencias($dimension);
        echo var_dump($similar_terms);

        if (($pos = array_search($dimension, $similar_terms)) !== false) {
            unset($similar_terms[$pos]);
        }

        if (!empty($similar_terms)) {
            echo '<h3>Dimensiones equivalentes:</h3>';
            echo '<p>' . implode(', ', $similar_terms) . '</p>';

            $shortcode = sprintf('[products limit="12" attribute="pa_dimension" terms="%s"]', implode(',', $similar_terms));
            echo do_shortcode($shortcode);
        }

        echo '<p class="back-to-shop"><a style="margin-left:-20px;" class="button wc-backward" href="' . esc_url(home_url('/llantas')) . '">Volver a la tienda</a></p>';

        echo '</div>';
    }
    return ob_get_clean();
}
add_shortcode('tires_result', 'tires_result_shortcode');


function calcular_diametro_total($ancho, $perfil, $rin)
{
    $rin_mm = $rin * 25.4;
    return ($ancho * $perfil / 100 * 2) + $rin_mm;
}

function encontrar_equivalencias($dimension)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'equivalences';

    $equivalence = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT equivalence FROM $table_name WHERE original = %s",
            $dimension
        )
    );
    $equivalence = array_map(function ($item) {
        return empty($item->equivalence) ? null : $item->equivalence;
    }, $equivalence);
    echo '<pre>' . json_encode($equivalence) . '</pre>';

    if ($equivalence === null ||  empty($equivalence[0])) {
        $equivalence = equivalencesFromGemini($dimension);

        if ($equivalence) {
            foreach ($equivalence as $value) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'original' => $dimension,
                        'equivalence' => $value
                    )
                );
            }
        }
    }

    return $equivalence;
}

function formatoLlanta($llanta)
{
    return $llanta->ancho . '/' . $llanta->perfil . 'R' . $llanta->rin;
}

function equivalencesFromGemini($dimension)
{
    $token = get_option('tires_plugin_token', '');
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$token";
    $arg = array(
        'body' => json_encode(array(
            "contents" => [
                "parts" => [
                    [
                        "text" => "Neumáticos equivalentes a $dimension"
                    ]
                ]
            ],
            "systemInstruction" => [
                "role" => "user",
                "parts" => [
                    [
                        "text" => "Respuesta de array simple sin texto adicional"
                    ]
                ]
            ],
            "generationConfig" => [
                "responseMimeType" => "application/json"
            ],
        )),
        'headers' => array(
            'Content-Type' => 'plain/text',
        ),
    );

    $response = wp_remote_post($url, $arg);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        echo "Error en la solicitud: $error_message";
        return;
    }


    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true)['candidates'][0]['content']['parts'][0]['text'];

    return json_decode($data, true);
}

function create_table_equivalences()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'equivalences';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        original VARCHAR(20) NOT NULL,
        equivalence VARCHAR(20) NOT NULL
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'create_table_equivalences');
