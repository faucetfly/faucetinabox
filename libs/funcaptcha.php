<?php
/*
 * FunCaptcha
 * PHP Integration Library
 *
 * @version 1.1.0
 *
 * Copyright (c) 2013 SwipeAds -- http://www.funcaptcha.com
 * AUTHOR:
 *   Kevin Gosschalk
 *   Brandon Bakker
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 */
define("FUNCAPTCHA_SERVER", "funcaptcha.com");

// Define class if it does not already exist
if (!class_exists('FUNCAPTCHA')):

	class FUNCAPTCHA {
		// Set defaults for values that can be specified via the config file or passed in via __construct.
		protected $funcaptcha_public_key = '';
		protected $funcaptcha_private_key = '';
		protected $funcaptcha_host = 'funcaptcha.com';
		protected $funcaptcha_challenge_url = '';
		protected $funcaptcha_debug = FALSE;
		protected $funcaptcha_api_type = "php";
		protected $funcaptcha_plugin_version = "1.1.0";
		protected $funcaptcha_security_level = 0;
		protected $funcaptcha_lightbox_mode = FALSE;
		protected $funcaptcha_lightbox_button_id = "";
		protected $funcaptcha_lightbox_submit_javascript = "";
		protected $session_token;
		protected $funcaptcha_theme = 0;
		protected $funcaptcha_language = "en";
		protected $funcaptcha_proxy;
		protected $funcaptcha_json_path = "json.php";
		protected $funcaptcha_nojs_fallback = false;
		protected $version = '1.1.0';

		/**
		 * Constructor
		 *
		 */
		public function __construct() {
			$this->funcaptcha_host = FUNCAPTCHA_SERVER;

			if ($this->funcaptcha_api_type == "vBulletin") {
				$this->funcaptcha_json_path = DIR . "/includes/json.php";
			}

			if ($this->funcaptcha_host == "") {
				$this->msgLog("ERROR", "Warning: Host is not set.");
			} else {
				$this->msgLog("DEBUG", "Set Host: '$this->funcaptcha_host'");
			}
		}

		/**
		 * Returns FunCaptcha HTML to display in form
		 *
		 * @param string $public_key - FunCaptcha public key
		 * @param array $args - Additional information to pass to FunCaptcha servers
		 * @return string
		 */
		public function getFunCaptcha($public_key, $args = null) {

			$this->funcaptcha_public_key = $public_key;
			if ($this->funcaptcha_public_key == "" || $this->funcaptcha_public_key == null) {
				$this->msgLog("ERROR", "Warning: Public key is not set.");
			} else {
				$this->msgLog("DEBUG", "Public key: '$this->funcaptcha_public_key'");
			}


			//send your public key, your site name, the users ip and browser type.
			$data = array(
				'public_key' => $this->funcaptcha_public_key,
				'site' => $_SERVER["SERVER_NAME"],
				'userip' => $this->getIP(),
				'userbrowser' => $_SERVER['HTTP_USER_AGENT'],
				'api_type' => $this->funcaptcha_api_type,
				'plugin_version' => $this->funcaptcha_plugin_version,
				'security_level' => $this->funcaptcha_security_level,
				'language' => $this->funcaptcha_language,
				'noscript_support' => $this->funcaptcha_nojs_fallback,
				'lightbox' => $this->funcaptcha_lightbox_mode,
				'lightbox_button_id' => $this->funcaptcha_lightbox_button_id,
				'lightbox_submit_js' => $this->funcaptcha_lightbox_submit_javascript,
				'theme' => $this->funcaptcha_theme,
				'args' => $args
			);

			//get session token.
			$session = $this->doPostReturnObject('/fc/gt/', $data);
			$this->session_token = $session->token;
			$this->funcaptcha_challenge_url = $session->challenge_url;

			if (!$this->funcaptcha_challenge_url) {
				$this->msgLog("ERROR", "Warning: Couldn't retrieve challenge url.");
			} else {
				$this->msgLog("DEBUG", "Challenge url: '$this->funcaptcha_challenge_url'");
			}

			if (!$this->session_token) {
				$this->msgLog("ERROR", "Warning: Couldn't retrieve session.");
			} else {
				$this->msgLog("DEBUG", "Session token: '$this->session_token'");
			}

			if ($this->session_token && $this->funcaptcha_challenge_url && $this->funcaptcha_host) {
				//return html to generate captcha.
				$url = "https://";
				$url .= $this->funcaptcha_host;
				$url .= $this->funcaptcha_challenge_url;
				$url .= "?cache=" . time();
				return "<div id='FunCaptcha'></div><input type='hidden' id='FunCaptcha-Token' name='fc-token' value='" . $this->session_token . "'><script src='" . $url . "' type='text/javascript' language='JavaScript'></script>" . ($this->funcaptcha_nojs_fallback ? $session->noscript : "<noscript><p>Please enable JavaScript to continue.</p></noscript>");
			} else {
				//if failed to connect, display helpful message.
				$style = "padding: 10px; border: 1px solid #b1abb2; background: #f1f1f1; color: #000000;";
				$message = "The CAPTCHA cannot be displayed. This may be a configuration or server problem. You may not be able to continue. Please visit our <a href='http://funcaptcha.com/status' target='_blank'>status page</a> for more information or to contact us.";
				echo "<p style=\"$style\">$message</p>\n";
			}
		}


		/**
		 * Returns the remote user's IP address
		 *
		 * @return String
		 * */
		public function getIP() {
			if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
				return $_SERVER["HTTP_X_FORWARDED_FOR"];
			} else if (isset($_SERVER["REMOTE_ADDR"])) {
				return $_SERVER["REMOTE_ADDR"];
			} else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
				return $_SERVER["HTTP_CLIENT_IP"];
			}

			return "";
		}

		/**
		 * Set security level of FunCaptcha
		 *
		 * Possible options are:
		 * 0 - Automatic-- security rises for suspicious users
		 * 20 - Enhanced security-- always use Enhanced security
		 *
		 * See our website for more details on these options
		 *
		 * @param int $security - Security level
		 * @return boolean
		 */
		public function setSecurityLevel($security) {
			$this->funcaptcha_security_level = $security;
			$this->msgLog("DEBUG", "Security Level: '$this->funcaptcha_security_level'");
		}

		/**
		 * Set theme of FunCaptcha
		 *
		 * See here for options: https://www.funcaptcha.com/themes/
		 *
		 * @param int $theme - theme option, 0 is default.
		 * @return boolean
		 */
		public function setTheme($theme) {
			$this->funcaptcha_theme = $theme;
			$this->msgLog("DEBUG", "Theme: '$this->funcaptcha_theme'");
		}

		/**
		 * Set language of FunCaptcha
		 *
		 * @param string $language - language to set, defaults to english if not available.
		 * @return boolean
		 */
		public function setLanguage($language) {
			$this->funcaptcha_language = $language;
			$this->msgLog("DEBUG", "Language: '$this->funcaptcha_theme'");
		}

		/**
		 * Set proxy for FunCaptcha
		 *
		 * @param int $proxy - Proxy server (including port, eg: 111.11.11.111:8080)
		 * @return boolean
		 */
		public function setProxy($proxy) {
			$this->funcaptcha_proxy = $proxy;
			$this->msgLog("DEBUG", "Proxy: '$this->funcaptcha_proxy'");
		}

		/**
		 * Set if the user has no Javascript if it should fallback to a non-FunCaptcha CAPTCHA instead.
		 *
		 * @param int $toggle - Boolean, if on or not.
		 * @return boolean
		 */
		public function setNoJSFallback($toggle) {
			$this->funcaptcha_nojs_fallback = $toggle;
			$this->msgLog("DEBUG", "No JS Fallback: '$this->funcaptcha_nojs_fallback'");
		}

		/**
		 * Set lightbox mode of FunCaptcha
		 *
		 *
		 * See our website for more details on these options
		 *
		 * @param boolean $enable - Enable lightbox mode.
		 * @param boolean $submit_button_id - ID of button to be used to display lightbox.
		 * @param boolean $submit_javascript_function_name - Name of javascript function to call on lightbox FunCaptcha completion.
		 * @return boolean
		 */
		public function setLightboxMode($enable, $submit_button_id = null, $submit_javascript_function_name = null) {
			$this->funcaptcha_lightbox_mode = $enable;
			$this->funcaptcha_lightbox_button_id = $submit_button_id;
			$this->funcaptcha_lightbox_submit_javascript = $submit_javascript_function_name;
			$this->msgLog("DEBUG", "Lightbox mode: '$this->funcaptcha_lightbox_mode'");
			$this->msgLog("DEBUG", "Lightbox Button ID: '$this->funcaptcha_lightbox_button_id'");
			$this->msgLog("DEBUG", "Lightbox JS Name: '$this->funcaptcha_lightbox_submit_javascript'");
		}

		/**
		 * Verify if user has solved the FunCaptcha
		 *
		 * @param string $private_key - FunCaptcha private key
		 * @param array $args - Additional information to pass to FunCaptcha servers
		 * @return boolean
		 */
		public function checkResult($private_key, $args = null) {
			$this->funcaptcha_private_key = $private_key;

			$this->msgLog("DEBUG", ("Session token to check: " . $_POST['fc-token']));

			if ($this->funcaptcha_private_key == "") {
				$this->msgLog("ERROR", "Warning: Private key is not set.");
			} else {
				$this->msgLog("DEBUG", "Private key: '$this->funcaptcha_private_key'");
			}

			if ($_POST['fc-token']) {
				$data = array(
					'private_key' => $this->funcaptcha_private_key,
					'session_token' => $_POST['fc-token'],
					'fc_rc_challenge' => (isset($_POST['fc_rc_challenge']) ? $_POST['fc_rc_challenge'] : null),
					'args' => $args
				);
				$result = $this->doPostReturnObject('/fc/v/', $data);
			} else {
				$this->msgLog("ERROR", "Unable check the result.  Please check that you passed in the correct public, private keys.");
			}
			return $result->solved;
		}

		/**
		 * Internal function - does HTTPs post and returns result.
		 *
		 * @param string $url_path - server path
		 * @param array $data - data to send
		 * @return object
		 */
		protected function doPostReturnObject($url_path, $data) {
			$result = "";
			$fields_string = "";
			$data_string = "";
			foreach ($data as $key => $value) {
				if (is_array($value)) {
					if (!empty($value)) {
						foreach ($value as $k => $v) {
							$data_string .= $key . '[' . $k . ']=' . $v . '&';
						}
					} else {
						$data_string .= $key . '=&';
					}
				} else {
					$data_string .= $key . '=' . $value . '&';
				}
			}
			rtrim($data_string, '&');

			$curl_url = "https://";
			$curl_url .= $this->funcaptcha_host;
			$curl_url .= $url_path;

			// Log it.
			$this->msgLog("DEBUG", "cURl: url='$curl_url', data='$data_string'");

			if (function_exists('curl_init') and function_exists('curl_exec')) {
				if ($ch = curl_init($curl_url)) {
					// Set the cURL options.
					curl_setopt($ch, CURLOPT_POST, count($data));
					curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
					if (isset($this->funcaptcha_proxy)) {
						curl_setopt($ch, CURLOPT_PROXY, $this->funcaptcha_proxy);
					}
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

					// Execute the cURL request.
					$result = curl_exec($ch);
					curl_close($ch);
				} else {
					// Log it.
					$this->msgLog("DEBUG", "Unable to enable cURL: url='$curl_url'");
				}
			} else {
				// Log it.
				// Build a header
				$http_request = "POST $url_path HTTP/1.1\r\n";
				$http_request .= "Host: $this->funcaptcha_host\r\n";
				$http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
				$http_request .= "Content-Length: " . strlen($data_string) . "\r\n";
				$http_request .= "User-Agent: FunCaptcha/PHP " . $this->funcaptcha_plugin_version . "\r\n";
				$http_request .= "Connection: Close\r\n";
				$http_request .= "\r\n";
				$http_request .= $data_string . "\r\n";

				$result = '';
				$errno = $errstr = "";
				$fs = fsockopen("ssl://" . $this->funcaptcha_host, 443, $errno, $errstr, 10);

				if (false == $fs) {
					$this->msgLog("ERROR", "Could not open socket");
				} else {
					fwrite($fs, $http_request);
					while (!feof($fs)) {
						$result .= fgets($fs, 4096);
					}
					$result = explode("\r\n\r\n", $result, 2);
					$result = $result[1];
				}
			}
			$result = $this->JSONDecode($result);

			return $result;
		}

		/**
		 * Internal function - does does JSON decoding of data from server.
		 *
		 * @param string $string - json to decode
		 * @return object
		 */
		protected function JSONDecode($string) {
			$result = array();
			if (function_exists("json_decode")) {
				try {
					$result = json_decode($string);
				} catch (Exception $e) {
					$this->msgLog("ERROR", "Exception when calling json_decode: " . $e->getMessage());
					$result = null;
				}
			} else if (file_Exists($this->funcaptcha_json_path)) {
				require_once($this->funcaptcha_json_path);
				$json = new Services_JSON();
				$result = $json->decode($string);
			} else {
				$this->msgLog("ERROR", "No JSON decode function available.");
			}
			return $result;
		}

		/**
		 * Log a message
		 *
		 * @param string $type - type of error
		 * @param string $message - message to log
		 * @return null
		 */
		protected function msgLog($type, $message) {

			// Is it an error message?
			if (FALSE !== stripos($type, "error")) {
				error_log($message);
			}

			// Build the full message.
			$debug_message = "<p style='padding: 10px; border: 1px solid #2389d1; background: #43c0ff; color: #134276;'><strong>$type:</strong> $message</p>\n";

			// Output to screen if in debug mode
			if ($this->funcaptcha_debug) {
				echo "$debug_message";
			}
		}

		/**
		 * Debug mode, enables showing output of errors.
		 *
		 * @param boolean $mode debug state
		 */
		public function debugMode($mode) {
			$this->funcaptcha_debug = $mode;
		}

	}
endif;