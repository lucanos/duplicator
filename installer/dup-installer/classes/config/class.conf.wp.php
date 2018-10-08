<?php
defined("ABSPATH") or die("");

/**
 * Class used to update and edit web server configuration files
 * for both Apache and IIS files .htaccess and web.config
 *
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\WPConfig
 *
 */
class DUPX_WPConfig
{
	/**
	 * Updates the standard WordPress config file settings
	 *
	 * @return null
	 */
	public static function updateVars(&$patterns, &$replace)
	{
		$wpconfig_arkpath	= "{$GLOBALS['DUPX_ROOT']}/dup-wp-config-arc__{$GLOBALS['DUPX_AC']->package_hash}.txt";
		$wpconfig		= @file_get_contents($wpconfig_arkpath, true);

		$db_port    = is_int($_POST['dbport']) ? DUPX_U::sanitize_text_field($_POST['dbport']) : 3306;
		$db_host	= ($db_port == 3306) ? DUPX_U::sanitize_text_field($_POST['dbhost']) : DUPX_U::sanitize_text_field($_POST['dbhost']).':'.DUPX_U::sanitize_text_field($db_port);
		$db_name	= isset($_POST['dbname']) ? DUPX_U::sanitize_text_field($_POST['dbname']) : null;
		$db_user	= isset($_POST['dbuser']) ? DUPX_U::sanitize_text_field($_POST['dbuser']) : null;
       	$db_pass	= isset($_POST['dbpass']) ? DUPX_U::sanitize_text_field($_POST['dbpass']) : null;

		//--------------------
		//LEGACY PARSER LOGIC:
		$patterns = array_merge($patterns, array(
			"/'DB_NAME',\s*'.*?'/",
			"/'DB_USER',\s*'.*?'/",
			"/'DB_PASSWORD',\s*'.*?'/",
			"/'DB_HOST',\s*'.*?'/"));

		$replace = array_merge($replace, array(
			"'DB_NAME', "		. "'{$db_name}'",
			"'DB_USER', "		. "'{$db_user}'",
			"'DB_PASSWORD', "	. "'{$db_pass}'",
			"'DB_HOST', "		. "'{$db_host}'"
		));

		//SSL CHECKS
		if ($_POST['ssl_admin']) {
			if (!strstr($wpconfig, 'FORCE_SSL_ADMIN')) {
				$wpconfig = $wpconfig . PHP_EOL . "define('FORCE_SSL_ADMIN', true);";
			}
		} else {
			array_push($patterns, "/'FORCE_SSL_ADMIN',\s*true/");
			array_push($replace, "'FORCE_SSL_ADMIN', false");
		}

		//CACHE CHECKS
		if ($_POST['cache_wp']) {
			if (!strstr($wpconfig, 'WP_CACHE')) {
				$wpconfig = $wpconfig . PHP_EOL . "define('WP_CACHE', true);";
			}
		} else {
			array_push($patterns, "/'WP_CACHE',\s*true/");
			array_push($replace, "'WP_CACHE', false");
		}
		if (!$_POST['cache_path']) {
			array_push($patterns, "/'WPCACHEHOME',\s*'.*?'/");
			array_push($replace, "'WPCACHEHOME', ''");
		}

		//--------------------
		//NEW TOKEN PARSER LOGIC:
		//$count checks for dynamic variable types such as:  define('WP_TEMP_DIR',	'D:/' . $var . 'somepath/');
		//which should not be updated.  Goal is to evenaly move all var checks into tokenParser
		$defines  = self::parseDefines($wpconfig_arkpath);

		//WP_CONTENT_DIR
		if (isset($defines['WP_CONTENT_DIR'])) {
			$new_path = str_replace($_POST['path_old'], $_POST['path_new'], DUPX_U::setSafePath($defines['WP_CONTENT_DIR']), $count);
			if ($count > 0) {
				array_push($patterns, "/('|\")WP_CONTENT_DIR.*?\)\s*;/");
				array_push($replace, "'WP_CONTENT_DIR', '{$new_path}');");
			}
		}

		//WP_CONTENT_URL
		// '/' added to prevent word boundary with domains that have the same root path
		if (isset($defines['WP_CONTENT_URL'])) {
			$new_path = str_replace($_POST['url_old'] . '/', $_POST['url_new'] . '/', $defines['WP_CONTENT_URL'], $count);
			if ($count > 0) {
				array_push($patterns, "/('|\")WP_CONTENT_URL.*?\)\s*;/");
				array_push($replace, "'WP_CONTENT_URL', '{$new_path}');");
			}
		}

		//WP_TEMP_DIR
		if (isset($defines['WP_TEMP_DIR'])) {
			$new_path = str_replace($_POST['path_old'], $_POST['path_new'], DUPX_U::setSafePath($defines['WP_TEMP_DIR']) , $count);
			if ($count > 0) {
				array_push($patterns, "/('|\")WP_TEMP_DIR.*?\)\s*;/");
				array_push($replace, "'WP_TEMP_DIR', '{$new_path}');");
			}
		}
	}

	/**
	 * Used to parse the wp-config PHP statements
	 *
	 * @param string	$wpconfigPath The full path to the wp-config.php file
	 *
	 * @return array	Returns and array of defines with the names
	 *					as the key and the value as the value.
	 */
	public static function parseDefines($wpconfigPath)
	{
		$defines = array();
		$wpconfig_file = @file_get_contents($wpconfigPath);
		
		if (!function_exists('token_get_all')) {
			DUPX_Log::info("\nNOTICE: PHP function 'token_get_all' does not exist so skipping WP_CONTENT_DIR and WP_CONTENT_URL processing.");
			return $defines;
		}

		if ($wpconfig_file === false) {
			return $defines;
		}

		$defines = array();
		$tokens	 = token_get_all($wpconfig_file);
		$token	 = reset($tokens);
        $state   = 0;
		while ($token) {
			if (is_array($token)) {
				if ($token[0] == T_WHITESPACE || $token[0] == T_COMMENT || $token[0] == T_DOC_COMMENT) {
					// do nothing
				} else if ($token[0] == T_STRING && strtolower($token[1]) == 'define') {
					$state = 1;
				} else if ($state == 2 && self::isConstant($token[0])) {
					$key	 = $token[1];
					$state	 = 3;
				} else if ($state == 4 && self::isConstant($token[0])) {
					$value	 = $token[1];
					$state	 = 5;
				}
			} else {
				$symbol = trim($token);
				if ($symbol == '(' && $state == 1) {
					$state = 2;
				} else if ($symbol == ',' && $state == 3) {
					$state = 4;
				} else if ($symbol == ')' && $state == 5) {
					$defines[self::tokenStrip($key)] = self::tokenStrip($value);
					$state = 0;
				}
			}
			$token = next($tokens);
		}

		return $defines;
	}

	/**
	 * Strips a value from from its location
	 *
	 * @return string	The stripped token value
	 */
	private static function tokenStrip($value)
	{
		return preg_replace('!^([\'"])(.*)\1$!', '$2', $value);
	}

	/**
	 * Is the value a constant
	 *
	 * @return bool	Returns string if the value is a constant
	 */
	private static function isConstant($token)
	{
		return $token == T_CONSTANT_ENCAPSED_STRING || $token == T_STRING || $token == T_LNUMBER || $token == T_DNUMBER;
	}

}