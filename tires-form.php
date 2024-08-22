<?php
/*
Plugin Name: Formulario Llantas
Description: Formulario para hacer busqueda de llantas por vehículos o medidas.
Version: 1.0
Author: Danilo Zuluaga
*/

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
            $results = $wpdb->get_results("SELECT DISTINCT anio as id, anio as text FROM vehiculos WHERE marca = '" . $mark . "' AND anio LIKE '%" . $term . "%' ORDER BY anio ASC LIMIT 20");
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
            $results = array(
                array('id' => 1, 'text' => 'Tipo A'),
                array('id' => 2, 'text' => 'Tipo B'),
                array('id' => 3, 'text' => 'Tipo C'),
            );
            break;
        case 'ratio_search':
            $results = array(
                array('id' => 1, 'text' => 'Tipo A'),
                array('id' => 2, 'text' => 'Tipo B'),
                array('id' => 3, 'text' => 'Tipo C'),
            );
            break;
        case 'rim_search':
            $results = array(
                array('id' => 1, 'text' => 'Tipo A'),
                array('id' => 2, 'text' => 'Tipo B'),
                array('id' => 3, 'text' => 'Tipo C'),
            );
            break;
    }

    wp_send_json($results);
}
add_action('wp_ajax_tires_form_search', 'tires_form_ajax_search');
add_action('wp_ajax_nopriv_tires_form_search', 'tires_form_ajax_search');

function tires_form_process_redirect()
{
    if (isset($_GET['mark']) && isset($_GET['year']) && isset($_GET['line'])) {
        global $wpdb;
        $original = $wpdb->get_var($wpdb->prepare(
            "SELECT llanta_dimension FROM vehiculos WHERE marca = %s AND anio = %d AND linea = %s",
            $_GET['mark'],
            $_GET['year'],
            $_GET['line']
        ));
        $url = add_query_arg('dimension', $original ? urlencode($original) : '', home_url('/resultados'));
        wp_redirect($url);
        exit;
    }
}
add_action('template_redirect', 'tires_form_process_redirect');

function tires_result_shortcode()
{
    if (isset($_GET['dimension']) && !empty($_GET['dimension'])) {
        $dimension = sanitize_text_field($_GET['dimension']);

        echo '<h3 class="titleFind">DIMENSIÓN RECOMENDADA: <span class="tyrecommended">' . esc_html($dimension) . '</span></h3>';
        echo '<section class="content-wrapper">';

        // Generar y mostrar productos para la dimensión especificada
        $shortcode = sprintf('[products limit="12" attribute="pa_dimension" terms="%s"]', $dimension);

        ob_start();
        echo do_shortcode($shortcode);
        $products_output = ob_get_clean();

        if (!stripos($products_output, 'product')) {
            // Divide las dimensiones en partes: ancho, perfil, diámetro
            $dim_parts = explode('/', $dimension);
            $ancho = isset($dim_parts[0]) ? intval($dim_parts[0]) : 0;
            $perfil_diametro = isset($dim_parts[1]) ? explode('R', $dim_parts[1]) : [];
            $perfil = isset($perfil_diametro[0]) ? intval($perfil_diametro[0]) : 0;
            $diametro = isset($perfil_diametro[1]) ? intval($perfil_diametro[1]) : 0;

            // Calcula el diámetro externo original
            $original_diametro_externo = ($ancho * ($perfil / 100) * 2) + ($diametro * 25.4);

            $similar_terms = [];

            // Generar variaciones de las dimensiones originales en un rango razonable y válido
            $ancho_variations = range($ancho - 20, $ancho + 20, 10);
            $perfil_variations = range($perfil - 10, $perfil + 10, 5);
            $diametro_variations = [$diametro]; // Mantener el mismo diámetro

            foreach ($ancho_variations as $new_ancho) {
                foreach ($perfil_variations as $new_perfil) {
                    foreach ($diametro_variations as $new_diametro) {
                        if ($new_ancho > 0 && $new_perfil > 0 && $new_diametro > 0) {
                            $new_diametro_externo = ($new_ancho * ($new_perfil / 100) * 2) + ($new_diametro * 25.4);
                            $diferencia_diametro = (($new_diametro_externo - $original_diametro_externo) / $original_diametro_externo) * 100;

                            if ($diferencia_diametro <= 1.5 && $diferencia_diametro >= -2) {
                                $similar_terms[] = $new_ancho . '/' . $new_perfil . 'R' . $new_diametro;
                            }
                        }
                    }
                }
            }

            // Mostrar alternativas
            if (!empty($similar_terms)) {
                $similar_shortcode = sprintf('[products limit="12" attribute="pa_dimension" terms="%s"]', implode(',', $similar_terms));

                ob_start();
                echo do_shortcode($similar_shortcode);
                $products_output = ob_get_clean();

            } 
            
            if (!stripos($products_output, 'product') || empty($similar_terms)) {
                echo '<section class="NotFindProduct">';
                echo '<h3 class="">No se han encontrado productos de las dimensiones recomendadas.</h3>';
                echo '<p class="back-to-shop"><a style="margin-left:-20px;" class="button wc-backward" href="' . esc_url(get_permalink(wc_get_page_id('shop'))) . '">Volver a la tienda</a></p>';
                echo '</section>'; // Cierra .NotFindProduct
            }
        }

        echo '</section>'; // Cierra .content-wrapper
    } else {
        // No mostrar nada si el parámetro de dimensión no está establecido
        return;
    }
}
add_shortcode('tires_result', 'tires_result_shortcode');
