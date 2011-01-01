<?

/**
 * Bitly API Interface
 *
 * This package interfaces with the www.bit.ly URL-shortening REST API.
 * Documentation for the API is here: http://code.google.com/p/bitly-api/wiki/ApiDocumentation
 *
 * @TODO: Add support for passing multiple hashes to functions since v3 API supports that now.
 *
 * @author    Ed Hickey
 * @package   Bitly
 * @version   1.1
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 **/



/**
 * Bitly class
 *
 * @package   Bitly
 * @subpackage  classes
 * Example:
 *   $b = new Bitly('myname', 'API_KEY');
 *   echo $b->shorten('http://www.google.com');  // "http://bit.ly/SD3eqa"
 *
 **/
class Bitly {

  const API_VERSION = '3';
  const API_URL = 'http://api.bit.ly';

  /**
   * The cURL object.
   *
   * @access private
   * @var cURL object
   */
  private $curl;

  /**
   * The response returned from the API call in make_request().
   *
   * @access private
   * @var mixed
   */
  private $response;

  /**
   * The response headers returned from the API call in make_request().
   *
   * @access public
   * @var array
   */
  var $headers;

  /**
   * The required query string parameters needed for the API.
   *
   * @access private
   * @var string
   */
  private $default_params;

  /**
   * The API action/method, set by the various functions.
   *
   * @var string
   */
  private $action;

  /**
   * An array of the Bit.ly errors.  Populated via Bitly#errors.
   *
   * @var Array
   */
  private $_errors;



  /**
   * Set up the cURL object and the base request URL.
   *
   * @param  string $login The Bit.ly account login
   * @param  string $api_key API key listed here: http://bit.ly/account/
   * @return void
   **/
  public function __construct($login, $api_key){
    $this->login = $login;
    $this->api_key = $api_key;

    $this->default_params = 'login=' . urlencode($this->login) . '&' . 'apiKey=' . urlencode($this->api_key);
  }


  /**
   * Shortens the passed URL.
   *
   * @param  string $url URL to shorten
   * @return string Shortened URL.
   **/
  public function shorten($url){
    $this->action = 'shorten';
    $this->params = '&longUrl=' . urlencode($url);

    $this->make_request();
    return $this->response->data->url;
  }

  /**
   * Decodes the URL back into it's original (long) source URL.
   *
   * @param  string $url_or_hash The Bit.ly hash or URL.
   * @return string Original (long) URL.
   **/
  public function expand($url_or_hash){
    $this->action = 'expand';
    $hash = $this->hash_from_url($url_or_hash);
    $this->params = "&hash=$hash";

    $this->make_request();
    return $this->response->data->expand[0]->long_url;
  }

  /**
   * Returns click information about the URL, including global_clicks and user_clicks.
   *
   * @param  string $url_or_hash The Bit.ly hash or URL.
   * @return object of Bitly click info.
   **/
  public function clicks($url_or_hash){
    $this->action = 'clicks';
    $hash = $this->hash_from_url($url_or_hash);
    $this->params = "&hash=$hash";

    $this->make_request();
    return $this->response->data->clicks[0];
  }


  /**
   * Returns a list of potential error codes.
   *
   * @return array Bit.ly error codes.
   **/
  public function errors(){
    // Cache it since they won't change.
    if(!is_array($this->_errors)){
      $this->action = 'errors';
      $this->make_request();
      $this->_errors = $this->response->results;
    }
    return $this->_errors;
  }

  /**
   * Makes the request to Bit.ly based on class variables set in other functions.
   * The API response is stored in $this->response.
   * @access private
   * @return bool
   **/
  private function make_request(){
    $this->curl_init();

    $url = self::API_URL . '/v' . self::API_VERSION . "/{$this->action}?{$this->default_params}&{$this->params}";
    curl_setopt($this->curl, CURLOPT_URL, $url);

    $resp = curl_exec($this->curl);
    $this->headers = curl_getinfo($this->curl);

    // Check for HTTP errors
    if(intval(curl_errno($this->curl)) != 0){
      throw new BitlyError('http', curl_error($this->curl), curl_errno($this->curl));
    }else{
      $this->response = json_decode($resp);
      // Throw if the API returns errors.
      if(!empty($this->response->errorMessage)){
        throw new BitlyError('Bitly', $this->response->errorMessage, $this->response->errorCode);
      }
    }

    curl_close($this->curl);
    return true;
  }

  /**
   * Sets up curl
   *
   * @return boolean
   **/
  private function curl_init()
  {
    $this->curl = curl_init();
    $this->response = $this->headers = null;
    curl_setopt($this->curl, CURLOPT_PORT, 80);
    curl_setopt($this->curl, CURLOPT_USERAGENT, 'PHP Bit.ly');
    curl_setopt($this->curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

    return true;
  }

  /**
   * Parses the Bit.ly has out of the passed $string.
   *
   * @access private
   * @param String $string The Bit.ly URL or hash. e.g.: "http://bit.ly/3CzY1f" or "3CzY1f"
   * @return String The hash derived from passed $string.
   **/
  private function hash_from_url($string){
    $parts = explode('/', $string);
    return array_pop($parts);
  }
}


/**
 * Simple exception class to display the various error types in Bitly.
 * @package   Bitly
 * @subpackage  classes
 **/
class BitlyError extends Exception{

  function __construct($type, $error_message, $error_code){
    $error_message = "$type : $error_message (#$error_code)";
    parent::__construct((string) $error_message, $error_code);
  }
}
