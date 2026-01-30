<?php
/**
 * Plugin Name: Mobapp Transporte Multiopciones 
 * Description: Método de envío para WooCommerce que permite al cliente elegir su transporte preferido
 * Version: 1.0.0
 * Author: Mobapp
 * Text Domain: mobapp-transporte-multiopciones
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// Inicializar el plugin después de que WooCommerce cargue
add_action('woocommerce_shipping_init', 'transporte_pago_destino_init');

function transporte_pago_destino_init() {
    
    class WC_Shipping_Transporte_Pago_Destino extends WC_Shipping_Method {
        
        public function __construct($instance_id = 0) {
            $this->id = 'transporte_pago_destino';
            $this->instance_id = absint($instance_id);
            $this->method_title = __('Transporte Pago en Destino', 'transporte-pago-destino');
            $this->method_description = __('Permite al cliente elegir el transporte de su preferencia', 'transporte-pago-destino');
            $this->supports = array(
                'shipping-zones',
                'instance-settings',
                'instance-settings-modal',
            );
            
            $this->init();
        }
        
        public function init() {
            $this->init_form_fields();
            $this->init_settings();
            
            $this->title = $this->get_option('title', __('Transporte Pago en Destino', 'transporte-pago-destino'));
            $this->enabled = $this->get_option('enabled', 'yes');
            $this->transportes = $this->get_option('transportes', array());
            
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }
        
        public function init_form_fields() {
            $this->instance_form_fields = array(
                'enabled' => array(
                    'title' => __('Activar/Desactivar', 'transporte-pago-destino'),
                    'type' => 'checkbox',
                    'label' => __('Activar este método de envío', 'transporte-pago-destino'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Título del método', 'transporte-pago-destino'),
                    'type' => 'text',
                    'description' => __('Título que verá el cliente en el checkout', 'transporte-pago-destino'),
                    'default' => __('Transporte Pago en Destino', 'transporte-pago-destino'),
                    'desc_tip' => true,
                ),
                'transportes' => array(
                    'title' => __('Lista de Transportes', 'transporte-pago-destino'),
                    'type' => 'transportes_table',
                    'description' => __('Agregue los transportes disponibles', 'transporte-pago-destino'),
                ),
            );
        }
        
        public function generate_transportes_table_html($key, $data) {
            $field_key = $this->get_field_key($key);
            $transportes = $this->get_option($key, array());
            
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label><?php echo esc_html($data['title']); ?></label>
                </th>
                <td class="forminp">
                    <div id="transportes-container">
                        <table class="widefat" id="transportes-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Nombre del Transporte', 'transporte-pago-destino'); ?></th>
                                    <th><?php _e('Activo', 'transporte-pago-destino'); ?></th>
                                    <th><?php _e('Acciones', 'transporte-pago-destino'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if (!empty($transportes)) {
                                    foreach ($transportes as $index => $transporte) {
                                        ?>
                                        <tr>
                                            <td>
                                                <input type="text" 
                                                       name="<?php echo esc_attr($field_key); ?>[<?php echo $index; ?>][nombre]" 
                                                       value="<?php echo esc_attr($transporte['nombre']); ?>" 
                                                       class="regular-text" />
                                            </td>
                                            <td>
                                                <input type="checkbox" 
                                                       name="<?php echo esc_attr($field_key); ?>[<?php echo $index; ?>][activo]" 
                                                       value="1" 
                                                       <?php checked(isset($transporte['activo']) && $transporte['activo'], true); ?> />
                                            </td>
                                            <td>
                                                <button type="button" class="button remove-transporte"><?php _e('Eliminar', 'transporte-pago-destino'); ?></button>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                        <p>
                            <button type="button" class="button button-primary" id="add-transporte">
                                <?php _e('+ Agregar Transporte', 'transporte-pago-destino'); ?>
                            </button>
                        </p>
                    </div>
                    <script type="text/javascript">
                        jQuery(document).ready(function($) {
                            var index = <?php echo !empty($transportes) ? max(array_keys($transportes)) + 1 : 0; ?>;
                            var fieldKey = '<?php echo esc_js($field_key); ?>';
                            
                            $('#add-transporte').on('click', function() {
                                var row = '<tr>' +
                                    '<td><input type="text" name="' + fieldKey + '[' + index + '][nombre]" value="" class="regular-text" /></td>' +
                                    '<td><input type="checkbox" name="' + fieldKey + '[' + index + '][activo]" value="1" checked /></td>' +
                                    '<td><button type="button" class="button remove-transporte"><?php _e('Eliminar', 'transporte-pago-destino'); ?></button></td>' +
                                    '</tr>';
                                $('#transportes-table tbody').append(row);
                                index++;
                            });
                            
                            $(document).on('click', '.remove-transporte', function() {
                                $(this).closest('tr').remove();
                            });
                        });
                    </script>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
        
        public function validate_transportes_table_field($key, $value) {
            $transportes = array();
            
            if (is_array($value)) {
                foreach ($value as $transporte) {
                    if (!empty($transporte['nombre'])) {
                        $transportes[] = array(
                            'nombre' => sanitize_text_field($transporte['nombre']),
                            'activo' => isset($transporte['activo']) ? true : false,
                        );
                    }
                }
            }
            
            return $transportes;
        }
        
        public function calculate_shipping($package = array()) {
            $this->add_rate(array(
                'id' => $this->get_rate_id(),
                'label' => $this->title,
                'cost' => 0,
                'package' => $package,
            ));
        }
        
        public function get_transportes_activos() {
            $transportes = $this->get_option('transportes', array());
            $activos = array();
            
            foreach ($transportes as $transporte) {
                if (isset($transporte['activo']) && $transporte['activo'] && !empty($transporte['nombre'])) {
                    $activos[] = $transporte['nombre'];
                }
            }
            
            return $activos;
        }
    }
}

// Registrar el método de envío
add_filter('woocommerce_shipping_methods', 'add_transporte_pago_destino_method');

function add_transporte_pago_destino_method($methods) {
    $methods['transporte_pago_destino'] = 'WC_Shipping_Transporte_Pago_Destino';
    return $methods;
}

// Agregar campos en el checkout
add_action('woocommerce_after_shipping_rate', 'mostrar_selector_transporte', 10, 2);

function mostrar_selector_transporte($method, $index) {
    if ($method->method_id !== 'transporte_pago_destino') {
        return;
    }
    
    // Obtener transportes activos
    $shipping_methods = WC()->shipping()->get_shipping_methods();
    $transportes = array();
    
    if (isset($shipping_methods['transporte_pago_destino'])) {
        $zones = WC_Shipping_Zones::get_zones();
        foreach ($zones as $zone) {
            foreach ($zone['shipping_methods'] as $shipping_method) {
                if ($shipping_method->id === 'transporte_pago_destino') {
                    $transportes = $shipping_method->get_transportes_activos();
                    break 2;
                }
            }
        }
        
        // También revisar la zona "resto del mundo"
        if (empty($transportes)) {
            $default_zone = new WC_Shipping_Zone(0);
            foreach ($default_zone->get_shipping_methods() as $shipping_method) {
                if ($shipping_method->id === 'transporte_pago_destino') {
                    $transportes = $shipping_method->get_transportes_activos();
                    break;
                }
            }
        }
    }
    
    $chosen_transporte = WC()->session->get('transporte_elegido', '');
    $transporte_otro = WC()->session->get('transporte_otro', '');
    
    ?>
    <div class="transporte-selector" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
        <p style="margin-bottom: 10px;"><strong><?php _e('Seleccione su transporte preferido:', 'transporte-pago-destino'); ?></strong></p>
        
        <select name="transporte_elegido" id="transporte_elegido" style="width: 100%; margin-bottom: 10px;">
            <option value=""><?php _e('-- Seleccionar transporte --', 'transporte-pago-destino'); ?></option>
            <?php foreach ($transportes as $transporte) : ?>
                <option value="<?php echo esc_attr($transporte); ?>" <?php selected($chosen_transporte, $transporte); ?>>
                    <?php echo esc_html($transporte); ?>
                </option>
            <?php endforeach; ?>
            <option value="otro" <?php selected($chosen_transporte, 'otro'); ?>><?php _e('Otro (especificar)', 'transporte-pago-destino'); ?></option>
        </select>
        
        <div id="transporte_otro_container" style="display: <?php echo ($chosen_transporte === 'otro') ? 'block' : 'none'; ?>;">
            <input type="text" 
                   name="transporte_otro" 
                   id="transporte_otro" 
                   value="<?php echo esc_attr($transporte_otro); ?>"
                   placeholder="<?php _e('Escriba el nombre del transporte', 'transporte-pago-destino'); ?>" 
                   style="width: 100%;" />
        </div>
    </div>
    
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#transporte_elegido').on('change', function() {
                if ($(this).val() === 'otro') {
                    $('#transporte_otro_container').slideDown();
                } else {
                    $('#transporte_otro_container').slideUp();
                }
                
                // Guardar en sesión via AJAX
                $.ajax({
                    url: wc_checkout_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'guardar_transporte_elegido',
                        transporte: $(this).val(),
                        transporte_otro: $('#transporte_otro').val(),
                        security: '<?php echo wp_create_nonce('guardar_transporte_nonce'); ?>'
                    }
                });
            });
            
            $('#transporte_otro').on('change', function() {
                $.ajax({
                    url: wc_checkout_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'guardar_transporte_elegido',
                        transporte: $('#transporte_elegido').val(),
                        transporte_otro: $(this).val(),
                        security: '<?php echo wp_create_nonce('guardar_transporte_nonce'); ?>'
                    }
                });
            });
        });
    </script>
    <?php
}

// AJAX para guardar transporte en sesión
add_action('wp_ajax_guardar_transporte_elegido', 'guardar_transporte_elegido');
add_action('wp_ajax_nopriv_guardar_transporte_elegido', 'guardar_transporte_elegido');

function guardar_transporte_elegido() {
    check_ajax_referer('guardar_transporte_nonce', 'security');
    
    WC()->session->set('transporte_elegido', sanitize_text_field($_POST['transporte']));
    WC()->session->set('transporte_otro', sanitize_text_field($_POST['transporte_otro']));
    
    wp_die();
}

// Validar que se seleccionó un transporte
add_action('woocommerce_checkout_process', 'validar_transporte_elegido');

function validar_transporte_elegido() {
    $chosen_methods = WC()->session->get('chosen_shipping_methods');
    
    if (!empty($chosen_methods)) {
        foreach ($chosen_methods as $method) {
            if (strpos($method, 'transporte_pago_destino') !== false) {
                $transporte = WC()->session->get('transporte_elegido', '');
                
                if (empty($transporte)) {
                    wc_add_notice(__('Por favor seleccione un transporte para el envío.', 'transporte-pago-destino'), 'error');
                }
                
                if ($transporte === 'otro') {
                    $transporte_otro = WC()->session->get('transporte_otro', '');
                    if (empty($transporte_otro)) {
                        wc_add_notice(__('Por favor especifique el nombre del transporte.', 'transporte-pago-destino'), 'error');
                    }
                }
            }
        }
    }
}

// Guardar transporte en el pedido
add_action('woocommerce_checkout_create_order', 'guardar_transporte_en_pedido', 10, 2);

function guardar_transporte_en_pedido($order, $data) {
    $transporte = WC()->session->get('transporte_elegido', '');
    $transporte_otro = WC()->session->get('transporte_otro', '');
    
    if (!empty($transporte)) {
        if ($transporte === 'otro' && !empty($transporte_otro)) {
            $transporte_final = $transporte_otro . ' (personalizado)';
        } else {
            $transporte_final = $transporte;
        }
        
        $order->update_meta_data('_transporte_elegido', $transporte_final);
    }
    
    // Limpiar sesión
    WC()->session->set('transporte_elegido', '');
    WC()->session->set('transporte_otro', '');
}

// Mostrar transporte en el admin de pedidos
add_action('woocommerce_admin_order_data_after_shipping_address', 'mostrar_transporte_admin_pedido');

function mostrar_transporte_admin_pedido($order) {
    $transporte = $order->get_meta('_transporte_elegido');
    
    if (!empty($transporte)) {
        echo '<div class="address" style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #0073aa;">';
        echo '<p><strong>' . __('Transporte Elegido:', 'transporte-pago-destino') . '</strong><br>';
        echo '<span style="font-size: 14px; color: #0073aa;">' . esc_html($transporte) . '</span></p>';
        echo '</div>';
    }
}

// Mostrar en la lista de pedidos (columna personalizada)
add_filter('manage_edit-shop_order_columns', 'agregar_columna_transporte');
add_filter('manage_woocommerce_page_wc-orders_columns', 'agregar_columna_transporte');

function agregar_columna_transporte($columns) {
    $new_columns = array();
    
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'shipping_address') {
            $new_columns['transporte_elegido'] = __('Transporte', 'transporte-pago-destino');
        }
    }
    
    return $new_columns;
}

add_action('manage_shop_order_posts_custom_column', 'mostrar_columna_transporte', 10, 2);
add_action('manage_woocommerce_page_wc-orders_custom_column', 'mostrar_columna_transporte_hpos', 10, 2);

function mostrar_columna_transporte($column, $post_id) {
    if ($column === 'transporte_elegido') {
        $order = wc_get_order($post_id);
        $transporte = $order->get_meta('_transporte_elegido');
        echo !empty($transporte) ? esc_html($transporte) : '—';
    }
}

function mostrar_columna_transporte_hpos($column, $order) {
    if ($column === 'transporte_elegido') {
        $transporte = $order->get_meta('_transporte_elegido');
        echo !empty($transporte) ? esc_html($transporte) : '—';
    }
}

// Agregar transporte a los emails
add_action('woocommerce_email_after_order_table', 'agregar_transporte_email', 10, 4);

function agregar_transporte_email($order, $sent_to_admin, $plain_text, $email) {
    $transporte = $order->get_meta('_transporte_elegido');
    
    if (empty($transporte)) {
        return;
    }
    
    if ($plain_text) {
        echo "\n" . __('Transporte Elegido:', 'transporte-pago-destino') . ' ' . $transporte . "\n";
    } else {
        ?>
        <div style="margin: 20px 0; padding: 15px; background-color: #f8f8f8; border-left: 4px solid #0073aa;">
            <h3 style="margin: 0 0 10px 0; color: #0073aa;"><?php _e('Información de Transporte', 'transporte-pago-destino'); ?></h3>
            <p style="margin: 0;">
                <strong><?php _e('Transporte Elegido:', 'transporte-pago-destino'); ?></strong> 
                <?php echo esc_html($transporte); ?>
            </p>
        </div>
        <?php
    }
}

// Mostrar en detalles del pedido (cliente)
add_action('woocommerce_order_details_after_order_table', 'mostrar_transporte_detalles_pedido');

function mostrar_transporte_detalles_pedido($order) {
    $transporte = $order->get_meta('_transporte_elegido');
    
    if (!empty($transporte)) {
        ?>
        <section class="woocommerce-transporte-details">
            <h2><?php _e('Información de Transporte', 'transporte-pago-destino'); ?></h2>
            <table class="woocommerce-table woocommerce-table--transporte-details">
                <tbody>
                    <tr>
                        <th><?php _e('Transporte Elegido:', 'transporte-pago-destino'); ?></th>
                        <td><?php echo esc_html($transporte); ?></td>
                    </tr>
                </tbody>
            </table>
        </section>
        <?php
    }
}
