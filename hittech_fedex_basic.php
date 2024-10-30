<?php
/**
 * Plugin Name: Fedex Shipping With Live rates and shipping labels. 
 * Plugin URI: https://hittechmarket.com/product/fedex-shipping-with-label-printing/
 * Description: Realtime Shipping Rates, shipping labels.
 * Version: 1.0.0
 * Author: Hittechmarket
 * Author URI: https://hittechmarket.com/
 * Developer: HITtechmarket
 * Developer URI: https://hittechmarket.com/
 * Text Domain: hittech_fedex
 * Domain Path: /i18n/languages/
 *
 * WC requires at least: 2.6
 * WC tested up to: 5.8
 *
 *
 * @package WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define WC_PLUGIN_FILE.
if ( ! defined( 'hittech_fedex_PLUGIN_FILE' ) ) {
	define( 'hittech_fedex_PLUGIN_FILE', __FILE__ );
}

function hittech_hit_woo_fedex_plugin_activation( $plugin ) {
    if( $plugin == plugin_basename( __FILE__ ) ) {
        $setting_value = version_compare(WC()->version, '2.1', '>=') ? "wc-settings" : "woocommerce_settings";
    	// Don't forget to exit() because wp_redirect doesn't exit automatically
    	exit( wp_redirect( admin_url( 'admin.php?page=' . $setting_value  . '&tab=shipping&section=hittech_fedex' ) ) );
    }
}
add_action( 'activated_plugin', 'hittech_hit_woo_fedex_plugin_activation' );


// Include the main WooCommerce class.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	if( !class_exists('hittech_fedex_parent') ){
		Class hittech_fedex_parent
		{
			public function __construct() {
				add_action( 'woocommerce_shipping_init', array($this,'hittech_fedex_init') );
				add_action( 'init', array($this,'hittech_fedex_order_status_update') );
				add_filter( 'woocommerce_shipping_methods', array($this,'hittech_fedex_method') );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'hittech_fedex_plugin_action_links' ) );
				add_action( 'add_meta_boxes', array($this, 'hittech_create_fedex_shipping_meta_box'), 10, 1);
				add_action( 'save_post', array($this, 'hittech_hitshippo_create_fedex_shipping'), 10, 1 );
				
				add_action( 'admin_menu', array($this, 'hittech_fedex_menu_page' ));
				

				$general_settings = get_option('hittech_fedex_main_settings');
				$general_settings = empty($general_settings) ? array() : $general_settings;

				if(isset($general_settings['hittech_fedex_v_enable']) && $general_settings['hittech_fedex_v_enable'] == 'yes' ){
					add_action( 'woocommerce_product_options_shipping', array($this,'hittech_choose_vendor_address' ));
					add_action( 'woocommerce_process_product_meta', array($this,'hittech_save_product_meta' ));

					// Edit User Hooks
					add_action( 'edit_user_profile', array($this,'hittech_define_fedex_credentails') );
					add_action( 'edit_user_profile_update', array($this, 'hittech_save_user_fields' ));

				}
			}

			function hittech_fedex_menu_page() {
				
				add_submenu_page( 'options-general.php', 'Fedex Shipping Config', 'Fedex Shipping Config', 'manage_options', 'hittech-fedex-configuration', array($this, 'hittech_my_admin_page_contents') ); 

			}
			function hittech_my_admin_page_contents(){
				include_once('controllors/views/hittech_fedex_settings_view.php');
			}

			public function hittech_choose_vendor_address(){
				global $woocommerce, $post;
				$hit_multi_vendor = get_option('hit_multi_vendor');
				$hit_multi_vendor = empty($hit_multi_vendor) ? array() : $hit_multi_vendor;
				$selected_addr = get_post_meta( $post->ID, 'fedex_address', true);

				$main_settings = get_option('hittech_fedex_main_settings');
				$main_settings = empty($main_settings) ? array() : $main_settings;
				if(!isset($main_settings['hittech_fedex_v_roles']) || empty($main_settings['hittech_fedex_v_roles'])){
					return;
				}
				$v_users = get_users( [ 'role__in' => $main_settings['hittech_fedex_v_roles'] ] );

				?>
				<div class="options_group">
				<p class="form-field fedex_shipment">
					<label for="fedex_shipment"><?php _e( 'Fedex Account', 'woocommerce' ); ?></label>
					<select id="fedex_shipment" style="width:240px;" name="fedex_shipment" class="wc-enhanced-select" data-placeholder="<?php _e( 'Search for a product&hellip;', 'woocommerce' ); ?>">
						<option value="default" >Default Account</option>
						<?php
							if ( $v_users ) {
								foreach ( $v_users as $value ) {
									echo '<option value="' .  esc_html($value->data->ID)  . '" '.($selected_addr == $value->data->ID ? 'selected="true"' : '').'>' . esc_html($value->data->display_name) . '</option>';
								}
							}
						?>
					</select>
					</p>
				</div>
				<?php
			}

			public function hittech_save_product_meta( $post_id ){
				if(isset( $_POST['fedex_shipment'])){
					$fedex_shipment = sanitize_text_field($_POST['fedex_shipment']);
					if( !empty( $fedex_shipment ) )
					update_post_meta( $post_id, 'fedex_address', (string) esc_html( $fedex_shipment ) );
				}

			}

			public function hittech_define_fedex_credentails( $user ){

				$main_settings = get_option('hittech_fedex_main_settings');
				$main_settings = empty($main_settings) ? array() : $main_settings;
				$allow = false;

				if(!isset($main_settings['hittech_fedex_v_roles'])){
					return;
				}else{
					foreach ($user->roles as $value) {
						if(in_array($value, $main_settings['hittech_fedex_v_roles'])){
							$allow = true;
						}
					}
				}

				if(!$allow){
					return;
				}

				$general_settings = get_post_meta($user->ID,'hittech_fedex_vendor_settings',true);
				$general_settings = empty($general_settings) ? array() : $general_settings;
				$countires =  array(
									'AF' => 'Afghanistan',
									'AL' => 'Albania',
									'DZ' => 'Algeria',
									'AS' => 'American Samoa',
									'AD' => 'Andorra',
									'AO' => 'Angola',
									'AI' => 'Anguilla',
									'AG' => 'Antigua and Barbuda',
									'AR' => 'Argentina',
									'AM' => 'Armenia',
									'AW' => 'Aruba',
									'AU' => 'Australia',
									'AT' => 'Austria',
									'AZ' => 'Azerbaijan',
									'BS' => 'Bahamas',
									'BH' => 'Bahrain',
									'BD' => 'Bangladesh',
									'BB' => 'Barbados',
									'BY' => 'Belarus',
									'BE' => 'Belgium',
									'BZ' => 'Belize',
									'BJ' => 'Benin',
									'BM' => 'Bermuda',
									'BT' => 'Bhutan',
									'BO' => 'Bolivia',
									'BA' => 'Bosnia and Herzegovina',
									'BW' => 'Botswana',
									'BR' => 'Brazil',
									'VG' => 'British Virgin Islands',
									'BN' => 'Brunei',
									'BG' => 'Bulgaria',
									'BF' => 'Burkina Faso',
									'BI' => 'Burundi',
									'KH' => 'Cambodia',
									'CM' => 'Cameroon',
									'CA' => 'Canada',
									'CV' => 'Cape Verde',
									'KY' => 'Cayman Islands',
									'CF' => 'Central African Republic',
									'TD' => 'Chad',
									'CL' => 'Chile',
									'CN' => 'China',
									'CO' => 'Colombia',
									'KM' => 'Comoros',
									'CK' => 'Cook Islands',
									'CR' => 'Costa Rica',
									'HR' => 'Croatia',
									'CU' => 'Cuba',
									'CY' => 'Cyprus',
									'CZ' => 'Czech Republic',
									'DK' => 'Denmark',
									'DJ' => 'Djibouti',
									'DM' => 'Dominica',
									'DO' => 'Dominican Republic',
									'TL' => 'East Timor',
									'EC' => 'Ecuador',
									'EG' => 'Egypt',
									'SV' => 'El Salvador',
									'GQ' => 'Equatorial Guinea',
									'ER' => 'Eritrea',
									'EE' => 'Estonia',
									'ET' => 'Ethiopia',
									'FK' => 'Falkland Islands',
									'FO' => 'Faroe Islands',
									'FJ' => 'Fiji',
									'FI' => 'Finland',
									'FR' => 'France',
									'GF' => 'French Guiana',
									'PF' => 'French Polynesia',
									'GA' => 'Gabon',
									'GM' => 'Gambia',
									'GE' => 'Georgia',
									'DE' => 'Germany',
									'GH' => 'Ghana',
									'GI' => 'Gibraltar',
									'GR' => 'Greece',
									'GL' => 'Greenland',
									'GD' => 'Grenada',
									'GP' => 'Guadeloupe',
									'GU' => 'Guam',
									'GT' => 'Guatemala',
									'GG' => 'Guernsey',
									'GN' => 'Guinea',
									'GW' => 'Guinea-Bissau',
									'GY' => 'Guyana',
									'HT' => 'Haiti',
									'HN' => 'Honduras',
									'HK' => 'Hong Kong',
									'HU' => 'Hungary',
									'IS' => 'Iceland',
									'IN' => 'India',
									'ID' => 'Indonesia',
									'IR' => 'Iran',
									'IQ' => 'Iraq',
									'IE' => 'Ireland',
									'IL' => 'Israel',
									'IT' => 'Italy',
									'CI' => 'Ivory Coast',
									'JM' => 'Jamaica',
									'JP' => 'Japan',
									'JE' => 'Jersey',
									'JO' => 'Jordan',
									'KZ' => 'Kazakhstan',
									'KE' => 'Kenya',
									'KI' => 'Kiribati',
									'KW' => 'Kuwait',
									'KG' => 'Kyrgyzstan',
									'LA' => 'Laos',
									'LV' => 'Latvia',
									'LB' => 'Lebanon',
									'LS' => 'Lesotho',
									'LR' => 'Liberia',
									'LY' => 'Libya',
									'LI' => 'Liechtenstein',
									'LT' => 'Lithuania',
									'LU' => 'Luxembourg',
									'MO' => 'Macao',
									'MK' => 'Macedonia',
									'MG' => 'Madagascar',
									'MW' => 'Malawi',
									'MY' => 'Malaysia',
									'MV' => 'Maldives',
									'ML' => 'Mali',
									'MT' => 'Malta',
									'MH' => 'Marshall Islands',
									'MQ' => 'Martinique',
									'MR' => 'Mauritania',
									'MU' => 'Mauritius',
									'YT' => 'Mayotte',
									'MX' => 'Mexico',
									'FM' => 'Micronesia',
									'MD' => 'Moldova',
									'MC' => 'Monaco',
									'MN' => 'Mongolia',
									'ME' => 'Montenegro',
									'MS' => 'Montserrat',
									'MA' => 'Morocco',
									'MZ' => 'Mozambique',
									'MM' => 'Myanmar',
									'NA' => 'Namibia',
									'NR' => 'Nauru',
									'NP' => 'Nepal',
									'NL' => 'Netherlands',
									'NC' => 'New Caledonia',
									'NZ' => 'New Zealand',
									'NI' => 'Nicaragua',
									'NE' => 'Niger',
									'NG' => 'Nigeria',
									'NU' => 'Niue',
									'KP' => 'North Korea',
									'MP' => 'Northern Mariana Islands',
									'NO' => 'Norway',
									'OM' => 'Oman',
									'PK' => 'Pakistan',
									'PW' => 'Palau',
									'PA' => 'Panama',
									'PG' => 'Papua New Guinea',
									'PY' => 'Paraguay',
									'PE' => 'Peru',
									'PH' => 'Philippines',
									'PL' => 'Poland',
									'PT' => 'Portugal',
									'PR' => 'Puerto Rico',
									'QA' => 'Qatar',
									'CG' => 'Republic of the Congo',
									'RE' => 'Reunion',
									'RO' => 'Romania',
									'RU' => 'Russia',
									'RW' => 'Rwanda',
									'SH' => 'Saint Helena',
									'KN' => 'Saint Kitts and Nevis',
									'LC' => 'Saint Lucia',
									'VC' => 'Saint Vincent and the Grenadines',
									'WS' => 'Samoa',
									'SM' => 'San Marino',
									'ST' => 'Sao Tome and Principe',
									'SA' => 'Saudi Arabia',
									'SN' => 'Senegal',
									'RS' => 'Serbia',
									'SC' => 'Seychelles',
									'SL' => 'Sierra Leone',
									'SG' => 'Singapore',
									'SK' => 'Slovakia',
									'SI' => 'Slovenia',
									'SB' => 'Solomon Islands',
									'SO' => 'Somalia',
									'ZA' => 'South Africa',
									'KR' => 'South Korea',
									'SS' => 'South Sudan',
									'ES' => 'Spain',
									'LK' => 'Sri Lanka',
									'SD' => 'Sudan',
									'SR' => 'Suriname',
									'SZ' => 'Swaziland',
									'SE' => 'Sweden',
									'CH' => 'Switzerland',
									'SY' => 'Syria',
									'TW' => 'Taiwan',
									'TJ' => 'Tajikistan',
									'TZ' => 'Tanzania',
									'TH' => 'Thailand',
									'TG' => 'Togo',
									'TO' => 'Tonga',
									'TT' => 'Trinidad and Tobago',
									'TN' => 'Tunisia',
									'TR' => 'Turkey',
									'TC' => 'Turks and Caicos Islands',
									'TV' => 'Tuvalu',
									'VI' => 'U.S. Virgin Islands',
									'UG' => 'Uganda',
									'UA' => 'Ukraine',
									'AE' => 'United Arab Emirates',
									'GB' => 'United Kingdom',
									'US' => 'United States',
									'UY' => 'Uruguay',
									'UZ' => 'Uzbekistan',
									'VU' => 'Vanuatu',
									'VE' => 'Venezuela',
									'VN' => 'Vietnam',
									'YE' => 'Yemen',
									'ZM' => 'Zambia',
									'ZW' => 'Zimbabwe',
								);
				 $_fedex_carriers = array(
							'FIRST_OVERNIGHT'                    => 'FedEx First Overnight',
							'PRIORITY_OVERNIGHT'                 => 'FedEx Priority Overnight',
							'STANDARD_OVERNIGHT'                 => 'FedEx Standard Overnight',
							'FEDEX_2_DAY_AM'                     => 'FedEx 2Day A.M',
							'FEDEX_2_DAY'                        => 'FedEx 2Day',
							'SAME_DAY'                        => 'FedEx Same Day',
							'SAME_DAY_CITY'                        => 'FedEx Same Day City',
							'SAME_DAY_METRO_AFTERNOON'                        => 'FedEx Same Day Metro Afternoon',
							'SAME_DAY_METRO_MORNING'                        => 'FedEx Same Day Metro Morning',
							'SAME_DAY_METRO_RUSH'                        => 'FedEx Same Day Metro Rush',
							'FEDEX_EXPRESS_SAVER'                => 'FedEx Express Saver',
							'GROUND_HOME_DELIVERY'               => 'FedEx Ground Home Delivery',
							'FEDEX_GROUND'                       => 'FedEx Ground',
							'INTERNATIONAL_ECONOMY'              => 'FedEx International Economy',
							'INTERNATIONAL_ECONOMY_DISTRIBUTION'              => 'FedEx International Economy Distribution',
							'INTERNATIONAL_FIRST'                => 'FedEx International First',
							'INTERNATIONAL_GROUND'                => 'FedEx International Ground',
							'INTERNATIONAL_PRIORITY'             => 'FedEx International Priority',
							'INTERNATIONAL_PRIORITY_DISTRIBUTION'             => 'FedEx International Priority Distribution',
							'EUROPE_FIRST_INTERNATIONAL_PRIORITY' => 'FedEx Europe First International Priority',
							'INTERNATIONAL_PRIORITY_EXPRESS' => 'FedEx International Priority Express',
							'FEDEX_INTERNATIONAL_PRIORITY_PLUS' => 'FedEx First International Priority Plus',
							'INTERNATIONAL_DISTRIBUTION_FREIGHT' => 'FedEx International Distribution Fright',
							'FEDEX_1_DAY_FREIGHT'                => 'FedEx 1 Day Freight',
							'FEDEX_2_DAY_FREIGHT'                => 'FedEx 2 Day Freight',
							'FEDEX_3_DAY_FREIGHT'                => 'FedEx 3 Day Freight',
							'INTERNATIONAL_ECONOMY_FREIGHT'      => 'FedEx Economy Freight',
							'INTERNATIONAL_PRIORITY_FREIGHT'     => 'FedEx Priority Freight',
							'SMART_POST'                         => 'FedEx Smart Post',
							'FEDEX_FIRST_FREIGHT'                => 'FedEx First Freight',
							'FEDEX_FREIGHT_ECONOMY'              => 'FedEx Freight Economy',
							'FEDEX_FREIGHT_PRIORITY'             => 'FedEx Freight Priority',
							'FEDEX_CARGO_AIRPORT_TO_AIRPORT'             => 'FedEx CARGO Airport to Airport',
							'FEDEX_CARGO_FREIGHT_FORWARDING'             => 'FedEx CARGO Freight FOrwarding',
							'FEDEX_CARGO_INTERNATIONAL_EXPRESS_FREIGHT'             => 'FedEx CARGO International Express Fright',
							'FEDEX_CARGO_INTERNATIONAL_PREMIUM'             => 'FedEx CARGO International Premium',
							'FEDEX_CARGO_MAIL'             => 'FedEx CARGO Mail',
							'FEDEX_CARGO_REGISTERED_MAIL'             => 'FedEx CARGO Registered Mail',
							'FEDEX_CARGO_SURFACE_MAIL'             => 'FedEx CARGO Surface Mail',
							'FEDEX_CUSTOM_CRITICAL_AIR_EXPEDITE_EXCLUSIVE_USE'             => 'FedEx Custom Critical Air Expedite Exclusive Use',
							'FEDEX_CUSTOM_CRITICAL_AIR_EXPEDITE_NETWORK'             => 'FedEx Custom Critical Air Expedite Network',
							'FEDEX_CUSTOM_CRITICAL_CHARTER_AIR'             => 'FedEx Custom Critical Charter Air',
							'FEDEX_CUSTOM_CRITICAL_POINT_TO_POINT'             => 'FedEx Custom Critical Point to Point',
							'FEDEX_CUSTOM_CRITICAL_SURFACE_EXPEDITE'             => 'FedEx Custom Critical Surface Expedite',
							'FEDEX_CUSTOM_CRITICAL_SURFACE_EXPEDITE_EXCLUSIVE_USE'             => 'FedEx Custom Critical Surface Expedite Exclusive Use',
							'FEDEX_CUSTOM_CRITICAL_TEMP_ASSURE_AIR'             => 'FedEx Custom Critical Temp Assure Air',
							'FEDEX_CUSTOM_CRITICAL_TEMP_ASSURE_VALIDATED_AIR'             => 'FedEx Custom Critical Temp Assure Validated Air',
							'FEDEX_CUSTOM_CRITICAL_WHITE_GLOVE_SERVICES'             => 'FedEx Custom Critical White Glove Services',
							'TRANSBORDER_DISTRIBUTION_CONSOLIDATION'             => 'Fedex Transborder Distribution Consolidation',
							'FEDEX_DISTANCE_DEFERRED'            => 'FedEx Distance Deferred',
							'FEDEX_NEXT_DAY_EARLY_MORNING'       => 'FedEx Next Day Early Morning',
							'FEDEX_NEXT_DAY_MID_MORNING'         => 'FedEx Next Day Mid Morning',
							'FEDEX_NEXT_DAY_AFTERNOON'           => 'FedEx Next Day Afternoon',
							'FEDEX_NEXT_DAY_END_OF_DAY'          => 'FedEx Next Day End of Day',
							'FEDEX_NEXT_DAY_FREIGHT'             => 'FedEx Next Day Freight',
							);

			$fedex_core = array();
			$fedex_core['AD'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['AE'] = array('region' => 'AP', 'currency' =>'AED', 'weight' => 'KG_CM');
			$fedex_core['AF'] = array('region' => 'AP', 'currency' =>'AFN', 'weight' => 'KG_CM');
			$fedex_core['AG'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['AI'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['AL'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['AM'] = array('region' => 'AP', 'currency' =>'AMD', 'weight' => 'KG_CM');
			$fedex_core['AN'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'KG_CM');
			$fedex_core['AO'] = array('region' => 'AP', 'currency' =>'AOA', 'weight' => 'KG_CM');
			$fedex_core['AR'] = array('region' => 'AM', 'currency' =>'ARS', 'weight' => 'KG_CM');
			$fedex_core['AS'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['AT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['AU'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
			$fedex_core['AW'] = array('region' => 'AM', 'currency' =>'AWG', 'weight' => 'LB_IN');
			$fedex_core['AZ'] = array('region' => 'AM', 'currency' =>'AZN', 'weight' => 'KG_CM');
			$fedex_core['AZ'] = array('region' => 'AM', 'currency' =>'AZN', 'weight' => 'KG_CM');
			$fedex_core['GB'] = array('region' => 'EU', 'currency' =>'GBP', 'weight' => 'KG_CM');
			$fedex_core['BA'] = array('region' => 'AP', 'currency' =>'BAM', 'weight' => 'KG_CM');
			$fedex_core['BB'] = array('region' => 'AM', 'currency' =>'BBD', 'weight' => 'LB_IN');
			$fedex_core['BD'] = array('region' => 'AP', 'currency' =>'BDT', 'weight' => 'KG_CM');
			$fedex_core['BE'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['BF'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
			$fedex_core['BG'] = array('region' => 'EU', 'currency' =>'BGN', 'weight' => 'KG_CM');
			$fedex_core['BH'] = array('region' => 'AP', 'currency' =>'BHD', 'weight' => 'KG_CM');
			$fedex_core['BI'] = array('region' => 'AP', 'currency' =>'BIF', 'weight' => 'KG_CM');
			$fedex_core['BJ'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
			$fedex_core['BM'] = array('region' => 'AM', 'currency' =>'BMD', 'weight' => 'LB_IN');
			$fedex_core['BN'] = array('region' => 'AP', 'currency' =>'BND', 'weight' => 'KG_CM');
			$fedex_core['BO'] = array('region' => 'AM', 'currency' =>'BOB', 'weight' => 'KG_CM');
			$fedex_core['BR'] = array('region' => 'AM', 'currency' =>'BRL', 'weight' => 'KG_CM');
			$fedex_core['BS'] = array('region' => 'AM', 'currency' =>'BSD', 'weight' => 'LB_IN');
			$fedex_core['BT'] = array('region' => 'AP', 'currency' =>'BTN', 'weight' => 'KG_CM');
			$fedex_core['BW'] = array('region' => 'AP', 'currency' =>'BWP', 'weight' => 'KG_CM');
			$fedex_core['BY'] = array('region' => 'AP', 'currency' =>'BYR', 'weight' => 'KG_CM');
			$fedex_core['BZ'] = array('region' => 'AM', 'currency' =>'BZD', 'weight' => 'KG_CM');
			$fedex_core['CA'] = array('region' => 'AM', 'currency' =>'CAD', 'weight' => 'LB_IN');
			$fedex_core['CF'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
			$fedex_core['CG'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
			$fedex_core['CH'] = array('region' => 'EU', 'currency' =>'CHF', 'weight' => 'KG_CM');
			$fedex_core['CI'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
			$fedex_core['CK'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
			$fedex_core['CL'] = array('region' => 'AM', 'currency' =>'CLP', 'weight' => 'KG_CM');
			$fedex_core['CM'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
			$fedex_core['CN'] = array('region' => 'AP', 'currency' =>'CNY', 'weight' => 'KG_CM');
			$fedex_core['CO'] = array('region' => 'AM', 'currency' =>'COP', 'weight' => 'KG_CM');
			$fedex_core['CR'] = array('region' => 'AM', 'currency' =>'CRC', 'weight' => 'KG_CM');
			$fedex_core['CU'] = array('region' => 'AM', 'currency' =>'CUC', 'weight' => 'KG_CM');
			$fedex_core['CV'] = array('region' => 'AP', 'currency' =>'CVE', 'weight' => 'KG_CM');
			$fedex_core['CY'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['CZ'] = array('region' => 'EU', 'currency' =>'CZK', 'weight' => 'KG_CM');
			$fedex_core['DE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['DJ'] = array('region' => 'EU', 'currency' =>'DJF', 'weight' => 'KG_CM');
			$fedex_core['DK'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
			$fedex_core['DM'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['DO'] = array('region' => 'AP', 'currency' =>'DOP', 'weight' => 'LB_IN');
			$fedex_core['DZ'] = array('region' => 'AM', 'currency' =>'DZD', 'weight' => 'KG_CM');
			$fedex_core['EC'] = array('region' => 'EU', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['EE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['EG'] = array('region' => 'AP', 'currency' =>'EGP', 'weight' => 'KG_CM');
			$fedex_core['ER'] = array('region' => 'EU', 'currency' =>'ERN', 'weight' => 'KG_CM');
			$fedex_core['ES'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['ET'] = array('region' => 'AU', 'currency' =>'ETB', 'weight' => 'KG_CM');
			$fedex_core['FI'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['FJ'] = array('region' => 'AP', 'currency' =>'FJD', 'weight' => 'KG_CM');
			$fedex_core['FK'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
			$fedex_core['FM'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['FO'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
			$fedex_core['FR'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['GA'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
			$fedex_core['GB'] = array('region' => 'EU', 'currency' =>'GBP', 'weight' => 'KG_CM');
			$fedex_core['GD'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['GE'] = array('region' => 'AM', 'currency' =>'GEL', 'weight' => 'KG_CM');
			$fedex_core['GF'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['GG'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
			$fedex_core['GH'] = array('region' => 'AP', 'currency' =>'GBS', 'weight' => 'KG_CM');
			$fedex_core['GI'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
			$fedex_core['GL'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
			$fedex_core['GM'] = array('region' => 'AP', 'currency' =>'GMD', 'weight' => 'KG_CM');
			$fedex_core['GN'] = array('region' => 'AP', 'currency' =>'GNF', 'weight' => 'KG_CM');
			$fedex_core['GP'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['GQ'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
			$fedex_core['GR'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['GT'] = array('region' => 'AM', 'currency' =>'GTQ', 'weight' => 'KG_CM');
			$fedex_core['GU'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['GW'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
			$fedex_core['GY'] = array('region' => 'AP', 'currency' =>'GYD', 'weight' => 'LB_IN');
			$fedex_core['HK'] = array('region' => 'AM', 'currency' =>'HKD', 'weight' => 'KG_CM');
			$fedex_core['HN'] = array('region' => 'AM', 'currency' =>'HNL', 'weight' => 'KG_CM');
			$fedex_core['HR'] = array('region' => 'AP', 'currency' =>'HRK', 'weight' => 'KG_CM');
			$fedex_core['HT'] = array('region' => 'AM', 'currency' =>'HTG', 'weight' => 'LB_IN');
			$fedex_core['HU'] = array('region' => 'EU', 'currency' =>'HUF', 'weight' => 'KG_CM');
			$fedex_core['IC'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['ID'] = array('region' => 'AP', 'currency' =>'IDR', 'weight' => 'KG_CM');
			$fedex_core['IE'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['IL'] = array('region' => 'AP', 'currency' =>'ILS', 'weight' => 'KG_CM');
			$fedex_core['IN'] = array('region' => 'AP', 'currency' =>'INR', 'weight' => 'KG_CM');
			$fedex_core['IQ'] = array('region' => 'AP', 'currency' =>'IQD', 'weight' => 'KG_CM');
			$fedex_core['IR'] = array('region' => 'AP', 'currency' =>'IRR', 'weight' => 'KG_CM');
			$fedex_core['IS'] = array('region' => 'EU', 'currency' =>'ISK', 'weight' => 'KG_CM');
			$fedex_core['IT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['JE'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
			$fedex_core['JM'] = array('region' => 'AM', 'currency' =>'JMD', 'weight' => 'KG_CM');
			$fedex_core['JO'] = array('region' => 'AP', 'currency' =>'JOD', 'weight' => 'KG_CM');
			$fedex_core['JP'] = array('region' => 'AP', 'currency' =>'JPY', 'weight' => 'KG_CM');
			$fedex_core['KE'] = array('region' => 'AP', 'currency' =>'KES', 'weight' => 'KG_CM');
			$fedex_core['KG'] = array('region' => 'AP', 'currency' =>'KGS', 'weight' => 'KG_CM');
			$fedex_core['KH'] = array('region' => 'AP', 'currency' =>'KHR', 'weight' => 'KG_CM');
			$fedex_core['KI'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
			$fedex_core['KM'] = array('region' => 'AP', 'currency' =>'KMF', 'weight' => 'KG_CM');
			$fedex_core['KN'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['KP'] = array('region' => 'AP', 'currency' =>'KPW', 'weight' => 'LB_IN');
			$fedex_core['KR'] = array('region' => 'AP', 'currency' =>'KRW', 'weight' => 'KG_CM');
			$fedex_core['KV'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['KW'] = array('region' => 'AP', 'currency' =>'KWD', 'weight' => 'KG_CM');
			$fedex_core['KY'] = array('region' => 'AM', 'currency' =>'KYD', 'weight' => 'KG_CM');
			$fedex_core['KZ'] = array('region' => 'AP', 'currency' =>'KZF', 'weight' => 'LB_IN');
			$fedex_core['LA'] = array('region' => 'AP', 'currency' =>'LAK', 'weight' => 'KG_CM');
			$fedex_core['LB'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['LC'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'KG_CM');
			$fedex_core['LI'] = array('region' => 'AM', 'currency' =>'CHF', 'weight' => 'LB_IN');
			$fedex_core['LK'] = array('region' => 'AP', 'currency' =>'LKR', 'weight' => 'KG_CM');
			$fedex_core['LR'] = array('region' => 'AP', 'currency' =>'LRD', 'weight' => 'KG_CM');
			$fedex_core['LS'] = array('region' => 'AP', 'currency' =>'LSL', 'weight' => 'KG_CM');
			$fedex_core['LT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['LU'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['LV'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['LY'] = array('region' => 'AP', 'currency' =>'LYD', 'weight' => 'KG_CM');
			$fedex_core['MA'] = array('region' => 'AP', 'currency' =>'MAD', 'weight' => 'KG_CM');
			$fedex_core['MC'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['MD'] = array('region' => 'AP', 'currency' =>'MDL', 'weight' => 'KG_CM');
			$fedex_core['ME'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['MG'] = array('region' => 'AP', 'currency' =>'MGA', 'weight' => 'KG_CM');
			$fedex_core['MH'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['MK'] = array('region' => 'AP', 'currency' =>'MKD', 'weight' => 'KG_CM');
			$fedex_core['ML'] = array('region' => 'AP', 'currency' =>'COF', 'weight' => 'KG_CM');
			$fedex_core['MM'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['MN'] = array('region' => 'AP', 'currency' =>'MNT', 'weight' => 'KG_CM');
			$fedex_core['MO'] = array('region' => 'AP', 'currency' =>'MOP', 'weight' => 'KG_CM');
			$fedex_core['MP'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['MQ'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['MR'] = array('region' => 'AP', 'currency' =>'MRO', 'weight' => 'KG_CM');
			$fedex_core['MS'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['MT'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['MU'] = array('region' => 'AP', 'currency' =>'MUR', 'weight' => 'KG_CM');
			$fedex_core['MV'] = array('region' => 'AP', 'currency' =>'MVR', 'weight' => 'KG_CM');
			$fedex_core['MW'] = array('region' => 'AP', 'currency' =>'MWK', 'weight' => 'KG_CM');
			$fedex_core['MX'] = array('region' => 'AM', 'currency' =>'MXN', 'weight' => 'KG_CM');
			$fedex_core['MY'] = array('region' => 'AP', 'currency' =>'MYR', 'weight' => 'KG_CM');
			$fedex_core['MZ'] = array('region' => 'AP', 'currency' =>'MZN', 'weight' => 'KG_CM');
			$fedex_core['NA'] = array('region' => 'AP', 'currency' =>'NAD', 'weight' => 'KG_CM');
			$fedex_core['NC'] = array('region' => 'AP', 'currency' =>'XPF', 'weight' => 'KG_CM');
			$fedex_core['NE'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
			$fedex_core['NG'] = array('region' => 'AP', 'currency' =>'NGN', 'weight' => 'KG_CM');
			$fedex_core['NI'] = array('region' => 'AM', 'currency' =>'NIO', 'weight' => 'KG_CM');
			$fedex_core['NL'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['NO'] = array('region' => 'EU', 'currency' =>'NOK', 'weight' => 'KG_CM');
			$fedex_core['NP'] = array('region' => 'AP', 'currency' =>'NPR', 'weight' => 'KG_CM');
			$fedex_core['NR'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
			$fedex_core['NU'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
			$fedex_core['NZ'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
			$fedex_core['OM'] = array('region' => 'AP', 'currency' =>'OMR', 'weight' => 'KG_CM');
			$fedex_core['PA'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['PE'] = array('region' => 'AM', 'currency' =>'PEN', 'weight' => 'KG_CM');
			$fedex_core['PF'] = array('region' => 'AP', 'currency' =>'XPF', 'weight' => 'KG_CM');
			$fedex_core['PG'] = array('region' => 'AP', 'currency' =>'PGK', 'weight' => 'KG_CM');
			$fedex_core['PH'] = array('region' => 'AP', 'currency' =>'PHP', 'weight' => 'KG_CM');
			$fedex_core['PK'] = array('region' => 'AP', 'currency' =>'PKR', 'weight' => 'KG_CM');
			$fedex_core['PL'] = array('region' => 'EU', 'currency' =>'PLN', 'weight' => 'KG_CM');
			$fedex_core['PR'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['PT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['PW'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['PY'] = array('region' => 'AM', 'currency' =>'PYG', 'weight' => 'KG_CM');
			$fedex_core['QA'] = array('region' => 'AP', 'currency' =>'QAR', 'weight' => 'KG_CM');
			$fedex_core['RE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['RO'] = array('region' => 'EU', 'currency' =>'RON', 'weight' => 'KG_CM');
			$fedex_core['RS'] = array('region' => 'AP', 'currency' =>'RSD', 'weight' => 'KG_CM');
			$fedex_core['RU'] = array('region' => 'AP', 'currency' =>'RUB', 'weight' => 'KG_CM');
			$fedex_core['RW'] = array('region' => 'AP', 'currency' =>'RWF', 'weight' => 'KG_CM');
			$fedex_core['SA'] = array('region' => 'AP', 'currency' =>'SAR', 'weight' => 'KG_CM');
			$fedex_core['SB'] = array('region' => 'AP', 'currency' =>'SBD', 'weight' => 'KG_CM');
			$fedex_core['SC'] = array('region' => 'AP', 'currency' =>'SCR', 'weight' => 'KG_CM');
			$fedex_core['SD'] = array('region' => 'AP', 'currency' =>'SDG', 'weight' => 'KG_CM');
			$fedex_core['SE'] = array('region' => 'EU', 'currency' =>'SEK', 'weight' => 'KG_CM');
			$fedex_core['SG'] = array('region' => 'AP', 'currency' =>'SGD', 'weight' => 'KG_CM');
			$fedex_core['SH'] = array('region' => 'AP', 'currency' =>'SHP', 'weight' => 'KG_CM');
			$fedex_core['SI'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['SK'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['SL'] = array('region' => 'AP', 'currency' =>'SLL', 'weight' => 'KG_CM');
			$fedex_core['SM'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['SN'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
			$fedex_core['SO'] = array('region' => 'AM', 'currency' =>'SOS', 'weight' => 'KG_CM');
			$fedex_core['SR'] = array('region' => 'AM', 'currency' =>'SRD', 'weight' => 'KG_CM');
			$fedex_core['SS'] = array('region' => 'AP', 'currency' =>'SSP', 'weight' => 'KG_CM');
			$fedex_core['ST'] = array('region' => 'AP', 'currency' =>'STD', 'weight' => 'KG_CM');
			$fedex_core['SV'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['SY'] = array('region' => 'AP', 'currency' =>'SYP', 'weight' => 'KG_CM');
			$fedex_core['SZ'] = array('region' => 'AP', 'currency' =>'SZL', 'weight' => 'KG_CM');
			$fedex_core['TC'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['TD'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
			$fedex_core['TG'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
			$fedex_core['TH'] = array('region' => 'AP', 'currency' =>'THB', 'weight' => 'KG_CM');
			$fedex_core['TJ'] = array('region' => 'AP', 'currency' =>'TJS', 'weight' => 'KG_CM');
			$fedex_core['TL'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['TN'] = array('region' => 'AP', 'currency' =>'TND', 'weight' => 'KG_CM');
			$fedex_core['TO'] = array('region' => 'AP', 'currency' =>'TOP', 'weight' => 'KG_CM');
			$fedex_core['TR'] = array('region' => 'AP', 'currency' =>'TRY', 'weight' => 'KG_CM');
			$fedex_core['TT'] = array('region' => 'AM', 'currency' =>'TTD', 'weight' => 'LB_IN');
			$fedex_core['TV'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
			$fedex_core['TW'] = array('region' => 'AP', 'currency' =>'TWD', 'weight' => 'KG_CM');
			$fedex_core['TZ'] = array('region' => 'AP', 'currency' =>'TZS', 'weight' => 'KG_CM');
			$fedex_core['UA'] = array('region' => 'AP', 'currency' =>'UAH', 'weight' => 'KG_CM');
			$fedex_core['UG'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['US'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['UY'] = array('region' => 'AM', 'currency' =>'UYU', 'weight' => 'KG_CM');
			$fedex_core['UZ'] = array('region' => 'AP', 'currency' =>'UZS', 'weight' => 'KG_CM');
			$fedex_core['VC'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['VE'] = array('region' => 'AM', 'currency' =>'VEF', 'weight' => 'KG_CM');
			$fedex_core['VG'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['VI'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['VN'] = array('region' => 'AP', 'currency' =>'VND', 'weight' => 'KG_CM');
			$fedex_core['VU'] = array('region' => 'AP', 'currency' =>'VUV', 'weight' => 'KG_CM');
			$fedex_core['WS'] = array('region' => 'AP', 'currency' =>'WST', 'weight' => 'KG_CM');
			$fedex_core['XB'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
			$fedex_core['XC'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
			$fedex_core['XE'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'LB_IN');
			$fedex_core['XM'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
			$fedex_core['XN'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['XS'] = array('region' => 'AP', 'currency' =>'SIS', 'weight' => 'KG_CM');
			$fedex_core['XY'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'LB_IN');
			$fedex_core['YE'] = array('region' => 'AP', 'currency' =>'YER', 'weight' => 'KG_CM');
			$fedex_core['YT'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['ZA'] = array('region' => 'AP', 'currency' =>'ZAR', 'weight' => 'KG_CM');
			$fedex_core['ZM'] = array('region' => 'AP', 'currency' =>'ZMW', 'weight' => 'KG_CM');
			$fedex_core['ZW'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');

				 echo '<hr><h3 class="heading">Fedex - <a href="https://hitshipo.com/" target="_blank">HITShipo</a></h3>';
				    ?>

				    <table class="form-table">
						<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Fedex Integration Team will give this details to you.','hittech_fedex') ?>"></span>	<?php _e('Web Service Key','hittech_fedex') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hittech_fedex') ?> </p>
						</td>
						<td>
							<input type="text" name="hittech_fedex_site_id" value="<?php echo (isset($general_settings['hittech_fedex_site_id'])) ? esc_html($general_settings['hittech_fedex_site_id']) : ''; ?>">
						</td>

					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Fedex Integration Team will give this details to you.','hittech_fedex') ?>"></span>	<?php _e('Web Service Password','hittech_fedex') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hittech_fedex') ?> </p>
						</td>
						<td>
							<input type="text" name="hittech_fedex_site_pwd" value="<?php echo (isset($general_settings['hittech_fedex_site_pwd'])) ? esc_html($general_settings['hittech_fedex_site_pwd']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Fedex Integration Team will give this details to you.','hittech_fedex') ?>"></span>	<?php _e('Fedex Account Number','hittech_fedex') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hittech_fedex') ?> </p>
						</td>
						<td>

							<input type="text" name="hittech_fedex_acc_no" value="<?php echo (isset($general_settings['hittech_fedex_acc_no'])) ? esc_html($general_settings['hittech_fedex_acc_no']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Fedex Integration Team will give this details to you.','hittech_fedex') ?>"></span>	<?php _e('Fedex Meter Number','hittech_fedex') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hittech_fedex') ?> </p>
						</td>
						<td>

							<input type="text" name="hittech_fedex_access_key" value="<?php echo (isset($general_settings['hittech_fedex_access_key'])) ? esc_html($general_settings['hittech_fedex_access_key']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipping Person Name','hittech_fedex') ?>"></span>	<?php _e('Shipper Name','hittech_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hittech_fedex_shipper_name" value="<?php echo (isset($general_settings['hittech_fedex_shipper_name'])) ? esc_html($general_settings['hittech_fedex_shipper_name']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipper Company Name.','hittech_fedex') ?>"></span>	<?php _e('Company Name','hittech_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hittech_fedex_company" value="<?php echo (isset($general_settings['hittech_fedex_company'])) ? esc_html($general_settings['hittech_fedex_company']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipper Mobile / Contact Number.','hittech_fedex') ?>"></span>	<?php _e('Contact Number','hittech_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hittech_fedex_mob_num" value="<?php echo (isset($general_settings['hittech_fedex_mob_num'])) ? esc_html($general_settings['hittech_fedex_mob_num']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Email Address of the Shipper.','hittech_fedex') ?>"></span>	<?php _e('Email Address','hittech_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hittech_fedex_email" value="<?php echo (isset($general_settings['hittech_fedex_email'])) ? esc_html($general_settings['hittech_fedex_email']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Address Line 1 of the Shipper from Address.','hittech_fedex') ?>"></span>	<?php _e('Address Line 1','hittech_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hittech_fedex_address1" value="<?php echo (isset($general_settings['hittech_fedex_address1'])) ? esc_html($general_settings['hittech_fedex_address1']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Address Line 2 of the Shipper from Address.','hittech_fedex') ?>"></span>	<?php _e('Address Line 2','hittech_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hittech_fedex_address2" value="<?php echo (isset($general_settings['hittech_fedex_address2'])) ? esc_html($general_settings['hittech_fedex_address2']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%;padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('City of the Shipper from address.','hittech_fedex') ?>"></span>	<?php _e('City','hittech_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hittech_fedex_city" value="<?php echo (isset($general_settings['hittech_fedex_city'])) ? esc_html($general_settings['hittech_fedex_city']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('State of the Shipper from address.','hittech_fedex') ?>"></span>	<?php _e('State (Two Digit String)','hittech_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hittech_fedex_state" value="<?php echo (isset($general_settings['hittech_fedex_state'])) ? esc_html($general_settings['hittech_fedex_state']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Postal/Zip Code.','hittech_fedex') ?>"></span>	<?php _e('Postal/Zip Code','hittech_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hittech_fedex_zip" value="<?php echo (isset($general_settings['hittech_fedex_zip'])) ? esc_html($general_settings['hittech_fedex_zip']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Country of the Shipper from Address.','hittech_fedex') ?>"></span>	<?php _e('Country','hittech_fedex') ?></h4>
						</td>
						<td>
							<select name="hittech_fedex_country" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($countires as $key => $value)
								{

									if(isset($general_settings['hittech_fedex_country']) && ($general_settings['hittech_fedex_country'] == $key))
									{
										echo "<option value=".esc_html($key)." selected='true'>".esc_html($value)." [". esc_html($fedex_core[$key]['currency']) ."]</option>";
									}
									else
									{
										echo "<option value=".esc_html($key).">".esc_html($value)." [". esc_html($fedex_core[$key]['currency']) ."]</option>";
									}
								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Conversion Rate from Site Currency to FedEx Currency.','hittech_fedex') ?>"></span>	<?php _e('Conversion Rate from Site Currency to Fedex Currency ( Ignore if auto conversion is Enabled )','hittech_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hittech_fedex_con_rate" value="<?php echo (isset($general_settings['hittech_fedex_con_rate'])) ? esc_html($general_settings['hittech_fedex_con_rate']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td>
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Choose currency that return by fedex, currency will be converted from this currency to woocommerce currency while showing rates on frontoffice.','hittech_fedex') ?>"></span><?php _e('Fedex Currency Code','hittech_fedex') ?></h4>
						</td>
						<td>
							<select name="hittech_fedex_currency" style="width:153px;">
								<?php foreach($fedex_core as  $currency)
								{
									if(isset($general_settings['hittech_fedex_currency']) && ($general_settings['hittech_fedex_currency'] == $currency['currency']))
									{
										echo "<option value=".esc_html($currency['currency'])." selected='true'>".esc_html($currency['currency'])."</option>";
									}
									else
									{
										echo "<option value=".esc_html($currency['currency']).">".esc_html($currency['currency'])."</option>";
									}
								}

								if (!isset($general_settings['hittech_fedex_currency']) || ($general_settings['hittech_fedex_currency'] != "NMP")) {
										echo "<option value=NMP>NMP</option>";
								}elseif (isset($general_settings['hittech_fedex_currency']) && ($general_settings['hittech_fedex_currency'] == "NMP")) {
										echo "<option value=NMP selected='true'>NMP</option>";
								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Default Domestic Shipping Service.','hittech_fedex') ?>"></span>	<?php _e('Default Domestic Service','hittech_fedex') ?></h4>
							<p><?php _e('This will be used while shipping label generation.','hittech_fedex') ?></p>
						</td>
						<td>
							<select name="hittech_fedex_def_dom" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($_fedex_carriers as $key => $value)
								{
									if(isset($general_settings['hittech_fedex_def_dom']) && ($general_settings['hittech_fedex_def_dom'] == $key))
									{
										echo "<option value=".esc_html($key)." selected='true'>[".esc_html($key)."] ".esc_html($value)."</option>";
									}
									else
									{
										echo "<option value=".esc_html($key).">[".esc_html($key)."] ".esc_html($value)."</option>";
									}
								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Default International Shipping Service.','hittech_fedex') ?>"></span>	<?php _e('Default International Service','hittech_fedex') ?></h4>
							<p><?php _e('This will be used while shipping label generation.','hittech_fedex') ?></p>
						</td>
						<td>
							<select name="hittech_fedex_def_inter" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($_fedex_carriers as $key => $value)
								{
									if(isset($general_settings['hittech_fedex_def_inter']) && ($general_settings['hittech_fedex_def_inter'] == $key))
									{
										echo "<option value=".esc_html($key)." selected='true'>[".esc_html($key)."] ".esc_html($value)."</option>";
									}
									else
									{
										echo "<option value=".esc_html($key).">[".esc_html($key)."] ".esc_html($value)."</option>";
									}
								} ?>
							</select>
						</td>
					</tr>
				    </table>
				    <hr>
				    <?php
			}

			public function hittech_save_user_fields($user_id){
				if(isset($_POST['hittech_fedex_country'])){
					$general_settings['hittech_fedex_site_id'] = sanitize_text_field(isset($_POST['hittech_fedex_site_id']) ? $_POST['hittech_fedex_site_id'] : '');
					$general_settings['hittech_fedex_site_pwd'] = sanitize_text_field(isset($_POST['hittech_fedex_site_pwd']) ? $_POST['hittech_fedex_site_pwd'] : '');
					$general_settings['hittech_fedex_acc_no'] = sanitize_text_field(isset($_POST['hittech_fedex_acc_no']) ? $_POST['hittech_fedex_acc_no'] : '');
					$general_settings['hittech_fedex_access_key'] = sanitize_text_field(isset($_POST['hittech_fedex_access_key']) ? $_POST['hittech_fedex_access_key'] : '');
					$general_settings['hittech_fedex_shipper_name'] = sanitize_text_field(isset($_POST['hittech_fedex_shipper_name']) ? $_POST['hittech_fedex_shipper_name'] : '');
					$general_settings['hittech_fedex_company'] = sanitize_text_field(isset($_POST['hittech_fedex_company']) ? $_POST['hittech_fedex_company'] : '');
					$general_settings['hittech_fedex_mob_num'] = sanitize_text_field(isset($_POST['hittech_fedex_mob_num']) ? $_POST['hittech_fedex_mob_num'] : '');
					$general_settings['hittech_fedex_email'] = sanitize_text_field(isset($_POST['hittech_fedex_email']) ? $_POST['hittech_fedex_email'] : '');
					$general_settings['hittech_fedex_address1'] = sanitize_text_field(isset($_POST['hittech_fedex_address1']) ? $_POST['hittech_fedex_address1'] : '');
					$general_settings['hittech_fedex_address2'] = sanitize_text_field(isset($_POST['hittech_fedex_address2']) ? $_POST['hittech_fedex_address2'] : '');
					$general_settings['hittech_fedex_city'] = sanitize_text_field(isset($_POST['hittech_fedex_city']) ? $_POST['hittech_fedex_city'] : '');
					$general_settings['hittech_fedex_state'] = sanitize_text_field(isset($_POST['hittech_fedex_state']) ? $_POST['hittech_fedex_state'] : '');
					$general_settings['hittech_fedex_zip'] = sanitize_text_field(isset($_POST['hittech_fedex_zip']) ? $_POST['hittech_fedex_zip'] : '');
					$general_settings['hittech_fedex_country'] = sanitize_text_field(isset($_POST['hittech_fedex_country']) ? $_POST['hittech_fedex_country'] : '');
					// $general_settings['hittech_fedex_gstin'] = sanitize_text_field(isset($_POST['hittech_fedex_gstin']) ? $_POST['hittech_fedex_gstin'] : '');
					$general_settings['hittech_fedex_con_rate'] = sanitize_text_field(isset($_POST['hittech_fedex_con_rate']) ? $_POST['hittech_fedex_con_rate'] : '');
					$general_settings['hittech_fedex_currency'] = sanitize_text_field(isset($_POST['hittech_fedex_currency']) ? $_POST['hittech_fedex_currency'] : '');
					$general_settings['hittech_fedex_def_dom'] = sanitize_text_field(isset($_POST['hittech_fedex_def_dom']) ? $_POST['hittech_fedex_def_dom'] : '');

					$general_settings['hittech_fedex_def_inter'] = sanitize_text_field(isset($_POST['hittech_fedex_def_inter']) ? $_POST['hittech_fedex_def_inter'] : '');

					update_post_meta($user_id,'hittech_fedex_vendor_settings',$general_settings);
				}

			}

			public function hittech_fedex_init()
			{
				include_once("controllors/hittech_fedex_init.php");
			}
			public function hittech_fedex_method( $methods )
			{
				$methods['hittech_fedex'] = 'hittech_fedex';
				return $methods;
			}
			public function hittech_fedex_plugin_action_links($links)
			{
				$setting_value = version_compare(WC()->version, '2.1', '>=') ? "wc-settings" : "woocommerce_settings";
				$plugin_links = array(
					'<a href="' . admin_url( 'admin.php?page=' . $setting_value  . '&tab=shipping&section=hittech_fedex' ) . '" style="color:green;">' . __( 'Configure', 'hittech_fedex' ) . '</a>',
					'<a href="https://app.hitshipo.com/support" target="_blank" >' . __('Support', 'hittech_fedex') . '</a>'
					);
				return array_merge( $plugin_links, $links );
			}

			public function hittech_create_fedex_shipping_meta_box() {
				   add_meta_box( 'hittech_hitshippo_create_fedex_shipping', __('Fedex Shipping Label','hittech_fedex'), array($this, 'hittech_create_fedex_shipping_label_genetation'), 'shop_order', 'side', 'core' );
				  
		    }

		    public function hittech_fedex_order_status_update(){
		    	global $woocommerce;
				
			}

		
		    public function hittech_create_fedex_shipping_label_genetation($post){
		    	// print_r('expression');
		    	// die();
		        if($post->post_type !='shop_order' ){
		    		return;
		    	}
		    	$order = wc_get_order( $post->ID );
		    	$ship_met = $order->get_shipping_methods();

		    	$order_id = $order->get_id();
		        $_fedex_carriers = array(
							'FIRST_OVERNIGHT'                    => 'FedEx First Overnight',
							'PRIORITY_OVERNIGHT'                 => 'FedEx Priority Overnight',
							'STANDARD_OVERNIGHT'                 => 'FedEx Standard Overnight',
							'FEDEX_2_DAY_AM'                     => 'FedEx 2Day A.M',
							'FEDEX_2_DAY'                        => 'FedEx 2Day',
							'SAME_DAY'                        => 'FedEx Same Day',
							'SAME_DAY_CITY'                        => 'FedEx Same Day City',
							'SAME_DAY_METRO_AFTERNOON'                        => 'FedEx Same Day Metro Afternoon',
							'SAME_DAY_METRO_MORNING'                        => 'FedEx Same Day Metro Morning',
							'SAME_DAY_METRO_RUSH'                        => 'FedEx Same Day Metro Rush',
							'FEDEX_EXPRESS_SAVER'                => 'FedEx Express Saver',
							'GROUND_HOME_DELIVERY'               => 'FedEx Ground Home Delivery',
							'FEDEX_GROUND'                       => 'FedEx Ground',
							'INTERNATIONAL_ECONOMY'              => 'FedEx International Economy',
							'INTERNATIONAL_ECONOMY_DISTRIBUTION'              => 'FedEx International Economy Distribution',
							'INTERNATIONAL_FIRST'                => 'FedEx International First',
							'INTERNATIONAL_GROUND'                => 'FedEx International Ground',
							'INTERNATIONAL_PRIORITY'             => 'FedEx International Priority',
							'INTERNATIONAL_PRIORITY_DISTRIBUTION'             => 'FedEx International Priority Distribution',
							'EUROPE_FIRST_INTERNATIONAL_PRIORITY' => 'FedEx Europe First International Priority',
							'INTERNATIONAL_PRIORITY_EXPRESS' => 'FedEx International Priority Express',
							'FEDEX_INTERNATIONAL_PRIORITY_PLUS' => 'FedEx First International Priority Plus',
							'INTERNATIONAL_DISTRIBUTION_FREIGHT' => 'FedEx International Distribution Fright',
							'FEDEX_1_DAY_FREIGHT'                => 'FedEx 1 Day Freight',
							'FEDEX_2_DAY_FREIGHT'                => 'FedEx 2 Day Freight',
							'FEDEX_3_DAY_FREIGHT'                => 'FedEx 3 Day Freight',
							'INTERNATIONAL_ECONOMY_FREIGHT'      => 'FedEx Economy Freight',
							'INTERNATIONAL_PRIORITY_FREIGHT'     => 'FedEx Priority Freight',
							'SMART_POST'                         => 'FedEx Smart Post',
							'FEDEX_FIRST_FREIGHT'                => 'FedEx First Freight',
							'FEDEX_FREIGHT_ECONOMY'              => 'FedEx Freight Economy',
							'FEDEX_FREIGHT_PRIORITY'             => 'FedEx Freight Priority',
							'FEDEX_CARGO_AIRPORT_TO_AIRPORT'             => 'FedEx CARGO Airport to Airport',
							'FEDEX_CARGO_FREIGHT_FORWARDING'             => 'FedEx CARGO Freight FOrwarding',
							'FEDEX_CARGO_INTERNATIONAL_EXPRESS_FREIGHT'             => 'FedEx CARGO International Express Fright',
							'FEDEX_CARGO_INTERNATIONAL_PREMIUM'             => 'FedEx CARGO International Premium',
							'FEDEX_CARGO_MAIL'             => 'FedEx CARGO Mail',
							'FEDEX_CARGO_REGISTERED_MAIL'             => 'FedEx CARGO Registered Mail',
							'FEDEX_CARGO_SURFACE_MAIL'             => 'FedEx CARGO Surface Mail',
							'FEDEX_CUSTOM_CRITICAL_AIR_EXPEDITE_EXCLUSIVE_USE'             => 'FedEx Custom Critical Air Expedite Exclusive Use',
							'FEDEX_CUSTOM_CRITICAL_AIR_EXPEDITE_NETWORK'             => 'FedEx Custom Critical Air Expedite Network',
							'FEDEX_CUSTOM_CRITICAL_CHARTER_AIR'             => 'FedEx Custom Critical Charter Air',
							'FEDEX_CUSTOM_CRITICAL_POINT_TO_POINT'             => 'FedEx Custom Critical Point to Point',
							'FEDEX_CUSTOM_CRITICAL_SURFACE_EXPEDITE'             => 'FedEx Custom Critical Surface Expedite',
							'FEDEX_CUSTOM_CRITICAL_SURFACE_EXPEDITE_EXCLUSIVE_USE'             => 'FedEx Custom Critical Surface Expedite Exclusive Use',
							'FEDEX_CUSTOM_CRITICAL_TEMP_ASSURE_AIR'             => 'FedEx Custom Critical Temp Assure Air',
							'FEDEX_CUSTOM_CRITICAL_TEMP_ASSURE_VALIDATED_AIR'             => 'FedEx Custom Critical Temp Assure Validated Air',
							'FEDEX_CUSTOM_CRITICAL_WHITE_GLOVE_SERVICES'             => 'FedEx Custom Critical White Glove Services',
							'TRANSBORDER_DISTRIBUTION_CONSOLIDATION'             => 'Fedex Transborder Distribution Consolidation',
							'FEDEX_DISTANCE_DEFERRED'            => 'FedEx Distance Deferred',
							'FEDEX_NEXT_DAY_EARLY_MORNING'       => 'FedEx Next Day Early Morning',
							'FEDEX_NEXT_DAY_MID_MORNING'         => 'FedEx Next Day Mid Morning',
							'FEDEX_NEXT_DAY_AFTERNOON'           => 'FedEx Next Day Afternoon',
							'FEDEX_NEXT_DAY_END_OF_DAY'          => 'FedEx Next Day End of Day',
							'FEDEX_NEXT_DAY_FREIGHT'             => 'FedEx Next Day Freight',
							);

		        $general_settings = get_option('hittech_fedex_main_settings',array());

		        $items = $order->get_items();

    		    $custom_settings = array();
		    	$custom_settings['default'] =  array();
		    	$vendor_settings = array();

		    	$pack_products = array();

				foreach ( $items as $item ) {
					$product_data = $item->get_data();
				    $product = array();
				    $product['product_name'] = $product_data['name'];
				    $product['product_quantity'] = $product_data['quantity'];
				    $product['product_id'] = $product_data['product_id'];

				    $pack_products[] = $product;

				}

				if(isset($general_settings['hittech_fedex_v_enable']) && $general_settings['hittech_fedex_v_enable'] == 'yes' && isset($general_settings['hittech_fedex_v_labels']) && $general_settings['hittech_fedex_v_labels'] == 'yes'){
					// Multi Vendor Enabled
					foreach ($pack_products as $key => $value) {

						$product_id = $value['product_id'];
						$fedex_account = get_post_meta($product_id,'fedex_address', true);
						if(empty($fedex_account) || $fedex_account == 'default'){
							$fedex_account = 'default';
							$vendor_settings[$fedex_account] = $custom_settings['default'];
							$vendor_settings[$fedex_account]['products'][] = $value;
						}

						if($fedex_account != 'default'){
							$user_account = get_post_meta($fedex_account,'hittech_fedex_vendor_settings', true);
							$user_account = empty($user_account) ? array() : $user_account;
							if(!empty($user_account)){
								if(!isset($vendor_settings[$fedex_account])){

									$vendor_settings[$fedex_account] = $custom_settings['default'];
									unset($value['product_id']);
									$vendor_settings[$fedex_account]['products'][] = $value;
								}
							}else{
								$fedex_account = 'default';
								$vendor_settings[$fedex_account] = $custom_settings['default'];
								$vendor_settings[$fedex_account]['products'][] = $value;
							}
						}

					}

				}

				if(empty($vendor_settings)){
					$custom_settings['default']['products'] = $pack_products;
				}else{
					$custom_settings = $vendor_settings;
				}
				// echo '<pre>';print_r($custom_settings);die();
		       	$shipment_data = json_decode(get_option('hittech_fedex_values_'.$order_id), true); // using "true" to convert stdobject to array
		       	$notice = get_option('hittech_fedex_status_'.$order_id, null);
		       	// echo '<pre>';
		       	// print_r($shipment_data);
		       	// echo '<h3>Notice</h3>';
		       	// print_r($notice);
		       	// die();

		       	if ($notice && $notice == 'success') {
			       	echo "<p style='color:green'>Shipment created successfully</p>";
			       	delete_option('hittech_fedex_status_'.$order_id);
			    }elseif($notice && $notice != 'success'){
			       	echo "<p style='color:red'>".esc_html($notice)."</p>";
			       	delete_option('hittech_fedex_status_'.$order_id);
			    }

		       	if(!empty($shipment_data)){
				// print_r($shipment_data);
				// die();
		       		if(isset($shipment_data[0])){
			       		foreach ($shipment_data as $key => $value) {
			       			if(isset($value['user_id'])){
		       					unset($custom_settings[$value['user_id']]);
		       				}
		       				if(isset($value['user_id']) && $value['user_id'] == 'default'){
		       					echo '<br/><b>Default Account</b><br/>';
		       				}else{
		       					$user = get_user_by( 'id', $value['user_id'] );
		       					echo '<br/><b>Account:</b> <small>'.esc_html($user->display_name).'</small><br/>';
		       				}
			       			echo '<b>Shipment ID: <font style = "color:green;">'.$value['tracking_num'].'</font></b><br>';
				       		echo '<a href="'.'http://'.esc_html($value['label']).'" target="_blank" style="background:#533e8c; color: #fff;border-color: #533e8c;box-shadow: 0px 1px 0px #533e8c;text-shadow: 0px 1px 0px #fff; margin-top: 5px;" class="button button-primary"> Shipping Label '.esc_html(($key + 1)).' </a> ';
				       		echo '<a href="'.'http://'.esc_html($value['invoice']).'" target="_blank" style = "margin-top: 5px;" class="button button-primary"> Invoice </a>';
			       		}
			        }else {
			        	$custom_settings = array();
			        	echo '<b>Shipment ID: <font style = "color:green;">'.esc_html($shipment_data['tracking_num']).'</font></b>';
			       		echo '<a href="'.esc_html($shipment_data['label']).'" target="_blank" style="background:#533e8c; color: #fff;border-color: #533e8c;box-shadow: 0px 1px 0px #533e8c;text-shadow: 0px 1px 0px #fff; margin-top: 5px;" class="button button-primary"> Shipping Label '.($key + 1).' </a> ';
			       		echo '<a href="'.esc_html($shipment_data['invoice']).'" target="_blank" style = "margin-top: 5px;" class="button button-primary"> Invoice </a>';
			        }
					echo '<br/><br/> <button name="hittech_fedex_reset" class="button button-secondary" style = "margin-top: 5px;"> Reset All </button><br/>';
					echo '<input type="hidden" name="nonce" value="'. wp_create_nonce( "hittech_fedex" ).'">';
		       	}
			// echo '<pre>';print_r($shipment_data);die();
		       	foreach ($custom_settings as $ukey => $value) {

						if(!empty($shipment_data) && isset($shipment_data[0])){
				       		foreach ($shipment_data as $value) {
				       			if ($value['user_id'] == $ukey) {
				       				continue;
				       			}
				       		}
						}elseif(!empty($shipment_data) && $shipment_data['user_id'] == $ukey){
							continue;
						}

		       			if($ukey == 'default'){

		       				echo '<br/><u><b>Default Account</b></u>';
					        echo '<br/><br/><b>Choose Service to Ship</b>';
					        echo '<br/><select name="hittech_fedex_service_code_default">';
					        if(!empty($general_settings['hittech_fedex_carrier'])){
					        	foreach ($general_settings['hittech_fedex_carrier'] as $key => $value) {
					        		echo "<option value='".esc_html($key)."'>".esc_html($_fedex_carriers[$key])."</option>";
					        	}
					        }
					        echo '</select>';

					        echo '<br/><b>Shipment Content</b>';
					        echo '<br/><input type="text" style="width:250px;margin-bottom:10px;"  name="hittech_fedex_shipment_content_default" value="Shipment Number ' . esc_html($order_id) . '" >';
							echo '<button name="hittech_fedex_create_label" value="default" style="background:#533e8c; color: #fff;border-color: #533e8c;box-shadow: 0px 1px 0px #533e8c;text-shadow: 0px 1px 0px #fff;" class="button button-primary">Create Shipment</button><br/>';
							echo '<input type="hidden" name="nonce" value="'. wp_create_nonce( "hittech_fedex" ).'">';
		       			}else {
		       				$user = get_user_by( 'id', $ukey );
		       				echo '<br/><u><b>Account:</b> <small>'.esc_html($user->display_name).'</small></u>';
		       				echo '<br/><br/><b>Choose Service to Ship</b>';
					        echo '<br/><select name="hittech_fedex_service_code_'.esc_html($ukey).'">';
					        if(!empty($general_settings['hittech_fedex_carrier'])){
					        	foreach ($general_settings['hittech_fedex_carrier'] as $key => $value) {
					        		echo "<option value='".esc_html($key)."'>".esc_html($_fedex_carriers[$key])."</option>";
					        	}
					        }
					        echo '</select>';

					        echo '<br/><b>Shipment Content</b>';
					        echo '<br/><input type="text" style="width:250px;margin-bottom:10px;"  name="hittech_fedex_shipment_content_'.esc_html($ukey).'" value="Shipment Number ' . esc_html($order_id) . '" >';
							echo '<button name="hittech_fedex_create_label" value="'.esc_html($ukey).'" style="background:#533e8c; color: #fff;border-color: #533e8c;box-shadow: 0px 1px 0px #533e8c;text-shadow: 0px 1px 0px #fff;" class="button button-primary">Create Shipment</button><br/>';
							echo '<input type="hidden" name="nonce" value="'. wp_create_nonce( "hittech_fedex" ).'">';
		       			}
		       		}
			}
			function hit_create_fedex_packages($products,$algorithm,$currency, $units, $max_weight = 10, $boxes = []){
				switch ($algorithm) {
					case 'box' :
						return $this->hit_box_shipping($products,$boxes,$currency,$units);
						break;
					case 'weight_based' :
						return $this->hit_weight_based_shipping($products,$currency,$units,$max_weight);
						break;
					case 'per_item' :
					default :
						return $this->hit_per_item_shipping($products,$currency,$units);
						break;
				}
			}

			function hit_per_item_shipping($products,$currency,$units) {
				$to_ship = array();
				$group_id = 1;
			
				// Get weight of order
				foreach ($products as $item_id => $product) {
					$group = array();
					
					$insurance_array = array(
						'Amount' => round($product['price']),
						'Currency' => $currency
					);
			
					if($product['weight'] < 0.001){
						$dhl_per_item_weight = 0.001;
					}else{
						$dhl_per_item_weight = round($product['weight'], 3);
					}
					$group = array(
						'GroupNumber' => $group_id,
						'GroupPackageCount' => 1,
						'Weight' => array(
						'Value' => $dhl_per_item_weight,
						'Units' => ($units == 'KG_CM') ? 'KG' : 'LBS'
					),
						'packed_products' => $product
					);
			
					if ($product['width'] && $product['height'] && $product['depth']) {
			
						$group['Dimensions'] = array(
							'Length' => max(1, round($product['depth'],3)),
							'Width' => max(1, round($product['width'],3)),
							'Height' => max(1, round($product['height'],3)),
							'Units' => ($units == 'KG_CM') ? 'CM' : 'IN'
						);
					}
			
					$group['packtype'] = 'BOX';
			
					$group['InsuredValue'] = $insurance_array;
			
					$chk_qty = $product['product_quantity'];
			
					for ($i = 0; $i < $chk_qty; $i++)
						$to_ship[] = $group;
			
					$group_id++;
				}
			
				return $to_ship;
			}

			function hit_weight_based_shipping($products,$currency,$units,$max_weight =10){
				if ( ! class_exists( 'WeightPack' ) ) {
					include_once 'classes/weight_pack/class-hit-weight-packing.php';
				}
				$weight_pack=new WeightPack('pack_ascending');
				$weight_pack->set_max_weight($max_weight);
			
				$package_total_weight = 0;
				$insured_value = 0;
			
				$ctr = 0;
				foreach ($products as $item_id => $product) {
					$ctr++;
					if (!$product['weight']) {
						$product['weight'] = 0.001;
					}
					$chk_qty = $product['product_quantity'];
			
					$weight_pack->add_item($product['weight'], $product, $chk_qty);
				}
			
				$pack   =   $weight_pack->pack_items();  
				$errors =   $pack->get_errors();
				if( !empty($errors) ){
					//do nothing
					return;
				} else {
					$boxes    =   $pack->get_packed_boxes();
					$unpacked_items =   $pack->get_unpacked_items();
					$insured_value        =   0;
					$packages      =   array_merge( $boxes, $unpacked_items ); // merge items if unpacked are allowed
					$package_count  =   sizeof($packages);
					// get all items to pass if item info in box is not distinguished
					$packable_items =   $weight_pack->get_packable_items();
					$all_items    =   array();
					
					if(is_array($packable_items)){
						foreach($packable_items as $packable_item){
							$all_items[]    =   $packable_item['data'];
						}
					}
					//pre($packable_items);
					$order_total = '';
			
					$to_ship  = array();
					$group_id = 1;
					foreach($packages as $package){//pre($package);
						$packed_products = array();
						$insured_value =0;
						foreach ($package['items'] as $value) {
							$insured_value += $value['price'];
						}
			
						$packed_products    =   isset($package['items']) ? $package['items'] : $all_items;
						// Creating package request
						$package_total_weight   = $package['weight'];
			
						$insurance_array = array(
							'Amount' => $insured_value,
							'Currency' => $currency
						);
			
						$group = array(
							'GroupNumber' => $group_id,
							'GroupPackageCount' => 1,
							'Weight' => array(
							'Value' => round($package_total_weight, 3),
							'Units' => ($units == 'KG_CM') ? 'KG' : 'LBS'
						),
							'packed_products' => $packed_products,
						);
						$group['InsuredValue'] = $insurance_array;
						$group['packtype'] = 'BOX';
			
						$to_ship[] = $group;
						$group_id++;
					}
				}
				return $to_ship;
			}

function hit_box_shipping($package,$boxes,$orderCurrency,$units,$chk = false)
  {
    if (!class_exists('HIT_Boxpack')) {
      include_once 'classes/hit-box-packing.php';
    }
    $boxpack = new HIT_Boxpack();
    if(empty($boxes))
    {
      return false;
    }
    
    // Define boxes
    foreach ($boxes as $key => $box) {
      if (!$box['enabled']) {
        continue;
      }
      $box['pack_type'] = !empty($box['pack_type']) ? $box['pack_type'] : 'BOX' ;

      $newbox = $boxpack->add_box($box['length'], $box['width'], $box['height'], $box['box_weight'], $box['pack_type']);

      if (isset($box['id'])) {
        $newbox->set_id(current(explode(':', $box['id'])));
      }

      if ($box['max_weight']) {
        $newbox->set_max_weight($box['max_weight']);
      }
      if ($box['pack_type']) {
        $newbox->set_packtype($box['pack_type']);
      }
    }

    // Add items
    foreach ($package as $item_id => $values) {

      if ( $values['width'] && $values['height'] && $values['depth'] && $values['weight'] ) {

        $dimensions = array( $values['depth'], $values['height'], $values['width']);
        $chk_qty = $values['product_quantity'];
        for ($i = 0; $i < $chk_qty; $i++) {
          $boxpack->add_item($dimensions[2], $dimensions[1], $dimensions[0], $values['weight'], $values['price'], array(
            'data' => $values
          )
                    );
        }
      } else {
        //    $this->debug(sprintf(__('Product #%s is missing dimensions. Aborting.', 'wf-shipping-dhl'), $item_id), 'error');
        return;
      }
    }

    // Pack it
    $boxpack->pack();
    $packages = $boxpack->get_packages();
    $to_ship = array();
    $group_id = 1;
    foreach ($packages as $package) {
      // if ($package->unpacked === true) {
        //$this->debug('Unpacked Item');
      // } else {
        //$this->debug('Packed ' . $package->id);
      // }

      $dimensions = array($package->length, $package->width, $package->height);

      sort($dimensions);
      $insurance_array = array(
        'Amount' => round($package->value),
        'Currency' => $orderCurrency
      );


      $group = array(
        'GroupNumber' => $group_id,
        'GroupPackageCount' => 1,
        'Weight' => array(
        'Value' => round($package->weight, 3),
        'Units' => ($units == 'KG_CM') ? 'KG' : 'LBS'
      ),
        'Dimensions' => array(
        'Length' => max(1, round($dimensions[2], 3)),
        'Width' => max(1, round($dimensions[1], 3)),
        'Height' => max(1, round($dimensions[0], 3)),
        'Units' => ($units == 'KG_CM') ? 'CM' : 'IN'
      ),
        'InsuredValue' => $insurance_array,
        'packed_products' => array(),
        'package_id' => $package->id,
        'packtype' => 'BOX'
      );

      if (!empty($package->packed) && is_array($package->packed)) {
        foreach ($package->packed as $packed) {
          $group['packed_products'][] = $packed->meta['data'];
        }
      }

      if (!isset($package->packed)) {
         foreach ($package->unpacked as $unpacked) {
           $group['packed_products'][] = $unpacked->meta['data'];
         }
      }

      $to_ship[] = $group;

      $group_id++;
    }

    return $to_ship;
 }

		
			public function hittech_hitshippo_create_fedex_shipping($order_id){
				$post = get_post($order_id);
		    	if($post->post_type !='shop_order' ){
		    		return;
		    	}

		    	if (  isset( $_POST[ 'hittech_fedex_reset' ] ) && isset($_POST['nonce']) && wp_verify_nonce( $_POST['nonce'], 'hittech_fedex' ) ) {
		    		delete_option('hittech_fedex_values_'.$order_id);
		    	}

		    	if (isset($_POST['hittech_fedex_create_label']) && isset($_POST['nonce']) && wp_verify_nonce( $_POST['nonce'], 'hittech_fedex' )) {
					
		    		$create_shipment_for = sanitize_text_field($_POST['hittech_fedex_create_label']);

		    		$service_code = sanitize_text_field($_POST['hittech_fedex_service_code_'.$create_shipment_for]);
		        	$ship_content = !empty($_POST['hittech_fedex_shipment_content_'.$create_shipment_for]) ? sanitize_text_field($_POST['hittech_fedex_shipment_content_'.$create_shipment_for]) : 'Shipment Content';

					$order = wc_get_order( $order_id );
					
			       if($order){
		        	$order_data = $order->get_data();

		       		$order_id = $order_data['id'];
		       		$order_currency = $order_data['currency'];

		       		$order_shipping_first_name = $order_data['shipping']['first_name'];
					$order_shipping_last_name = $order_data['shipping']['last_name'];
					$order_shipping_company = empty($order_data['shipping']['company']) ? $order_data['shipping']['first_name'] :  $order_data['shipping']['company'];
					$order_shipping_address_1 = $order_data['shipping']['address_1'];
					$order_shipping_address_2 = $order_data['shipping']['address_2'];
					$order_shipping_city = $order_data['shipping']['city'];
					$order_shipping_state = $order_data['shipping']['state'];
					$order_shipping_postcode = $order_data['shipping']['postcode'];
					$order_shipping_country = $order_data['shipping']['country'];
					$order_shipping_phone = $order_data['billing']['phone'];
					$order_shipping_email = $order_data['billing']['email'];
					$shipping_charge = $order_data['shipping_total'];

					$items = $order->get_items();
					$pack_products = array();
					$total_weg = 0;
					$general_settings = get_option('hittech_fedex_main_settings',array());

				//weight conversion wc_get_weight( $weight, $to_unit, $from_unit )
				// $general_settings = get_option('hit_ups_auto_main_settings',array());
				$woo_weg_unit = get_option('woocommerce_weight_unit');
				$woo_dim_unit = get_option('woocommerce_dimension_unit');
				$config_weg_unit = $general_settings['hittech_fedex_weight_unit'];
				$mod_weg_unit = (!empty($config_weg_unit) && $config_weg_unit == 'LB_IN') ? 'lbs' : 'kg';
				$mod_dim_unit = (!empty($config_weg_unit) && $config_weg_unit == 'LB_IN') ? 'in' : 'cm';

					foreach ( $items as $item ) {
						$product_data = $item->get_data();
					    $product = array();
					    $product['product_name'] = str_replace('"', '', $product_data['name']);
					    $product['product_quantity'] = $product_data['quantity'];
					    $product['product_id'] = $product_data['product_id'];

					    $product_variation_id = $item->get_variation_id();
					    if(empty($product_variation_id)){
					    	$getproduct = wc_get_product( $product_data['product_id'] );
					    }else{
					    	$getproduct = wc_get_product( $product_variation_id );
					    }

						$product['price'] = $getproduct->get_price();
						$product['width'] = (!empty($getproduct->get_width())) ? round(wc_get_dimension($getproduct->get_width(),$mod_dim_unit,$woo_dim_unit)) : '';
				    	$product['height'] = (!empty($getproduct->get_height())) ? round(wc_get_dimension($getproduct->get_height(),$mod_dim_unit,$woo_dim_unit)) : '';
				   		$product['depth'] = (!empty($getproduct->get_length())) ? round(wc_get_dimension($getproduct->get_length(),$mod_dim_unit,$woo_dim_unit)) : '';
						$product['weight'] = (!empty($getproduct->get_weight())) ? (float)round(wc_get_weight($getproduct->get_weight(),$mod_weg_unit,$woo_weg_unit),2) : '';
						$total_weg += (!empty($product['weight'])) ? $product['weight'] : 0;

					    $pack_products[] = $product;

					}

					$cod_services = array('PRIORITY_OVERNIGHT',
								'STANDARD_OVERNIGHT',
								'FEDEX_2_DAY_AM',
								'FEDEX_2_DAY',
								'FEDEX_EXPRESS_SAVER',
								'FEDEX_1_DAY_FREIGHT',
								'FEDEX_2_DAY_FREIGHT',
								'FEDEX_3_DAY_FREIGHT',
								'FEDEX_FIRST_FREIGHT',
								'FEDEX_FREIGHT_ECONOMY',
								'FEDEX_FREIGHT_PRIORITY',
								'FEDEX_GROUND',
								);

					$custom_settings = array();
					$custom_settings['default'] = array(
													'hittech_fedex_site_id' => $general_settings['hittech_fedex_site_id'],
													'hittech_fedex_site_pwd' => $general_settings['hittech_fedex_site_pwd'],
													'hittech_fedex_acc_no' => $general_settings['hittech_fedex_acc_no'],
													'hittech_fedex_access_key' => $general_settings['hittech_fedex_access_key'],
													'hittech_fedex_shipper_name' => $general_settings['hittech_fedex_shipper_name'],
													'hittech_fedex_company' => $general_settings['hittech_fedex_company'],
													'hittech_fedex_mob_num' => $general_settings['hittech_fedex_mob_num'],
													'hittech_fedex_email' => $general_settings['hittech_fedex_email'],
													'hittech_fedex_address1' => $general_settings['hittech_fedex_address1'],
													'hittech_fedex_address2' => $general_settings['hittech_fedex_address2'],
													'hittech_fedex_city' => $general_settings['hittech_fedex_city'],
													'hittech_fedex_state' => $general_settings['hittech_fedex_state'],
													'hittech_fedex_zip' => $general_settings['hittech_fedex_zip'],
													'hittech_fedex_country' => $general_settings['hittech_fedex_country'],
													'hittech_fedex_con_rate' => isset($general_settings['hittech_fedex_con_rate']) ? $general_settings['hittech_fedex_con_rate'] : '',
													'service_code' => $service_code,
													'hittech_fedex_shippo_mail' => $general_settings['hittech_fedex_shippo_mail'],
													'hittech_fedex_currency' => $general_settings['hittech_fedex_currency'],
												);

					$vendor_settings = array();

				if(isset($general_settings['hittech_fedex_v_enable']) && $general_settings['hittech_fedex_v_enable'] == 'yes' && isset($general_settings['hittech_fedex_v_labels']) && $general_settings['hittech_fedex_v_labels'] == 'yes'){
					// Multi Vendor Enabled

					foreach ($pack_products as $key => $value) {

						$product_id = $value['product_id'];
						$fedex_account = get_post_meta($product_id,'fedex_address', true);
						if(empty($fedex_account) || $fedex_account == 'default'){
							$fedex_account = 'default';
							if (!isset($vendor_settings[$fedex_account])) {
								$vendor_settings[$fedex_account] = $custom_settings['default'];
							}
							$vendor_settings[$fedex_account]['products'][] = $value;
						}

						if($fedex_account != 'default'){
							$user_account = get_post_meta($fedex_account,'hittech_fedex_vendor_settings', true);
							$user_account = empty($user_account) ? array() : $user_account;
							if(!empty($user_account)){
								if(!isset($vendor_settings[$fedex_account])){

									$vendor_settings[$fedex_account] = $custom_settings['default'];

									if($user_account['hittech_fedex_site_id'] != '' && $user_account['hittech_fedex_site_pwd'] != '' && $user_account['hittech_fedex_acc_no'] != '' && $user_account['hittech_fedex_access_key'] != ''){
										$vendor_settings[$fedex_account]['hittech_fedex_site_id'] = $user_account['hittech_fedex_site_id'];
										$vendor_settings[$fedex_account]['hittech_fedex_site_pwd'] = $user_account['hittech_fedex_site_pwd'];
										$vendor_settings[$fedex_account]['hittech_fedex_acc_no'] = $user_account['hittech_fedex_acc_no'];
										$vendor_settings[$fedex_account]['hittech_fedex_access_key'] = $user_account['hittech_fedex_access_key'];
									}

									if ($user_account['hittech_fedex_shipper_name'] != '' && $user_account['hittech_fedex_address1'] != '' && $user_account['hittech_fedex_city'] != '' && $user_account['hittech_fedex_state'] != '' && $user_account['hittech_fedex_zip'] != '' && $user_account['hittech_fedex_country'] != ''){

										if($user_account['hittech_fedex_shipper_name'] != ''){
											$vendor_settings[$fedex_account]['hittech_fedex_shipper_name'] = $user_account['hittech_fedex_shipper_name'];
										}

										if($user_account['hittech_fedex_company'] != ''){
											$vendor_settings[$fedex_account]['hittech_fedex_company'] = $user_account['hittech_fedex_company'];
										}

										if($user_account['hittech_fedex_mob_num'] != ''){
											$vendor_settings[$fedex_account]['hittech_fedex_mob_num'] = $user_account['hittech_fedex_mob_num'];
										}

										if($user_account['hittech_fedex_email'] != ''){
											$vendor_settings[$fedex_account]['hittech_fedex_email'] = $user_account['hittech_fedex_email'];
										}

										if($user_account['hittech_fedex_address1'] != ''){
											$vendor_settings[$fedex_account]['hittech_fedex_address1'] = $user_account['hittech_fedex_address1'];
										}

										$vendor_settings[$fedex_account]['hittech_fedex_address2'] = !empty($user_account['hittech_fedex_address2']) ? $user_account['hittech_fedex_address2'] : '';

										if($user_account['hittech_fedex_city'] != ''){
											$vendor_settings[$fedex_account]['hittech_fedex_city'] = $user_account['hittech_fedex_city'];
										}

										if($user_account['hittech_fedex_state'] != ''){
											$vendor_settings[$fedex_account]['hittech_fedex_state'] = $user_account['hittech_fedex_state'];
										}

										if($user_account['hittech_fedex_zip'] != ''){
											$vendor_settings[$fedex_account]['hittech_fedex_zip'] = $user_account['hittech_fedex_zip'];
										}

										if($user_account['hittech_fedex_country'] != ''){
											$vendor_settings[$fedex_account]['hittech_fedex_country'] = $user_account['hittech_fedex_country'];
										}

										if (isset($user_account['hittech_fedex_con_rate'])) {
											$vendor_settings[$fedex_account]['hittech_fedex_con_rate'] = $user_account['hittech_fedex_con_rate'];
										}

										if (isset($user_account['hittech_fedex_currency'])) {
											$vendor_settings[$fedex_account]['hittech_fedex_currency'] = $user_account['hittech_fedex_currency'];
										}

									}

									if(isset($general_settings['hittech_fedex_v_email']) && $general_settings['hittech_fedex_v_email'] == 'yes'){
										$user_dat = get_userdata($fedex_account);
										$vendor_settings[$fedex_account]['hittech_fedex_shippo_mail'] = $user_dat->data->user_email;
									}

								}
								unset($value['product_id']);
								$vendor_settings[$fedex_account]['products'][] = $value;
							}else {
								$fedex_account = 'default';
								if (!isset($vendor_settings[$fedex_account])) {
									$vendor_settings[$fedex_account] = $custom_settings['default'];
								}
								$vendor_settings[$fedex_account]['products'][] = $value;
							}
						}
					}

				}

				if(empty($vendor_settings)){
					$custom_settings['default']['products'] = $pack_products;
				}else{
					$custom_settings = $vendor_settings;
				}

						$mode = 'live';
						if(isset($general_settings['hittech_fedex_test']) && $general_settings['hittech_fedex_test']== 'yes'){
							$mode = 'test';
						}
						$execution = 'manual';
						// if(isset($general_settings['hittech_fedex_shippo_label_gen']) && $general_settings['hittech_fedex_shippo_label_gen']== 'yes'){
						// 	$execution = 'auto';
						// }

						$acc_rates = ($general_settings['hittech_fedex_account_rates'] == 'yes') ? 'LIST' : 'NONE';
						$residental_del = ($general_settings['hittech_fedex_res_f'] == 'yes') ? 'true' : 'false';
						$col_type = (isset($general_settings['hittech_fedex_collection_type']) && !empty($general_settings['hittech_fedex_collection_type'])) ? $general_settings['hittech_fedex_collection_type'] : "CASH";
						$cod = "N";

						if ((isset($general_settings['hittech_fedex_cod']) && $general_settings['hittech_fedex_cod'] == "yes") && ($custom_settings[$create_shipment_for]['hittech_fedex_country'] == $order_shipping_country) && (in_array($service_code, $cod_services)) ) {
							$cod = "Y";
						}

						$boxes_to_shipo = array();
						if (isset($general_settings['hittech_fedex_packing_type']) && $general_settings['hittech_fedex_packing_type'] == "box") {
							if (isset($general_settings['hittech_fedex_boxes']) && !empty($general_settings['hittech_fedex_boxes'])) {
								foreach ($general_settings['hittech_fedex_boxes'] as $box) {
									if ($box['enabled'] != 1) {
										continue;
									}else {
										$boxes_to_shipo[] = $box;
									}
								}
							}
						}

						$shipment_time =date('c' , strtotime('+1 weekday'));

						$data = array();
						$data['integrated_key'] = $general_settings['hittech_fedex_shippo_int_key'];
						$data['order_id'] = $order_id;
						$data['exec_type'] = $execution;
						$data['mode'] = $mode;
						$data['ship_price'] = $shipping_charge;
						$data['meta'] = array(
							"site_id" => $custom_settings[$create_shipment_for]['hittech_fedex_site_id'],
							"password"  => $custom_settings[$create_shipment_for]['hittech_fedex_site_pwd'],
							"accountnum" => $custom_settings[$create_shipment_for]['hittech_fedex_acc_no'],
							"meternum" => $custom_settings[$create_shipment_for]['hittech_fedex_access_key'],
							"t_company" => $order_shipping_company,
							"t_address1" => $order_shipping_address_1,
							"t_address2" => $order_shipping_address_2,
							"t_city" => $order_shipping_city,
							"t_state" => $order_shipping_state,
							"t_postal" => $order_shipping_postcode,
							"t_country" => $order_shipping_country,
							"t_name" => $order_shipping_first_name . ' '. $order_shipping_last_name,
							"t_phone" => $order_shipping_phone,
							"t_email" => $order_shipping_email,
							"residential" => $residental_del,
							"drop_off_type" => $general_settings['hittech_fedex_drop_off'],
							"packing_type" => $general_settings['hittech_fedex_ship_pack_type'],
							"shipping_charge" => $shipping_charge,
							"products" => $custom_settings[$create_shipment_for]['products'],
							"pack_algorithm" => $general_settings['hittech_fedex_packing_type'],
							"boxes" => $boxes_to_shipo,
							"max_weight" => $general_settings['hittech_fedex_max_weight'],
							"wight_dim_unit" => $general_settings['hittech_fedex_weight_unit'],
							"total_product_weg" => $total_weg,
							"service_code" => $custom_settings[$create_shipment_for]['service_code'],	//'PRIORITY_OVERNIGHT'
							"shipment_content" => $ship_content,
							"s_company" => $custom_settings[$create_shipment_for]['hittech_fedex_company'],
							"s_address1" => $custom_settings[$create_shipment_for]['hittech_fedex_address1'],
							"s_address2" => $custom_settings[$create_shipment_for]['hittech_fedex_address2'],
							"s_city" => $custom_settings[$create_shipment_for]['hittech_fedex_city'],
							"s_state" => $custom_settings[$create_shipment_for]['hittech_fedex_state'],
							"s_postal" => $custom_settings[$create_shipment_for]['hittech_fedex_zip'],
							"s_country" => $custom_settings[$create_shipment_for]['hittech_fedex_country'],
							// "gstin" => $general_settings['hittech_fedex_gstin'],
							"s_name" => $custom_settings[$create_shipment_for]['hittech_fedex_shipper_name'],
							"s_phone" => $custom_settings[$create_shipment_for]['hittech_fedex_mob_num'],
							"s_email" => $custom_settings[$create_shipment_for]['hittech_fedex_email'],
							"label_format" => "PDF",
							"label_format_type" => "COMMON2D",
							"label_size" => $general_settings['hittech_fedex_label_size'],
							"account_rates" => $acc_rates,
							"sent_email_to" => $custom_settings[$create_shipment_for]['hittech_fedex_shippo_mail'],
							"cod" => $cod,
							"woo_curr" => get_option('woocommerce_currency'),
							"fedex_curr" => $custom_settings[$create_shipment_for]['hittech_fedex_currency'],
							"con_rate" => $custom_settings[$create_shipment_for]['hittech_fedex_con_rate'],
							"col_type" => $col_type,
						);

						$box_data = isset($data['meta']['boxes']) ? $data['meta']['boxes'] : [];
						$fedex_packs = $this->hit_create_fedex_packages( $data['meta']['products'], $data['meta']['pack_algorithm'], $data['meta']['currency'], $data['meta']['wight_dim_unit'], $data['meta']['max_weight'], $box_data);

						$total_weight_of_packages = 0;
						$total_cost_of_packages = 0;
						foreach ($fedex_packs as $key => $value) {
							$total_weight_of_packages += $value['Weight']['Value'];
							$total_cost_of_packages += $value['InsuredValue']['Amount'];
						}
						if ($data['meta']['wight_dim_unit'] == "KG_CM") {
							$weight_unit = "KG";
							$length_unit = "CM";
						}else {
							$weight_unit = "LB";
							$length_unit = "IN";
						}
						 // echo '<pre>';print_r($data);
						 // print_r(json_encode($data));
						 // die();
						 if ( isset($data['meta']['packing_type']) && $data['meta']['packing_type'] == "FEDEX_ENVELOPE" && $data['meta']['service_code'] != "FEDEX_GROUND"){
							if( isset($weight_unit) && ($weight_unit == "KG") && ( ($total_weight_of_packages * 2.205) > 4.5 ) ){
								$data['meta']['packing_type'] = "YOUR_PACKAGING";
							}elseif( isset($weight_unit) && ($weight_unit == "LB") && ( $total_weight_of_packages > 4.5 ) ){
								$data['meta']['packing_type'] = "YOUR_PACKAGING";
							}
						}
						$t_company = substr(htmlspecialchars(!empty( $data['meta']['t_company'] ) ? $data['meta']['t_company'] : $data['meta']['t_name']), 0, 35) ;
						$to_city = $data['meta']['t_city'];

						 $xmlRequest_start_raw = "<soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/' xmlns='http://fedex.com/ws/ship/v28'>
						 <soapenv:Header/>
									 <soapenv:Body>
										<ProcessShipmentRequest>
										   <WebAuthenticationDetail>
											  <UserCredential>
												 <Key>".$data['meta']['site_id']."</Key>
												 <Password>".$data['meta']['password']."</Password>
											  </UserCredential>
										   </WebAuthenticationDetail>
										   <ClientDetail>
											  <AccountNumber>".$data['meta']['accountnum']."</AccountNumber>
											  <MeterNumber>".$data['meta']['meternum']."</MeterNumber>
										   </ClientDetail>
										   <Version>
											  <ServiceId>ship</ServiceId>
											  <Major>28</Major>
											  <Intermediate>0</Intermediate>
											  <Minor>0</Minor>
										   </Version>
										   <RequestedShipment>
											  <ShipTimestamp>".$shipment_time."</ShipTimestamp>
											  <DropoffType>".$data['meta']['drop_off_type']."</DropoffType>
											  <ServiceType>".$data['meta']['service_code']."</ServiceType>
											  <PackagingType>".$data['meta']['packing_type']."</PackagingType>
											  <TotalWeight>
												 <Units>".$weight_unit."</Units>
												 <Value>".$total_weight_of_packages."</Value>
											  </TotalWeight>
											  <Shipper>
												 <AccountNumber>".$data['meta']['accountnum']."</AccountNumber>
												 <Contact>
													<PersonName>".$data['meta']['s_name']."</PersonName>
													<CompanyName>".$data['meta']['s_company']."</CompanyName>
													<PhoneNumber>".$data['meta']['s_phone']."</PhoneNumber>
													<EMailAddress>".$data['meta']['s_email']."</EMailAddress>
												 </Contact>
												 <Address>
													<StreetLines>".$data['meta']['s_address1']."</StreetLines>
													<City>".$data['meta']['s_city']."</City>
													<StateOrProvinceCode>".$data['meta']['s_state']."</StateOrProvinceCode>
													<PostalCode>".$data['meta']['s_postal']."</PostalCode>
													<CountryCode>".$data['meta']['s_country']."</CountryCode>
												  </Address>
											  </Shipper>
											  <Recipient>
												 <Contact>
													<PersonName>".$data['meta']['t_name']."</PersonName>
													<CompanyName>".$t_company."</CompanyName>
													<PhoneNumber>".$data['meta']['t_phone']."</PhoneNumber>
													<EMailAddress>".$data['meta']['t_email']."</EMailAddress>
												 </Contact>
												 <Address>
													<StreetLines>".$data['meta']['t_address1']."</StreetLines>
													<City>".$to_city."</City>
													<StateOrProvinceCode>".$data['meta']['t_state']."</StateOrProvinceCode>
													<PostalCode>".$data['meta']['t_postal']."</PostalCode>
													<CountryCode>".$data['meta']['t_country']."</CountryCode>
													<Residential>".$data['meta']['residential']."</Residential>
												 </Address>
											  </Recipient>
											  <ShippingChargesPayment>
												 <PaymentType>SENDER</PaymentType>
												 <Payor>
													<ResponsibleParty>
													   <AccountNumber>".$data['meta']['accountnum']."</AccountNumber>
													</ResponsibleParty>
												 </Payor>
											  </ShippingChargesPayment>";
						  

							  $label_image_type = !empty($data['meta']['label_format']) ? $data['meta']['label_format'] : 'PDF';
							  $label_stock_type = !empty($data['meta']['label_size']) ? $data['meta']['label_size'] : 'PAPER_7X4.75';

							  $xmlRequest_label = "
											  <LabelSpecification>
												  <LabelFormatType>COMMON2D</LabelFormatType>
												 <ImageType>".$label_image_type."</ImageType>
												 <LabelStockType>".$label_stock_type."</LabelStockType>
											  </LabelSpecification>
											  <ShippingDocumentSpecification>
											   <ShippingDocumentTypes>COMMERCIAL_INVOICE</ShippingDocumentTypes>
												   <CommercialInvoiceDetail>
													   <Format>
														  <ImageType>PDF</ImageType>
														  <StockType>PAPER_LETTER</StockType>
														  <ProvideInstructions>1</ProvideInstructions>
													   </Format>
												   </CommercialInvoiceDetail>
											   </ShippingDocumentSpecification>
											  <RateRequestTypes>LIST</RateRequestTypes>";
											  

							  $xmlRequest_mid ="
											  <PackageCount>1</PackageCount>";
							  
							  $line_item = "";
							  $line_items = [];
							  $cod_packs = [];
							  $col_type = isset($data['meta']['col_type']) ? $data['meta']['col_type'] : "CASH";

							  foreach ($fedex_packs as $key => $parcel) {		//<GroupPackageCount>".$parcel['GroupPackageCount']."</GroupPackageCount>
								  $line_item = "<RequestedPackageLineItems>
												 <SequenceNumber>".($key+1)."</SequenceNumber>
												 <GroupNumber>".$parcel['GroupNumber']."</GroupNumber>
												 <GroupPackageCount>1</GroupPackageCount>
												 <Weight>
													<Units>".$weight_unit."</Units>
													<Value>".$parcel['Weight']['Value']."</Value>
												 </Weight>";

							  if(isset($parcel['Dimensions'])){
								  $line_item .= "<Dimensions>
												<Length>".$parcel['Dimensions']['Length']."</Length>
												<Width>".$parcel['Dimensions']['Width']."</Width>
												<Height>".$parcel['Dimensions']['Height']."</Height>
												<Units>".$length_unit."</Units>
											 </Dimensions>
											 {COD}";
							  }
								  $line_item .= "<ItemDescriptionForClearance>".$data['meta']['shipment_content']."</ItemDescriptionForClearance></RequestedPackageLineItems>";
								  $line_items[] = $line_item;

								  $cost_per_pack = number_format($parcel['InsuredValue']['Amount'], 2, '.', '');
								  if ( (isset($data['meta']['cod']) && $data['meta']['cod'] == "Y") && (isset($data['meta']['woo_curr']) && !empty($data['meta']['woo_curr'])) && (isset($data['meta']['fedex_curr']) && !empty($data['meta']['fedex_curr'])) && ($data['meta']['woo_curr'] != $data['meta']['fedex_curr']) && (isset($data['meta']['con_rate']) && !empty($data['meta']['con_rate'])) ) {
									  $cost_per_pack *= $data['meta']['con_rate'];
								  }
								  $cod_pack = "<SpecialServicesRequested>
												  <SpecialServiceTypes>COD</SpecialServiceTypes>
												  <CodDetail>
													  <CodCollectionAmount>
														  <Currency>".$data['meta']['currency']."</Currency>
														  <Amount>".$cost_per_pack."</Amount>
													  </CodCollectionAmount>
													  <CollectionType>".$col_type."</CollectionType>
												  </CodDetail>
											  </SpecialServicesRequested>";
								  $cod_packs[] = $cod_pack;
							  }

							  $xmlRequest_end = "
										  </RequestedShipment>
										</ProcessShipmentRequest>
									 </soapenv:Body>
								  </soapenv:Envelope>";

			  $request_url = (isset($data['mode']) && $data['mode'] != 'test') ? 'https://ws.fedex.com:443/web-services/ship' : 'https://wsbeta.fedex.com:443/web-services/ship';
			  $total_package_count = sizeof($fedex_packs);
			  $master_tracking = "";
			  $master_tracking_id = "";
			  $xmlRequest_cust = "";
			  $line_item_key = 0;
			  $status = "";
			  $label_file_name = [];
			  $shipment_info = [];
			$output = array();
			$output['label'] = '';
			  for ($i = 0; $i < $total_package_count ; $i++) {
					//   if($master_tracking_id){
					// 	  $master_tracking = "
					// 						  <MasterTrackingId>
					// 							 <TrackingIdType>FEDEX</TrackingIdType>
					// 							 <TrackingNumber>".$master_tracking_id."</TrackingNumber>
					// 						  </MasterTrackingId>";
					//   }

					  // $shipping_charge = $data['meta']['shipping_charge'];
					  // $shipping_charge_per_pck = $shipping_charge/sizeof($fedex_packs);

					  if (($data['meta']['s_country'] != $data['meta']['t_country']) || $data['meta']['t_country'] == "IN") {
						  $unit_price = $fedex_packs[$line_item_key]['InsuredValue']['Amount'];
						  if ( (isset($data['meta']['woo_curr']) && !empty($data['meta']['woo_curr'])) && (isset($data['meta']['fedex_curr']) && !empty($data['meta']['fedex_curr'])) && ($data['meta']['woo_curr'] != $data['meta']['fedex_curr']) && (isset($data['meta']['con_rate']) && !empty($data['meta']['con_rate'])) ) {
							  $unit_price *= $data['meta']['con_rate'];
						  }
							 $xmlRequest_cust = "
											 <CustomsClearanceDetail>
										  <DutiesPayment>
												  <PaymentType>SENDER</PaymentType>
												  <Payor>
													 <ResponsibleParty>
														<AccountNumber>".$data['meta']['accountnum']."</AccountNumber>
														</ResponsibleParty>
												  </Payor>
											   </DutiesPayment>
											   <CustomsValue>
												  <Currency>".$data['meta']['currency']."</Currency>
												  <Amount>".number_format($total_cost_of_packages, 2, '.', '')."</Amount>
											   </CustomsValue>
											   <CommercialInvoice>
												  <Purpose>SOLD</Purpose>
											  </CommercialInvoice>
											   <Commodities>
											  <NumberOfPieces>".sizeof($fedex_packs)."</NumberOfPieces>
											  <Description>".$data['meta']['shipment_content']."</Description>
											  <CountryOfManufacture>".$data['meta']['s_country']."</CountryOfManufacture>
											  <Weight>
												   <Units>".$weight_unit."</Units>
												   <Value>".$total_weight_of_packages."</Value>
												</Weight>
												<Quantity>".sizeof($fedex_packs)."</Quantity>
												<QuantityUnits>".$length_unit."</QuantityUnits>
												<UnitPrice>
												   <Currency>".$data['meta']['currency']."</Currency>
											  <Amount>".number_format($unit_price, 2, '.', '')."</Amount>
											  </UnitPrice>
												<CustomsValue>
												   <Currency>".$data['meta']['currency']."</Currency>
												   <Amount>".number_format($total_cost_of_packages, 2, '.', '')."</Amount>
												</CustomsValue>
											  </Commodities>
											</CustomsClearanceDetail>";
											// echo '<pre>';
											// print_r(htmlspecialchars($xmlRequest_cust));
											// die();
					  }

					  $xmlRequest_start = $xmlRequest_start_raw;
					  if ( (isset($data['cod']) && $data['cod'] == "Y") && (!empty($data['service_code']) && $data['service_code'] != "FEDEX_GROUND") ) {
						  $xmlRequest_start .= $cod_packs[$line_item_key];
						  $line_items[$line_item_key] = str_replace('{COD}', '', $line_items[$line_item_key]);
					  }elseif ( (isset($data['cod']) && $data['cod'] == "Y") && (!empty($data['service_code']) && $data['service_code'] == "FEDEX_GROUND") ) {
						  $line_items[$line_item_key] = str_replace('{COD}', $cod_packs[$line_item_key], $line_items[$line_item_key]);
					  }else {
						  $line_items[$line_item_key] = str_replace('{COD}', '', $line_items[$line_item_key]);
					  }

				  $xmlRequest_single = $xmlRequest_start;
				  $xmlRequest_single .= $xmlRequest_cust;
				  $xmlRequest_single .= $xmlRequest_label;
				  $xmlRequest_single .= $master_tracking;
				  $xmlRequest_single .= $xmlRequest_mid;
				  $xmlRequest_single .= $line_items[$line_item_key];
				  $xmlRequest_single .= $xmlRequest_end;
		  // echo '<pre>';
		  // echo json_encode(htmlspecialchars($xmlRequest_single));
		  // die();

		  $result = wp_remote_post($request_url, array(
			'method' => 'POST',
			'timeout' => 70,
			'sslverify' => 0,
			'body' => $xmlRequest_single
				)
			);

		
		  $result_xml = str_replace(array(':','-'), '', $result['body']);
		  $xml = '';
		  libxml_use_internal_errors(true);
		  if(!empty($result)){
			  $xml = simplexml_load_string(utf8_encode($result_xml));
			  $response = $xml->SOAPENVBody->ProcessShipmentReply;
		  }
		  if (empty($result['body'])) {
			$result_array['status'] =  'Ouput Return Empty';
			update_option('hittech_fedex_status_'.$order_id, $result_array['status']);
			
		}elseif ($response->HighestSeverity == 'FAILURE' || $response->HighestSeverity == 'ERROR') {
			$result_array['status'] =  (string)$response->Notifications->Message;
			update_option('hittech_fedex_status_'.$order_id, $result_array['status']);
			
		}elseif ($xml->SOAPENVBody->SOAPENVFault->faultstring == 'Fault') {
			$result_array['status'] =  (string)$xml->SOAPENVBody->SOAPENVFault->detail->desc;
			update_option('hittech_fedex_status_'.$order_id, $result_array['status']);

		}elseif ($xml->SOAPENVBody->v28ProcessShipmentReply->v28HighestSeverity == 'FAILURE' || $xml->SOAPENVBody->v28ProcessShipmentReply->v28HighestSeverity == 'ERROR') {
			$result_array['status'] =  (string)$xml->SOAPENVBody->v28ProcessShipmentReply->v28Notifications->v28Message;
			update_option('hittech_fedex_status_'.$order_id, $result_array['status']);

		}else {
			$master_tracking_id = (string)$response->CompletedShipmentDetail->MasterTrackingId->TrackingNumber;
			
			if (!isset($response->CompletedShipmentDetail->CompletedPackageDetails->Label->Parts->Image)) {
			  	$result_array['status'] =  'Label did not returned by Fedex.';
				echo json_encode($result_array);
				die();
			}
			$dir = plugin_dir_path( __FILE__ ).'labels/';
			$result_array['tracking_num'] = $master_tracking_id;

			$label_image = (string) $response->CompletedShipmentDetail->CompletedPackageDetails->Label->Parts->Image;
			if (isset($response->CompletedShipmentDetail->ShipmentDocuments->Parts->Image)) {
				$comm_label_image = (string)$response->CompletedShipmentDetail->ShipmentDocuments->Parts->Image;
				$fp = fopen($dir.'order_'.$data['order_id'].'_track_'.$result_array['tracking_num'].'_invoice.pdf', 'wb');
				fwrite($fp, base64_decode($comm_label_image)); //Create COD Return PNG or PDF file
				fclose($fp);
			}
			// LABELS
			$fp = fopen($dir.'label_'.$i.'_order_'.$data['order_id'].'_track_'.$result_array['tracking_num'].'.pdf', 'wb');
			fwrite($fp, base64_decode($label_image)); //Create COD Return PNG or PDF file
			fclose($fp);
			$output['label'] .= $dir.'label_'.$i.'_order_'.$data['order_id'].'_track_'.$result_array['tracking_num'].'.pdf,';
			$output['invoice'] = $dir.'order_'.$data['order_id'].'_track_'.$result_array['tracking_num'].'_invoice.pdf';
		}
		if(isset($master_tracking_id) && !empty($master_tracking_id)){
			$output['user_id'] = $create_shipment_for;
			$result_arr = json_decode(get_option('hittech_fedex_values_'.$order_id, array()));
			$result_arr[] = $output;
			update_option('hittech_fedex_values_'.$order_id, json_encode($result_arr));
			update_option('hittech_fedex_status_'.$order_id, 'Success');
		}
	// echo '<pre>';
	// print_r($result);
	
		}
		// die();	

								// if($output){
								// 	if(isset($output['status'])){

								// 		if(isset($output['status']) && $output['status'] != 'success'){
								// 			   update_option('hittech_fedex_status_'.$order_id, $output['status']);

								// 		}else if(isset($output['status']) && $output['status'] == 'success'){
								// 			$output['user_id'] = $create_shipment_for;
								// 			$result_arr = json_decode(get_option('hittech_fedex_values_'.$order_id, array()));
								// 			$result_arr[] = $output;

								// 			update_option('hittech_fedex_values_'.$order_id, json_encode($result_arr));
								// 			update_option('hittech_fedex_status_'.$order_id, $output['status']);
								// 		}
								// 	}else{
								// 		update_option('hittech_fedex_status_'.$order_id, 'Site not Connected with HITShipo. Contact HITShipo Team.');
								// 	}
								// }else{
								// 	update_option('hittech_fedex_status_'.$order_id, 'Site not Connected with HITShipo. Contact HITShipo Team.');
								// }

			    	
			}

		}
		}

	}
	new hittech_fedex_parent();
}
}
