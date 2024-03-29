<?php
/**
 * Shipping.php
 *
 * Shipping module control and framework
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 28 March, 2008
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.1
 * @subpackage shipping
 **/

/**
 * ShippingModules class
 *
 * Controller for managing and loading the shipping modules that
 * are installed.
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage shipping
 **/
class ShippingModules extends ModuleLoader {

	var $dimensions = false;	// Flags when a module requires product dimensions
	var $postcodes = false;		// Flags when a module requires a post code for shipping estimates
	var $realtime = false;		// Flags when a module provides realtime rates
	var $methods = array();		// Registry of shipping method handles

	/**
	 * Initializes the shipping module loader
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void Description...
	 **/
	function __construct () {

		$this->path = SHOPP_SHIPPING;

		// Get hooks in place before getting things started
		add_action('shopp_module_loaded',array(&$this,'addmethods'));
		add_action('shopp_settings_shipping_ui',array(&$this,'ui'));

		$this->installed();
		$this->activated();
		$this->load();

	}

	/**
	 * Determines the activated shipping modules from the configured rates
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array List of module names for the activated modules
	 **/
	function activated () {
		$this->activated = array();
		$active = shopp_setting('active_shipping');
		if (!empty($active)) $this->activated = array_keys($active);

		return $this->activated;
	}

	/**
	 * Loads all the installed shipping modules for the shipping settings
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function settings () {
		$this->load(true);
	}

	/**
	 * Adds active shipping methods to the ShippingModules method registry
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $module The module class name
	 * @return void
	 **/
	function addmethods ($module) {
		if (!isset($this->active[$module])) return;

		$active = shopp_setting('active_shipping');

		$m = isset($active[$module])?$active[$module]:false;

		if (empty($m)) return;

		if ($this->active[$module]->postcode) $this->postcodes = true;
		if ($this->active[$module]->dimensions) $this->dimensions = true;
		if ($this->active[$module]->realtime) $this->realtime = true;

		if (!is_array($m)) return $this->methods[$module] = $module;

		foreach ($m as $index => $set) {
			$setting_name = "$module-$index";
			$setting = shopp_setting($setting_name);
			if (empty($setting)) continue;
 			$this->methods[$setting_name] = $module;
		}
	}

	/**
	 * Returns all of the active shipping methods
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array The list of method handles
	 **/
	function methods () {
		return $this->methods;
	}

	/**
	 * Get a specified gateway
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void Description...
	 **/
	function &get ($module) {
		if (empty($this->active)) $this->settings();
		if (!isset($this->active[$module])) return false;
		return $this->active[$module];
	}

	/**
	 * Initializes the settings UI for each loaded module
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void Description...
	 **/
	function ui () {
		foreach ($this->active as $package => &$module)
			$module->initui($package,$this->modules[$package]->name);
	}

	function templates () {
		foreach ($this->active as $package => &$module)
			$module->uitemplate($package,$this->modules[$package]->name);
	}

} // END class ShippingModules

/**
 * ShippingModule interface
 *
 * Provides a structured template of object methods that must be implemented
 * in order to have a fully compatible shipping module
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage shipping
 **/
interface ShippingModule {

	/**
	 * Registers the functions the shipping module will implement
	 *
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function methods ();

	/**
	 * Embeded JavaScript to render the shipping module settings interface
	 *
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function ui ();

	/**
	 * Determines if the shipping module has been activated
	 *
	 * NOTE: Automatically implemented by extending the ShippingFramework
	 *
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	public function activated();

	/**
	 * Used to initialize/reset shipping module calculation properties
	 *
	 * An empty stub function must be defined even if the module does not
	 * use it
	 *
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function init ();

	/**
	 * Used to calculate Item-specific shipping costs
	 *
	 * An empty stub function must be defined even if the module does not
	 * use it
	 *
	 * @since 1.1
	 *
	 * @param int $id The index of the Item in the cart contents array
	 * @param Item $Item The cart Item object
	 * @return void
	 **/
	public function calcitem($id,$Item);

	/**
	 * Used to calculate aggregate shipping amounts
	 *
	 * An empty stub function must be defined even if the module does not
	 * use it
	 *
	 * @since 1.1
	 *
	 * @param array $options A list of current ShippingOption objects
	 * @param Order $Order A reference to the current Order object
	 * @return array The modified $options list
	 **/
	public function calculate($options,$Order);

} // END interface ShippingModule

/**
 * ShippingFramework class
 *
 * Provides basic shipping module functionality
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage shipping
 **/
abstract class ShippingFramework {

	var $module = false;		// The module class name
	var $base = false;			// Base of operations settings
	var $postcode = false;		// Flag to enable the postcode field in the cart
	var $rates = array();		// The shipping rates that apply to the module
	var $dimensions = false;	// Uses dimensions in calculating estimates
	var $xml = false;			// Flag to load and enable XML parsing
	var $soap = false;			// Flag to load and SOAP client helper
	var $singular = false;		// Provides realtime rate lookups
	var $realtime = false;		// Shipping module can only be loaded once
	var $packager = false;		// Shipping packager object
	var $setting = '';			// Setting name for the shipping module setting record
	var $destinations = '';		// Destination label for settings display
	var $settings = array();	// Settings for the shipping module

	/**
	 * Initializes a shipping module
	 *
	 * Grabs settings that most shipping modules will needs and establishes
	 * the event listeners to trigger module functionality.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function __construct () {
		$Order = ShoppOrder();

		$this->module = get_class($this);

		if ($this->singular) $this->settings = shopp_setting($this->module);
		else {
			$active = shopp_setting('active_shipping');
			if (isset($active[$this->module]) && is_array($active[$this->module])) {
				$this->methods = array();
				$this->fallbacks = array();
				foreach ($active[$this->module] as $index => $set) {
					$setting = shopp_setting("$this->module-$index");
					if ( isset($setting['fallback']) && 'on' == $setting['fallback'] )
						$this->fallbacks["$this->module-$index"] = $setting;
					else $this->methods["$this->module-$index"] = $setting;
				}
			}
		}

		$this->base = shopp_setting('base_operations');
		$this->units = shopp_setting('weight_unit');

		if ($this->postcode) $Order->Cart->showpostcode = true;

		if ( $this->xml && ! class_exists('xmlQuery')) require(SHOPP_MODEL_PATH."/XML.php");
		if ( $this->soap && ! class_exists('nusoap_base') ) require(SHOPP_MODEL_PATH."/SOAP.php");

		// Setup default packaging for shipping module
		$this->settings['shipping_packaging'] = shopp_setting('shipping_packaging');

		// Shipping module can override the default behavior and the global setting by specifying the local packaging property
		if ( isset($this->packaging) && $this->packaging != $this->settings['shipping_packaging'] )
			$this->settings['shipping_packaging'] = $this->packaging;
		$this->packager = apply_filters('shopp_'.strtolower($this->module).'_packager', new ShippingPackager( array( 'type' => $this->settings['shipping_packaging'] ), $this->module ));

		$this->destinations = __('Service provider markets','Shopp');

		add_action('shopp_calculate_shipping_init',array(&$this,'init'));
		add_action('shopp_calculate_item_shipping',array(&$this,'calcitem'),10,2);
		add_action('shopp_calculate_shipping',array(&$this,'calculate'),10,2);

		if (isset($this->fallbacks) && !empty($this->fallbacks)) {
			add_action('shopp_calculate_fallback_shipping_init',array(&$this,'fallbacks'));
			add_action('shopp_calculate_fallback_shipping',array(&$this,'calculate'));
			add_action('shopp_calculate_fallback_shipping',array(&$this,'reset'),20);
		}

	}

	function fallbacks () {
		$this->_methods = $this->methods;
		$this->methods = $this->fallbacks;
	}

	function reset () {
		$this->methods = $this->_methods;
	}

	function setting ($id=false) {
		$active = shopp_setting('active_shipping');
		if (!$active) $active = array();

		if (!isset($active[$this->module])) $active[$this->module] = array();

		if (false === $id) {
			$active[$this->module][] = true;
			$id = count($active[$this->module])-1;
		}
		$this->setting = "{$this->module}-$id";

		if (isset($active[$this->module][$id]))
			$settings = shopp_setting($this->setting);

		if ($settings) $this->settings = $settings;
	}

	/**
	 * Determines if the current module is configured to be activated or not
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	function activated () {
		global $Shopp;
		$activated = $Shopp->Shipping->activated();
		return (in_array($this->module,$activated));
	}

	/**
	 * Initialize a list of shipping module settings
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $name The name of a setting
	 * @param string $name... (optional) Additional setting names to initialize
	 * @return void
	 **/
	function setup () {
		$settings = func_get_args();
		foreach ($settings as $name)
			if (!isset($this->settings[$name]))
				$this->settings[$name] = false;
	}

	/**
	 * Generic connection manager for sending data
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $data The encoded data to send, false for GET queries
	 * @param string $url The URL to connect to
	 * @return string Raw response
	 **/
	function send ($data=false,$url,$options=array()) {

		$defaults = array(
			'method' => 'POST',
			'timeout' => SHOPP_SHIPPING_TIMEOUT,
			'redirection' => 7,
			'httpversion' => '1.0',
			'user-agent' => SHOPP_GATEWAY_USERAGENT.'; '.get_bloginfo( 'url' ),
			'blocking' => true,
			'headers' => array(),
			'cookies' => array(),
			'body' => $data,
			'compress' => false,
			'decompress' => true,
			'sslverify' => false
		);
		$params = array_merge($defaults,$options);

		$connection = new WP_Http();
		$result = $connection->request($url,$params);

		if (is_wp_error($result)) {
			$errors = array(); foreach ($result->errors as $errname => $msgs) $errors[] = join(' ',$msgs);
			$errors = join(' ',$errors);
			new ShoppError(Lookup::errors('shipping','fail')." $errors ".Lookup::errors('contact','admin')." (WP_HTTP)",'shipping_comm_error',SHOPP_COMM_ERR);
			return false;
		} elseif (empty($result) || !isset($result['response'])) {
			new ShoppError(Lookup::errors('shipping','noresponse'),'shipping_comm_error',SHOPP_COMM_ERR);
			return false;
		} else extract($result);

		if (200 != $response['code']) {
			$error = Lookup::errors('shipping','http-'.$response['code']);
			if (empty($error)) $error = Lookup::errors('shipping','http-unkonwn');
			new ShoppError($error,'shipping_comm_error',SHOPP_COMM_ERR);
			return false;
		}

		return $body;
	}

	/**
	 * Helper to encode a data structure into a URL-compatible format
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $data Key/value pairs of data to encode
	 * @return string
	 **/
	function encode ($data) {
		$query = "";
		foreach($data as $key => $value) {
			if (is_array($value)) {
				foreach($value as $item) {
					if (strlen($query) > 0) $query .= "&";
					$query .= "$key=".urlencode($item);
				}
			} else {
				if (strlen($query) > 0) $query .= "&";
				$query .= "$key=".urlencode($value);
			}
		}
		return $query;
	}

	/**
	 * Identify the applicable column rate from the Order shipping information
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $rate The shipping rate to be used
	 * @return string The column index name
	 **/
	// @todo remove ShippingFramework::ratecolumn() in favor of tablerate()
	function ratecolumn ($rate) {

		return false; // @deprecated removing in favor of tablerate()
		$Order = &ShoppOrder();

		$Shipping = &$Order->Shipping;

		if ($Shipping->country == $this->base['country']) {
			// Use country/domestic region
			if (isset($rate[$this->base['country']]))
				$column = $this->base['country'];  // Use the country rate
			else $column = $Shipping->postarea(); // Try to get domestic regional rate
		} else if (isset($rate[$Shipping->region])) {
			// Global region rate
			$column = $Shipping->region;
		} else $column = 'Worldwide';

		return $column;
	}

	function tablerate ($table) {
		$Order = &ShoppOrder();

		$Address = &$Order->Shipping;
		$countries = Lookup::countries();
		$zones = Lookup::country_zones();

		$target = array('region'=>false,'country'=>false,'area'=>false,'zone'=>false,'postcode'=>false);

		// Prepare address for comparison
		$target['region'] = (int)$countries[$Address->country]['region'];
		$target['country'] = $Address->country;

		if (isset($Address->postcode) && !empty($Address->postcode)) {
			$target['postcode'] = $Address->postcode;
			$Address->postmap();
		}

		if (isset($Address->state) && !empty($Address->state)) {
			$target['zone'] = $Address->state;

			$areas = Lookup::country_areas();
			if (isset($areas[$Address->country]) && !empty($areas[$Address->country])) {
				$target['area'] = array();
				foreach ($areas[$Address->country] as $areaname => $areazones) {
					if (!in_array($Address->state,$areazones)) continue;
					$target['area'][] = $areaname;
				}
				rsort($target['area']);
				if (empty($target['area'])) $target['area'] = false;
			} else $target['area'] = $target['zone'];

		}

		// Sort table rules more specific to more generic matching
		usort($table,array('ShippingFramework','_sorttable'));

		// echo '<pre>';
		// Evaluate each destination rule
		foreach ($table as $index => $rate) {
			$r = floatvalue(isset($rate['rate'])?$rate['rate']:0);
			if (isset($rate['tiers'])) {
				$r = $rate['tiers'];
				usort($r,array('ShippingFramework','_sorttier'));
			}

			$dr = strpos($rate['destination'],',') !== false ? explode(',',$rate['destination']) : array($rate['destination']);
			$k = array_keys( array_slice($target, 0, count($dr) ) );

			$rule = array_combine($k,$dr);
			if (isset($rate['postcode']) && !empty($rate['postcode']) && $rate['postcode'] != '*')
				$rule['postcode'] = $rate['postcode'];
			$match = array_intersect_key($target,$rule);

			$d = array_diff($rule,$match);

			// @todo remove table rule matching debug
			// echo "***********************************\n";
			// echo "MATCH: \n"; print_r($match); echo "\n\n";
			// echo "RULE: \n"; print_r($rule); echo "\n\n";
			// echo "DIFF: \n"; print_r($d); echo "\n\n";

			// Use the rate if the destination rule is for anywhere
			if ($rule['region'] == '*') return $r;

			// Exact match FTW!
			if (empty($d)) return $r;

			// Handle special case for area matching
			if (!empty($d['area']) && is_array($match['area'])) {
				// Some countries can have multiple country areas
				// the target address can match on (most specific matches first)
				if (in_array($rule['area'],$match['area'])) unset($d['area']); // Clear excpetion to match
			}

			// Handle postcode matching
			if (!empty($d['postcode'])) {
				if (false !== strpos($rule['postcode'],','))
					$postcodes = explode(',',$rule['postcode']);
				else $postcodes = array($rule['postcode']);

				foreach ($postcodes as $coderule) {
					$coderule = trim($coderule);

					// Match numeric postcode ranges (only works for pure numeric postcodes like US zip codes)
					// Cannot be mixed with wildcard ranges (eg 55*-56* does not work, use 55000-56999)
					if (false !== strpos($coderule,'-')) {
						list($start,$end) = explode('-',$coderule);
						if ($match['postcode'] >= $start && $match['postcode'] <= $end)
							unset($d['postcode']); // Clear exception to match
						continue;
					}

					// Match wildcard postcode patterns
					if (strpos($coderule,'*') !== false) {
						$pattern = str_replace('*','(.+?)',$coderule);
						if (preg_match("/^$pattern$/i",$match['postcode']))
							unset($d['postcode']); // Clear exception for match
						continue;
					}

					// Exact match
					if ($coderule == $match['postcode']) {
						unset($d['postcode']);
						continue;
					}

				}

			}

			// If exceptions were cleared, return the matching rate
			if (empty($d)) return $r;

		}
		// echo '</pre>';

		// No matches found!?
		return false;
	}

	/**
	 * Calculates estimated delivery timeframes
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return string Delivery estimate string
	 **/
	function delivery ($data = array()) {
		$defaults = array( 'mindelivery' => '1w', 'maxdelivery' => '2w' );
		$data = array_merge( $defaults, $data );
		$cart = ShoppOrder()->Cart->processing;

		$min = shopp_setting('order_processing_min');
 		$max = shopp_setting('order_processing_max');

		$earliest = ShippingFramework::daytimes($min,$cart['min'],$data['mindelivery']);
		$latest = ShippingFramework::daytimes($max,$cart['max'],$data['maxdelivery']);

		return $earliest.'-'.$latest;
	}

	static function daytimes () {
		$args = func_get_args();
		$periods = array("h"=>3600,"d"=>86400,"w"=>604800,"m"=>2592000);

		$total = 0;
		foreach ($args as $timeframe) {
			if (empty($timeframe)) continue;
			list($i,$p) = sscanf($timeframe,'%d%s');
			$total += $i*$periods[$p];
		}
		return ceil($total/$periods['d']).'d';
	}

	static function _sorttable ($a, $b) {
		$c = array($a,$b);

		foreach ($c as $id => $r) {
			$i = strpos($r['destination'],',') !== false?count(explode(',',$r['destination'])):1;
			if (!empty($r['postcode']) && $r['postcode'] != '*')
				$i += strpos($r['postcode'],'*') !== false ? 5 : 6;
			$c[$id] = $i;
		}

		return ($c[0] < $c[1]);
	}

	static function _sorttier ($a, $b) {
		return floatvalue($a['threshold']) < floatvalue($b['threshold']) ? -1 : 1;
	}


	/**
	 * Generate the settings UI for the module
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $module The module class name
	 * @param string $name The formal name of the module
	 * @return void
	 **/
	function initui ($name) {
		$label = isset($this->settings['label'])?$this->settings['label']:$name;
		$this->ui = new ShippingSettingsUI($this,$name);
		$this->settings();
	}

	function uitemplate () {
		$this->ui->template();
	}

	function ui () {
		$editor = $this->ui->generate();
		foreach ($this->settings as $name => $value)
			$data['${'.$name.'}'] = $value;

		return str_replace(array_keys($data),$data,$editor);
	}

	function settings() { }

} // END class ShippingFramework

class ShippingSettingsUI extends ModuleSettingsUI {

	var $fieldname = 'settings';
	var $template = false;
	var $tables = false;
	var $type = '';
	var $unit = array();
	var $norates = false;

	function __construct ($Module,$name) {
		parent::__construct($Module,$name);

		$this->id = empty($Module->setting)?$this->module:$Module->setting;

		if ($this->label == $name) $this->label = __('Shipping Method','Shopp');
		if (method_exists($Module,'logo')) $this->label = 'data:image/png;base64,'.$Module->logo();
	}

	function settings () {
		$properties = array('module','type','unit','norates','threshold_class','rate_class');
		$settings = array();
		foreach ($properties as $prop)
			$settings[$prop] = $this->{$prop};

		return $settings;
	}

	function behaviors ($script) {
		shopp_custom_script('shiprates',$script);
	}

	function generate () {

		$logo = (strpos($this->label,'data:image') !== false);

		$_ = array();
		$_[] = '<tr><td colspan="5">';

		if ($logo) $_[] = '<style type="text/css">.shipper-logo { background: url('.$this->label.') no-repeat 10px 10px; text-indent: -9999em; height: 30px; }</style>';

		$_[] = '<table class="form-table shopp-settings"><tr>';
		$_[] = '<th scope="row" colspan="4" class="shipper-logo">'.$this->name.'<input type="hidden" name="module" value="'.$this->module.'" /><input type="hidden" name="id" value="'.$this->id.'" /></th>';
		$_[] = '</tr><tr>';

		if (!$logo) {
			$_[] = '<td>';
			$_[] = '<p><input type="text" name="'.$this->module.'[label]" value="'.$this->label.'" id="'.$this->id.'-label" size="30" class="selectall" /><br />';
			$_[] = '<label for="'.$this->id.'-label">'.__('Option Name','Shopp').'</label></input>';

			$_[] = '<p><select id="'.$this->id.'-delivery" name="'.$this->module.'[mindelivery]" class="mindelivery">${mindelivery_menu}</select> &mdash;';
			$_[] = '<select name="'.$this->module.'[maxdelivery]" class="maxdelivery">${maxdelivery_menu}</select><br />';
			$_[] = '<label for="'.$this->id.'-delivery">'.__('Estimated Delivery','Shopp').'</label></p>';

			$_[] = '<p><label for="'.$this->id.'-fallback" title="'.__('Enable this setting to offer it only when all real-time online rates fail.').'"><input type="hidden" name="'.$this->module.'[fallback]" value="off"><input id="'.$this->id.'-fallback" type="checkbox" name="'.$this->module.'[fallback]" value="on" ${fallbackon} class="fallback" />&nbsp;'.__('Real-time rates fallback','Shopp').'</label></p>';

			$_[] = '</td>';
		}

		foreach ($this->markup as $markup) {
			$_[] = '<td>';
			if (empty($markup)) $_[] = '&nbsp;';
			else $_[] = join('',$markup);
			$_[] = '</td>';
		}

		$_[] = '</tr><tr>';
		$_[] = '<td colspan="4">';
		$_[] = '<p class="textright">';
		$_[] = '<a href="${cancel_href}" class="button-secondary cancel alignleft">'.__('Cancel','Shopp').'</a>';
		if (!empty($this->type))
			$_[] = '<button type="submit" name="addrow" class="button-secondary addrate">'.__('Add Destination Rate','Shopp').'</button>';
		$_[] = '<input type="submit" name="save" value="'.__('Save Changes','Shopp').'" class="button-primary" /></p>';
		$_[] = '</td>';
		$_[] = '</tr></table>';
		$_[] = '</td></tr>';

		return join("",$_);

	}

	function template () {
		if ($this->tables) return; // Skip table-based UI standard templates (use TemplateShippingUI)
		$id = strtolower($this->id);
		$_ = array('<script id="'.$id.'-editor" type="text/x-jquery-tmpl">');
		$_[] = $this->generate();
		$_[] = '</script>';

		echo join("",$_)."\n\n";
	}

	function flatrates ($column=0,$attributes=array()) {
		$defaults = array(
			'name' => '',
			'classes' => '',
			'norates' => false,
			'table' => array()
		);
		$attributes = array_merge($defaults,$attributes);
		$attributes['id'] = "{$this->id}-{$attributes['name']}";
		extract($attributes);

		$this->type = 'flatrates';
		$this->tables = true;
		if ($norates) $this->norates = true;

		if (isset($_POST['addrow'])) {
			$row = (int)$_POST['addrow']+1;
			if ($row == count($table)) $table[] = array();
			else array_splice($table,$row,0,array());
		}

		if (isset($_POST['deleterow'])) {
			$row = (int)$_POST['deleterow'];
			if ($row !== 0) array_splice($table,$row,1);
		}

		$_ = array();
		$_[] = '<table class="rate-table-shipping '.$this->type.'">';
			$_[] = '<thead>';
				$_[] = '<tr>';
					$_[] = '<th class="textright" scope="col">'.__('Destination','Shopp').'</th>';
					$_[] = '<th scope="col">'.__('Postal Code','Shopp').'</th>';
					if (!$norates)
					$_[] = '<th class="num rate" scope="col">'.__('Rate','Shopp').'</th>';
					$_[] = '<th class="delete control" scope="col"><img src="'.SHOPP_ICONS_URI.'/clear.png" width="26" height="16" /></th>';
				$_[] = '</tr>';
			$_[] = '</thead>';
			$_[] = '<tbody>';

			if (!$this->template) {
				if (empty($table)) $_[] = $this->flatrate_row(0,array(),$norates);
				else {
					foreach ($table as $row => $setting)
						$_[] = $this->flatrate_row($row,$setting,$norates);
				}
			}

			$_[] = '</tbody>';
		$_[] = '</table>';

		$this->markup = array();
		$this->ui(join('',$_),$column);
	}

	function flatrate_row ($row=0,$setting=array(),$norates=false) {
		$defaults = array(
			'rate' => '${rate}',
		);
		$setting = array_merge($defaults,$setting);
		extract($setting);

		if ($this->template) $row = '${row}';

		$_ = array();
		$_[] = '<tr>';
		if (!$this->template)
			$_[] = $this->location_fields($row,$setting);
		if (!$norates)
			$_[] = '<td class="num rate"><input type="text" name="'.$this->module.'[table]['.$row.'][rate]" size="7" class="money selectall" value="'.$rate.'" /></td>';
		$_[] = '<td class="delete control"><button type="submit" name="deleterow" class="delete'.($row == 0?' hidden':'').'" value="'.$row.'"><img src="'.SHOPP_ICONS_URI.'/delete.png" width="16" height="16" /></button></td>';
		$_[] = '</tr>';

		return join('',$_);
	}

	function tablerates ($column=0,$attributes=array()) {
		$defaults = array(
			'class' => '',
			'threshold_class' => '',
			'rate_class' => '',
			'unit' => array(),
			'table' => array()
		);
		$attributes = array_merge($defaults,$attributes);
		$attributes['id'] = "{$this->id}-{$attributes['name']}";
		extract($attributes);

		$this->type = 'tablerates';
		$this->tables = true;
		if (!empty($unit)) $this->unit = $unit;
		if (!empty($threshold_class)) $this->threshold_class = $threshold_class;
		if (!empty($rate_class)) $this->rate_class  = $rate_class;

		if (isset($_POST['addrow'])) {
			$row = (int)$_POST['addrow']+1;
			if ($row == count($table)) $table[] = array();
			else array_splice($table,$row,0,array());
		}

		if (isset($_POST['deleterow'])) {
			$row = (int)$_POST['deleterow'];
			if ($row !== 0) array_splice($table,$row,1);
		}

		$_ = array();
		$_[] = '<table class="rate-table-shipping '.$this->type.'">';

		if (!$this->template) {
			if (empty($table)) $_[] = $this->tablerate_row(0,$attributes,array());
			else {
				usort($table,array('ShippingFramework','_sorttable'));
				foreach ($table as $row => $setting) {
					if ( isset($setting['tiers']) ) usort($setting['tiers'],array('ShippingFramework','_sorttier'));
					$_[] = $this->tablerate_row($row,$attributes,$setting);
				}
			}

		}
		$_[] = '</table>';

		$this->markup = array();
		$this->ui(join('',$_),$column);
	}

	function tablerate_row ($row=0,$attrs,$table) {
		$unit = $attrs['unit'];

		// Handle adding rate tiers
		if (isset($_POST['addtier'])) {
			list($inrow,$tier) = explode(',',$_POST['addtier']);
			if ($row == $inrow) {
				$tier++;

				// Stats to guess next numbers
				if (isset($table['tiers']) && !empty($table['tiers'])) {
					$max = $mean = $sum = $deltas = $avedev = array('t' => 0, 'r' => 0);
					$c = 0;
					foreach ($table['tiers'] as $index => $t) {
						if ($t['threshold'] == 0) continue;
						$sum['t'] += $t['threshold'];
						$sum['r'] += floatvalue($t['rate']);
						$c++;

						$max['t'] = max($max['t'],$t['threshold']);
						$max['r'] = max($max['r'],floatvalue($t['rate']));
						if ($index+1 == $tier) break;
					}
					$mean['t'] = $sum['t']/$c;
					$mean['r'] = $sum['r']/$c;

					foreach ($table['tiers'] as $index => $t) {
						if ($t['threshold'] == 0) continue;
						$deltas['t'] += abs($t['threshold']-$mean['t']);
						$deltas['r'] += abs(floatvalue($t['rate'])-$mean['r']);
						if ($index+1 == $tier) break;
					}
					$avedev['t'] = max(round($deltas['t']/$c,0),1);
					$avedev['r'] = max(round($deltas['r']/$c,5),1);

				}

				$newtier = array('threshold' => $max['t']+$avedev['t'],'rate'=>$max['r']+$avedev['r']);

				if ($tier == $c) $table['tiers'][] = $newtier;
				else array_splice($table['tiers'],$tier,0,array($newtier));
			}
		}

		if ($this->template) {
			$unit = array('${unit}','${unitabbr}');
		}

		// Handle deleting a rate tier
		if (isset($_POST['deletetier'])) {
			list($inrow,$tier) = explode(',',$_POST['deletetier']);
			if ($row == $inrow && $tier !== 0) array_splice($table['tiers'],$tier,1);
		}

		$_ = array();
		$_[] = '<thead>';
		$_[] = '<tr>';
			$_[] = '<th scope="col">'.__('Destination','Shopp').'</th>';
			$_[] = '<th scope="col">'.__('Postal Code','Shopp').'</th>';
			$_[] = '<th scope="col">'.sprintf(__('Rates by %s','Shopp'),"{$unit[0]}".((isset($unit[1]) && !empty($unit[1]))?" ({$unit[1]})":'') ).'</th>';
			$_[] = '<th class="delete control" scope="col"><img src="'.SHOPP_ICONS_URI.'/clear.png" width="26" height="16" /></th>';
		$_[] = '</tr>';
		$_[] = '</thead>';
		$_[] = '<tbody>';
		$_[] = '<tr>';
		if (!$this->template)
			$_[] = $this->location_fields($row,$setting);
			$_[] = '<td>';
				$_[] = '<table class="panel">';

				if (!$this->template) {
					if (empty($table) || empty($table['tiers'])) $_[] = $this->tablerate_row_tier($row,0,$attrs);
					else {
						foreach ($table['tiers'] as $tier => $setting)
							$_[] = $this->tablerate_row_tier($row,$tier,$attrs,$setting);
					}
				}

				$_[] = '</table>';

			$_[] = '</td>';
			$_[] = '<td class="delete control">';
			$_[] = '<button type="submit" name="deleterow" class="delete'.($row == 0?' hidden':'').'" value="'.$row.'"><img src="'.SHOPP_ICONS_URI.'/delete.png" width="16" height="16" /></button>';
			$_[] = '</td>';
		$_[] = '</tr>';
		$_[] = '</tbody>';

		return join('',$_);
	}

	function tablerate_row_tier ($row=0,$tier=0,$attrs,$setting=array()) {
		$unit = isset($attrs['unit'][1])?$attrs['unit'][1]:false;
		$threshold_class = !empty($attrs['threshold_class'])?$attrs['threshold_class']:'';
		$rate_class = !empty($attrs['rate_class'])?$attrs['rate_class']:'money';
		$defaults = array('threshold' => 0,'rate' => '1.00');
		$setting = array_merge($defaults,$setting);

		if ($this->template) {
			$row = '${row}';
			$tier = '${tier}';
			$unit = '${unitabbr}';
			$setting['rate'] = '${rate}';
			$setting['threshold'] = '${threshold}';
			$rate_class = '${rate_class}';
			$threshold_class = '${threshold_class}';
		}

		$_ = array();
		$_[] = '<tr>';
			$_[] = '<td class="control"><button type="submit" name="deletetier" class="delete'.($tier == 0?' hidden':'').'" value="'.("$row,$tier").'"><img src="'.SHOPP_ICONS_URI.'/delete.png" width="16" height="16" /></button></td>';
			$_[] = '<td class="unit leftfield"><label><input type="text" name="'.$this->module.'[table]['.$row.'][tiers]['.$tier.'][threshold]" size="7" value="'.$setting['threshold'].'" class="threshold selectall '.$threshold_class.'" /> '.$unit.' '.__('and above','Shopp').'</label></td>';
			$_[] = '<td class="rate rightfield"><input type="text" name="'.$this->module.'[table]['.$row.'][tiers]['.$tier.'][rate]" size="7" class="rate selectall '.$rate_class.'" value="'.$setting['rate'].'" /></td>';
			$_[] = '<td class="control"><button type="submit" name="addtier" value="'."$row,$tier".'" class="add"><img src="'.SHOPP_ICONS_URI.'/add.png" width="16" height="16" /></button></td>';
		$_[] = '</tr>';

		return join('',$_);
	}

	function parse_location ($destination) {
		$selected = array(
			'region' => '*',
			'country' => '',
			'area' => '',
			'zone' => ''
		);
		$selection = array();

		if (strpos($destination,',') !== false)
			$selection = explode(',',$destination);
		else $selection = array($destination);

		if (!is_array($selection)) $selection = array($selection);
		$keys = array_slice(array_keys($selected),0,count($selection));
		$selected = array_merge( $selected,array_combine($keys,$selection) );
		extract($selected);

		foreach ($selected as $name => &$value) {
			if ($value == '') continue;

			switch ($name) {
				case 'region':
					if ('*' == $value) $value = __('Worldwide','Shopp');
					else {
						$regions = Lookup::regions();
						if (isset($regions[ $value ])) $value = $regions[ $value ];
					}
					break;
				case 'country':
					$countries = Lookup::countries();
					$selected['countrycode'] = $value;
					if (isset($countries[ $value ])) $value = $countries[ $value ]['name'];
					break;
				case 'zone':
					$zones = Lookup::country_zones();
					if (isset($zones[ $country ])) $zones = $zones[ $country ];
					if (isset($zones[ $value ])) $value = $zones[ $value ];
					break;
			}

		}


		return $selected;
	}

	function location_menu ($destination = false,$module=false) {
		if (!$module) $this->module;
		$menuarrow = ' &#x25be;';
		$tab = str_repeat('&sdot;',3).'&nbsp;';
		$regions = Lookup::regions();
		$countries = Lookup::countries();
		$regional_countries = array();
		$country_areas = array();
		$country_zones = array();
		$postcode = false;
		$subregions = isset($_POST[$module]['table'][$row]['subregions']);
		$selection = array();

		$selected = array(
			'region' => '*',
			'country' => '',
			'area' => '',
			'zone' => ''
		);

		if (strpos($destination,',') !== false)
			$selection = explode(',',$destination);
		else $selection = array($destination);

		if ($subregions && isset($_POST[$module]['table'][$row]['destination']))
			$selection = explode(',',$_POST[$module]['table'][$row]['destination']);

		if (!is_array($selection)) $selection = array($selection);
		$keys = array_slice(array_keys($selected),0,count($selection));
		$selected = array_merge( $selected,array_combine($keys,$selection) );

		$regional_countries = array_filter($countries,create_function('$c','return (\''.($selected['region']).'\' === (string)$c[\'region\']);'));

		if (!empty($selected['country'])) {
			$ca = Lookup::country_areas();
			if (isset($ca[$selected['country']])) $country_areas = $ca[$selected['country']];

			$cz = Lookup::country_zones();
			if (isset($cz[$selected['country']])) $country_zones = $cz[$selected['country']];

		}

		$options = array('*' => __('Anywhere','Shopp'));
		foreach ($regions as $index => $region) {

			if ($index == $selected['region'] && !empty($regional_countries) && ($subregions || !empty($selected['country'])) ) {
				$options[$index] = $region.$menuarrow;
				foreach ($regional_countries as $country => $country_data) {
					$country_name = $country_data['name'];

					if ($country == $selected['country']) {
						$postcodes = Lookup::postcodes();
						$postcode = (isset($postcodes[ $selected['country'] ]));

						if (!empty($country_areas) && ($subregions || !empty($selected['area'])) ) {
							$options["$index,$country"] = $country_name.$menuarrow;
							$areas = array_keys($country_areas);

							foreach ($areas as $area => $area_name) {

								if ((string)$area == (string)$selected['area']) {
									$zones = array_flip($country_areas[$area_name]);
									$zones = array_intersect_key($country_zones,$zones);

									$group_name = $area_name.$menuarrow;

									$options[$group_name] = array(); // Setup option group for area zones
									if (empty($selected['zone'])) $selected['zone'] = key($zones);

									foreach ($zones as $zone => $zone_name) {
										$options[$group_name]["$index,$country,$area,$zone"] = $zone_name.', '.substr($country,0,2);
									} // end foreach($country_zones)

								} // end if ($selected['area'])
								else $options["$index,$country,$area"] = str_repeat('&nbsp;',2).$area_name;

							} // end foreach($areas)
						} elseif (!empty($country_zones) && ($subregions || !empty($selected['area'])) ) {
							$options[$country_name] = array();
							if (empty($selected['area'])) $selected['area'] = key($country_zones);

							foreach ($country_zones as $zone => $zone_name) {
								$options[$country_name]["$index,$country,$zone"] = $zone_name.', '.substr($country,0,2);
							} // end foreach($country_zones)
						} // end if ($country_zones)
						else $options["$index,$country"] = $country_name;

					} // end if ($selected['country'])
					else $options["$index,$country"] = $tab.$country_name;

				} // end foreach ($regional_countries)

			} // end if ($selected['region'])
			else $options[$index] = $region;

		} // end foreach ($regions)

		$selected = array_filter($selected, create_function('$i','return (\'\' != $i);'));
		$selection = join( ',', $selected );

		return array('options' => $options, 'selection' => $selection,'postcode' => $postcode);

	}

	function location_fields ($row=0,$setting=array()) {

		$menuarrow = ' &#x25be;';
		$destination = isset($setting['destination'])?$setting['destination']:'';

		$menu = $this->location_menu($destination);
		extract($menu);
		if ($this->template) {
			$row = '${row}';
			$setting['postcode'] = '${postcode}';
		}

		$_ = array();
		$_[] = '<td class="unit textright">';
		$_[] = '<select name="'.$this->module.'[table]['.$row.'][destination]" class="drilldown">';
		$_[] = menuoptions($options,$selection,true);
		$_[] = '</select>';
		$_[] = '<button type="submit" name="'.$this->module.'[table]['.$row.'][subregions]" value="+" class="button-secondary hide-if-js" title="'.__('Click to load sub-regions of the selected region...','Shopp').'"><small>'.trim($menuarrow).'</small></button>';
		$_[] = '</td>';
		if (empty($setting['postcode'])) $setting['postcode'] = '*'; $disabled = !$postcode?' disabled="disabled"':'';
		$_[] = '<td><input type="text" name="'.$this->module.'[table]['.$row.'][postcode]" value="'.$setting['postcode'].'" size="10"'.$disabled.' class="postcode" /></td>';

		return join('',$_);

	}

}

class TemplateShippingUI extends ShippingSettingsUI {

	function __construct() {
		parent::__construct(false,false);

		$this->template = true;

		$this->name = '${name}';
		$this->module = '${module}';
		$this->id = '${id}';
		$this->label = '${label}';

		$this->templates();
	}

	function templates () {
		$callbacks = array('location','flatrates','flatrate_row','tablerates','tablerate_row','tablerate_row_tier');
		foreach ($callbacks as $callback) add_action('shopp_shipping_module_settings',array($this,$callback));
	}

	function template ($id) {
		$_ = array('<script id="'.$id.'" type="text/x-jquery-tmpl">');
		$_[] = $this->generate();
		$_[] = '</script>';
		echo join("",$_)."\n\n";
	}

	function widget ($id,$markup) {
		$_ = array('<script id="'.$id.'" type="text/x-jquery-tmpl">');
		$_[] = $markup;
		$_[] = '</script>';
		echo join("",$_)."\n\n";
	}

	function location () {
		$markup = parent::location_fields();
		$this->widget('location-fields',$markup);
	}

	function flatrates () {
		parent::flatrates();
		$this->template('flatrates-editor');
	}

	function flatrate_row () {
		$markup = parent::flatrate_row();
		$this->widget('flatrate-row',$markup);
	}

	function tablerates () {
		parent::tablerates();
		$this->template('tablerates-editor');
	}

	function tablerate_row () {
		$markup = parent::tablerate_row(0,array(),array());
		$this->widget('tablerate-row',$markup);
	}

	function tablerate_row_tier () {
		$markup = parent::tablerate_row_tier(0,array(),array());
		$this->widget('tablerate-row-tier',$markup);
	}

}


interface ShippingPackagingInterface {
	/**
	 * adds item to current package
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param Item $Item the Item to add to packages
	 **/
	public function add_item ( Item &$Item );

	/**
	 * packages is the packages container iterator
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @return true while more packages
	 **/
	public function packages ();

	/**
	 * return current package
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @return Package current package, false if no packages
	 **/
	public function package ();

	/**
	 * return current package count
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @return int count of packages
	 **/
	public function count ();

}

/**
*
* Packaging Class
*
* Default packaging types
* package by weight/mass
* package like items together
* package each piece
* package all together
*
*/
class ShippingPackager implements ShippingPackagingInterface {

	/**
	 * The shipping module's identifier
	 *
	 * @since 1.2
	 * @access public
	 * @var string
	 */
	public $module = '';

	/**
	 * List of built-in packaging types.
	 *
	 * @since 1.2
	 * @access protected
	 * @var array
	 */
	protected $types;

	/**
	 * The default built-in packaging behavior (one of built-in types)
	 *
	 * @since 1.2
	 * @access protected
	 * @var string
	 */
	protected $pack = 'like'; // default packing behavior

	protected $packages = array();

	function __construct( $options = array(), $module = false ) {
		$this->types = array_keys( Lookup::packaging_types() );

		if ( $module !== false ) $this->module = $module;

		$this->options = apply_filters( 'shopp_packager_options', $options, $module );

		// Set weight limit
		$weight_limit = shopp_setting('shipping_package_weight_limit');
		$defaults = array(
			'wtl'=> ( $weight_limit ? $weight_limit : 50 ),
			'wl'=>-1,
			'hl'=>-1,
			'll'=>-1
		);
		if ( ! isset($this->options['limits']) ) $this->options['limits'] = array();
		$this->options['limits'] = array_merge($defaults, $this->options['limits']);

		// set packing behavior
		$this->pack = apply_filters( 'shopp_packager_type',
			( isset( $options['type'] ) && in_array($options['type'], $this->types) ? $options['type'] : $this->pack ),
			$module );

		// register packagers
		foreach ( $this->types as $pack ) add_action('shopp_packager_add_'.$pack, array(&$this, $pack.'_add'), 10, 2);
	}

	/**
	 * packager add item
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param Item $item the item to add to packages
	 **/
	public function add_item ( Item &$Item ) {
		if ( str_true($Item->packaging) )
			do_action_ref_array('shopp_packager_add_piece', array(&$Item, &$this) );
		else do_action_ref_array('shopp_packager_add_'.$this->pack, array(&$Item, &$this) );
	}


	/**
	 * packages() is the packages container iterator
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @return true while more packages
	 **/
	public function packages () {
		if (!$this->packages) return false;
		if (!isset($this->_loop)) {
			reset($this->packages);
			$this->_loop = true;
		} else next($this->packages);

		if ( false !== current($this->packages) ) return true;
		else {
			unset($this->_loop);
			return false;
		}
		break;
	}

	/**
	 * count() returns the package count
	 *
	 * @author John Dillick
	 * @since 1,2
	 *
	 * @return int number of packages constructed
	 **/
	public function count () {
		return count($this->packages);
	}


	/**
	 * Uses the packages iterator, returns current package
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @return Package current package, false if no packages
	 * @param string id (Optional) - manifest id package
	 *
	 **/
	public function package ( $id = false ) {
		if ( false !== $id && isset( $this->packages[$id] ) ) return $this->packages[$id];
		if ( ! $this->packages || ! isset($this->_loop) ) return false;
		return current( $this->packages );
	}

	/**
	 * mass_add used to add new Item in package by mass
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param array $p packages
	 * @param Item $Item the Item to add
	 **/
	public function mass_add ( &$Item, $pkgr ) {
		if ( $pkgr->module != $this->module ) return; // not mine

		$this->all_add($Item, $this, 'mass');
	}

	/**
	 * like_add adds Item to package if a like Item
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param Item $Item Item to add
	 **/
	public function like_add ( &$Item, $pkgr ) {
		if ( $pkgr->module != $this->module ) return; // not mine

		$limits = array();
		$defaults = array('wtl'=>-1,'wl'=>-1,'hl'=>-1,'ll'=>-1);
		extract($this->options);
		$limits = array_merge($defaults,$limits);
		$label = apply_filters( 'shopp_package_item_label', ! empty($Item->sku) ? $Item->sku : "{$Item->product}-{$Item->priceline}", $Item );

		// Base Case Test: Item will never fit in package
		$package = new ShippingPackage(true, $limits);

		// Check for full or partial fit on new package
		$fit = $package->limits($Item);

		// No fit possible with these limits; force separate packaging on item.
		if ( ! $fit ) {
			unset($package); // useless package, toss out
			$this->piece_add($Item, $this); // package alone
			return;
		}

		// Package Initialization
		if ( empty($this->packages) ) {
			$this->packages[] = $package;
		}

		// General Case Test: We have an empty package, or one with like items.
		foreach ( $this->packages as $i => $pkg ) {
			if ( $pkg->contents() && ! in_array( $label, array_keys( $pkg->contents() ) ) ) {
				continue; // the package isn't empty and isn't like this item
			}

			// Attempt add
			$Result = $pkg->add($Item);
			if ( ! $Result ) {
				continue; // No fit; keep looking.
			}

			if ( true === $Result ) {
				return; // Full fit; done.
			}

			if ( is_a( $Result, 'Item') ) {
				$this->like_add($Result, $this); // Partial fit; recurse to fit remainder.
				return;
			}
		}

		// Default Case: All packages too full; Create new and recurse.
		$this->packages[] = new ShippingPackage(true, $limits);
		$this->like_add($Item, $this);
	}

	/**
	 * piece_add used to add new Item in piece mail packaging
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param Item $Item Item to add
	 * @return void Description...
	 **/
	public function piece_add ( &$Item, $pkgr ) {
		if ( $pkgr->module != $this->module ) return; // not mine

		$count = $Item->quantity;

		$piece = clone $Item;
		$piece->quantity = 1;

		for ( $i=0; $i < $count; $i++ ) {
			$this->packages[] = $package = new ShippingPackage(true); // no limits on individual add
			$package->add($piece);
			$package->set_full(true);
			unset( $piece->pos );
		}
	}

	/**
	 * all_add used to add all Items to one package
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @param Item $Item Item to add
	 * @param string $type expect dimensions, or just mass
	 * @return void
	 **/
	public function all_add ( &$Item, $pkgr, $type='dims' ) {
		if ( $pkgr->module != $this->module ) return; // not mine

		$defaults = array('wtl'=>-1,'wl'=>-1,'hl'=>-1,'ll'=>-1);
		$limits = array_merge($defaults, ( isset($this->options['limits']) ? $this->options['limits'] : array() ) );

		// Base Case Test: The item will never fit
		$package = new ShippingPackage( ('dims' == $type), $limits );
		$fit = $package->limits( $Item );

		// No fit possible with these limits; force separate packaging on item.
		if ( ! $fit ) {
			unset($package);
			$this->piece_add($Item, $this); // package alone
			return;
		}

		// Package Initialization
		if ( empty($this->packages) ) {
			$this->packages[] = $package;
		}

		// General Case Test: We have a package that the Item will fit in.
		foreach( $this->packages as $i => $pkg ) {
			$Result = $pkg->add($Item);
			if ( ! $Result ) {
				continue; // try next
			}

			if ( true === $Result ) {
				return; // found full fit
			}

			if ( is_a($Result, 'Item') ) {
				// recurse to place remainder
				$this->all_add($Result, $this, $type);
				return;
			}

		}

		// Default Case: All packages too full; Create new and recurse.
		$this->packages[] = new ShippingPackage( ('dims' == $type), $limits );
		$this->all_add($Item, $this, $type);
	}

} // end class ShippingPackager

interface ShippingPackageInterface {
	/**
	 * the package weight
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @returns the total weight of the package
	 **/
	public function weight();

	/**
	 * the package width
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @returns the width of the package
	 **/
	public function width();

	/**
	 * the package height
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @returns the height of the package
	 **/
	public function height();

	/**
	 * the package length
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @returns the length of the package
	 **/
	public function length();

	/**
	 * the package monetary value
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @returns the value of the package
	 **/
	public function value();

	/**
	 * the package contents
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @returns array of Items in the package
	 **/
	public function contents();

}

class ShippingPackage implements ShippingPackageInterface {

	protected $wt = 0; //current weight

	protected $w = 0;  //current width

	protected $h = 0;  //current height

	protected $l = 0;  //current length

	protected $val = 0; // estimated value of package contents

	protected $dims = false; // package has dimensions?

	protected $boxtype = 'custom';

	// limits for this package
	protected $limits = array (
		'wtl' => -1, // no weight limit
		'wl'  => -1, // width limit
		'hl'  => -1, // height limit
		'll'  => -1 // lenght limit
	);

	protected $full = false; // accepting Items

	protected $contents = array(); // Item array

	function __construct( $dims = false, $limits = array( 'wtl' => -1, 'wl' => -1, 'hl' => -1, 'll' => -1 ), $boxtype = 'custom' ) {
		$this->dims = $dims;
		$this->limits = array_merge($this->limits, $limits);

		$this->boxtype = $boxtype;
		$this->date = current_time('timestamp');
	}

	public function set_limits ( $limits = array ( 'wtl' => -1, 'wl'  => -1, 'hl'  => -1, 'll'  => -1 ) ) {
		$this->limits = $limits;
	}

	public function set_full ( $full ) {
		if ( isset( $full ) ) $this->full = ($full);
	}

	/**
	*
	* add() adds item to current package if it fits
	*
	* @since 1.2
	* @return mixed true if the item was added to the package, false if no quantity will fit, Item remainder if partial add.
	* @param Item $Item - Item object being added
	*
	**/
	public function add( &$Item ) {
		$total = $Item->quantity;

		if ( $fit = $this->limits( $Item ) ) { // within limits
			// partial fit
			if ( $fit < $total ) {
				$Remainder = clone $Item;
				$Remainder->quantity = $total - $fit;
				unset($Remainder->pos, $Remainder->orient);
				$Item->quantity = $fit;
			}

			$label = apply_filters( 'shopp_package_item_label', ! empty($Item->sku) ? $Item->sku : "{$Item->product}-{$Item->priceline}", $Item );
			if ( ! empty( $this->contents[$label] ) )
				$this->contents[$label]->quantity += $Item->quantity;
			else $this->contents[$label] = $Item;
			$this->wt += $Item->weight * $Item->quantity;
			$this->val += $Item->unitprice * $Item->quantity;
			if ( $this->dims ) {
				$this->w = max( $this->w, $Item->orient['x'] );
				$this->l = max( $this->l, $Item->orient['y'] );
				$this->h = $this->h + $Item->orient['z'] * $Item->quantity;
			}
		}

		// didn't fit, return false
		if ( ! $fit ) return false;

		// fit perfectly, return true
		if ( $fit == $total ) return true;

		// partial fit
		return $Remainder;
	}

	/**
	*
	* limits() determines if an item will fit in the current package
	*
	* @since 1.2
	* @return int quantity that fits
	* @param Item $Item - Item object being added
	*
	**/
	public function limits( &$Item ) {
		if ( $this->is_full() ) return apply_filters( 'shopp_package_limit', false, $Item, $this->contents, $this->limits ); // full

		$wtl = $wl = $hl = $ll = -1;
		extract($this->limits);

		if ( -3 == array_sum(array($wl,$hl,$ll)) ) {
			// use default orientation when no dimension limits
			$this->orient($Item);
			unset($Item->pos);

			if ( -1 == $wtl ) return $Item->quantity; // no limits
		}

		$underlimit = true;				// bool package is under limit
		$fits = 0;						// number of items known to fit in current package
		$quantity = $Item->quantity;	// total quantity to attempt
		$orient = array();				// current fitting orientation

		// check for maximum quantity that will fit in this package
		for ( $qt = 1 ; $qt <= $quantity ; $qt++ ) {

			// Check if the dimensions will fit
			if ( $this->dims && $wl > 0 && $hl > 0 && $ll > 0 ) {
				$orienting = $this->orient($Item);
				// unable to complete fitting processing, missing item dims
				if ( ! $orienting ) $underlimit = false;

				$foundfit = false;
				while ( $orienting ) {

					// based on quantity and orientation, is it underlimit?
					$foundfit = (
						$wl >= max( $this->w, $Item->orient['x'] ) &&
						$ll >= max( $this->l, $Item->orient['y'] ) &&
						$hl >= ( $this->h + $Item->orient['z'] * $qt )
					);

					// if we find a successful orientation at this qty, preserve
					// reset orientation loop for next qty
					if ( $foundfit ) {
						unset($Item->pos);
						$orient = array_merge($orient, $Item->orient);

						break; // break while
					}

					$orienting = $this->orient($Item);
				}

				// if we found a fit, we are still underlimit
				$underlimit = $foundfit;
			}

			// Check if the weight will fit
			if ( $wtl > 0 ) {
				$underlimit = $underlimit && ( $wtl >= ( $this->wt + $Item->weight * $qt ) );
			}

			// current quantity fits
			if ( $underlimit ) {
				$fits = $qt;
				continue;
			}
			break;
		}

		// set the resulting Item orientation
		if ( $fits && ! empty($orient) ) {
			$Item->orient = $orient;
		}

		return apply_filters( 'shopp_package_limit', $fits, $Item, $this->contents, $this->limits ); // stub, always fits
	}

	/**
	 * orient - flips item orientation
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @return bool true if flipped, false if not flipped
	 **/
	public function orient( &$Item ) {
		if ( ! $Item->width || ! $Item->length || ! $Item->height ) return false;

		// setup
		if ( ! isset($Item->pos) ) {
			$Item->pos = array('start','x', 'y', 'z');

			$Item->orient = array( 'x' => $Item->width, 'y' => $Item->length, 'z' => $Item->height );
			reset($Item->pos);
			return true;
		}

		$pos = next( $Item->pos );

		// rotate about x
		if ( 'x' == $pos ) {
			list ( $Item->orient['x'], $Item->orient['y'], $Item->orient['z'] ) = array( $Item->width, $Item->length, $Item->height );
			return true;
		}

		// rotate about y
		if ( 'y' == $pos ) {
			list ( $Item->orient['x'], $Item->orient['y'], $Item->orient['z'] ) = array( $Item->length, $Item->height, $Item->width );
			return true;
		}
		// rotate about z
		if ( 'z' == $pos ) {
			list ( $Item->orient['x'], $Item->orient['y'], $Item->orient['z'] ) = array( $Item->length, $Item->width, $Item->height );
			return true;
		}

		// finished looping, reset
		unset($Item->pos);
		return false;
	}

	/**
	 * is_full
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @return bool true if the package has been marked full or if the weight or height limits have been met (or exceeded), else false
	 **/
	public function is_full () { return $this->full; }

	/**
	*
	* weight() returns the current package weight
	* @since 1.2
	* @return float weight of package, in system units
	* @param none
	*
	**/
	public function weight() { return $this->wt; }

	/**
	*
	* width() returns the current package width
	* @since 1.2
	* @return float width of package, in system units
	* @param none
	*
	**/
	public function width() { return $this->w; }

	/**
	*
	* height() returns the current package height
	* @since 1.2
	* @return float height of package, in system units
	* @param none
	*
	**/
	public function height() { return $this->h; }

	/**
	*
	* length() returns the current package length
	* @since 1.2
	* @return float length of package, in system units
	* @param none
	*
	**/
	public function length() { return $this->l; }

	/**
	*
	* value() returns the current package value
	* @since 1.2
	* @return float value of package, in base currency
	* @param none
	*
	**/
	public function value() { return ceil( $this->val ); }

	/**
	*
	* value() returns the current package contents
	* @since 1.2
	* @return array contents of package
	* @param none
	*
	**/
	public function contents() { return $this->contents; }

} // end class ShippingPackage

/**
 * ShippingCarrier class
 *
 * Implements structured payment card (credit card) behaviors including
 * card number validation and extra security field requirements.
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage gateways
 **/
class ShippingCarrier extends AutoObjectFramework {

	var $name;			// Display name
	var $weburl;		// Website URL
	var $areas;			// Areas serviced (where shipments can be sent from, use * for worldwide or comma-separated country codes)
	var $trackurl;		// Tracking Query URL (use %s as token for tracking number replacement)
	var $trackpattern;	// Regular expression pattern for carrier tracking numbers
}

?>