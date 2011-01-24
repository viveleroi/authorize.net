<?php
/**
 * @package  Authorize.net
 * @author   Michael Botsko, Botsko.net, LLC
 * @license  Mozilla Public License, 1.1
 * @version  Git-Version
 *
 * This class was developed using PHP5 to interact with the Authorize.net
 * payment gateway.
 *
 * Authorize.net provides details about the response at this web address:
 * http://www.authorize.net/support/merchant/Transaction_Response/Transaction_Response.htm
 */

/**
 * @package Authorize.net
 */
class Authorizenet {

	/**
	 * @var boolean Toggles test urls, etc
	 * @access public
	 */
	public $debug = false;

	/**
	 * @var boolean Toggles mock mode - fake approved request, and never contact auth.net
	 * @access public
	 */
	public $mock = false;

	/**
	 * @var boolean An array of a mock response
	 * @access private
	 */
	private $mock_reponse = array(
		0 => '1',
		1 => '1',
		2 => '1',
		3 => '(TESTMODE) This transaction has been approved.',
		4 => '000000',
		5 => 'P',
		6 => '0',
		7 => '',
		8 => 'Mock response.',
		9 => '75.00',
		10 => 'CC',
		11 => 'auth_only',
		12 => '',
		13 => 'John',
		14 => 'Smith',
		15 => 'SomeCompany',
		16 => '1234 West Main St',
		17 => 'Some City',
		18 => 'CA',
		19 => '12345',
		20 => 'US',
		21 => '555-555-5555',
		22 => '555-555-5555',
		23 => 'someone@somedomain.com',
		24 => '',
		25 => '',
		26 => '',
		27 => '',
		28 => '',
		29 => '',
		30 => '',
		31 => '',
		32 => '',
		33 => '',
		34 => '',
		35 => '',
		36 => '',
		37 => 'F8B3EFAAD9428554CC27C140B7648EFA',
		38 => '',
		39 => ''
	);

	/**
	 * @var array Holds human readable key names for response array
	 * @access private
	 */
	protected $nice_keys = array (
		"Response Code", "Response Subcode", "Response Reason Code", "Response Reason Text",
		"Approval Code", "AVS Result Code", "Transaction ID", "Invoice Number", "Description",
		"Amount", "Method", "Transaction Type", "Customer ID", "Cardholder First Name",
		"Cardholder Last Name", "Company", "Billing Address", "City", "State",
		"Zip", "Country", "Phone", "Fax", "Email", "Ship to First Name", "Ship to Last Name",
		"Ship to Company", "Ship to Address", "Ship to City", "Ship to State",
		"Ship to Zip", "Ship to Country", "Tax Amount", "Duty Amount", "Freight Amount",
		"Tax Exempt Flag", "PO Number", "MD5 Hash", "Card Code (CVV2/CVC2/CID) Response Code",
		"Cardholder Authentication Verification Value (CAVV) Response Code"
	);

	/**
	 * @var array Holds db field-compatible key names for response array
	 * @access private
	 */
	protected $code_keys = array (
		"response_code", "response_subcode", "response_reason_code", "response_reason_text",
		"approval_code", "avs_result_code", "transaction_id", "invoice_number", "description",
		"amount", "method", "transaction_type", "customer_id", "cardholder_first_name",
		"cardholder_last_name", "company", "billing_address", "city", "state",
		"zip", "country", "phone", "fax", "email", "shipto_first_name", "shipto_last_name",
		"shipto_company", "shipto_address", "shipto_city", "shipto_state",
		"shipto_zip", "shipto_country", "tax_amount", "duty_amount", "freight_amount",
		"tax_exempt_flag", "po_number", "hash", "cvv_response_code",
		"cavv_response_code"
	);

	/**
	 * @var array Holds raw response array
	 * @access private
	 */
	protected $response = array();

	/**
	 * @var string Holds live payment API url
	 * @access private
	 */
	const URL_LIVE = 'https://secure.authorize.net/gateway/transact.dll';

	/**
	 * @var string Holds test payment API url
	 * @access private
	 */
	const URL_TEST = 'https://test.authorize.net/gateway/transact.dll';


	/**
	 * Initializes the class, merges the transaction array with the defaults
	 *
	 * @param array $trxn
	 * @access public
	 */
	public function  __construct($trxn){

		$defaults = array(
			// These all must be provided by user
			'x_login'			=> '',
			'x_tran_key'		=> '',
			'x_first_name'		=> '',
			'x_last_name'		=> '',
			'x_address'			=> '',
			'x_city'			=> '',
			'x_state'			=> '',
			'x_zip'				=> '',
			'x_country'			=> '',
			'x_email'			=> '',
			'x_phone'			=> '',
			'x_card_num'		=> '',
			'x_amount'			=> '',
			'x_description'		=> '',
			'x_exp_date'		=> '',
			'x_card_code'		=> '',
			// These are typically not changed
			'x_version'			=> '3.1',
			'x_delim_data'		=> 'TRUE',
			'x_delim_char'		=> '|',
			'x_url'				=> 'FALSE',
			'x_type'			=> 'AUTH_CAPTURE',
			'x_test_request'	=> 'FALSE',
			'x_method'			=> 'CC',
			'x_relay_response'	=> 'FALSE',
			'x_encap_char'		=> '',
			'x_method'			=> 'CC'
		);

		$this->fields = array_merge($defaults, $trxn);

	}


	/**
	 * Forces a few parameters if settings currently require them
	 *
	 * @access private
	 */
	protected function forceParameters(){
		if($this->debug){
			$this->fields['x_type'] = 'AUTH_ONLY';
			$this->fields['x_test_request'] = 'TRUE';
		}
	}


	/**
	 * Builds the query of our transaction parameters to be passed using cURL
	 *
	 * @return string
	 * @access private
	 */
	protected function buildParamString(){
		return http_build_query($this->fields);
	}


	/**
	 * Returns raw response array
	 *
	 * @return array
	 * @access public
	 */
	public function getResponseArray(){
		return $this->response;
	}


	/**
	 * Returns human readable key named response array
	 *
	 * @return array
	 * @access public
	 */
	public function getNiceNamedResponseArray(){
		$named = array();
		for ($i=0; $i<sizeof($this->response);$i++) {
			$named[$this->nice_keys[$i]] = $this->response[$i];
		}
		return $named;
	}


	/**
	 * Returns db-compatible field key named response array
	 *
	 * @return array
	 * @access public
	 */
	public function getCodeNamedResponseArray(){
		$named = array();
		for ($i=0; $i<sizeof($this->response);$i++) {
			$key = isset($this->code_keys[$i]) ? $this->code_keys[$i] : $i;
			$named[$key] = $this->response[$i];
		}
		return $named;
	}


	/**
	 * Activates the API post/response process. Loads in the response string
	 * into the raw response array, unless there's an error.
	 *
	 * @return boolean
	 * @access public
	 */
	public function process(){

		$this->forceParameters();

		if(!$this->mock){

			// execute API calls
			$ch = curl_init( (!$this->debug ? self::URL_LIVE : self::URL_TEST ) );
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->buildParamString());
			$response = urldecode(curl_exec($ch));

			if (curl_errno($ch)) {
				$response[3] = curl_error($ch);
				return false;
			}
			else {
				curl_close ($ch);
			}

			$this->response = explode('|', $response);

		} else {
			$this->response = $this->mock_reponse;
		}

		return true;

	}


	/**
	 * Returns a key of the raw response array, if it exists
	 *
	 * @param integer $key
	 * @return mixed (string|integer)
	 * @access private
	 */
	protected function getKey($key){
		if(array_key_exists($key, $this->response)){
			return $this->response[$key];
		}
		return false;
	}


	/**
	 * Returns a complete approved or not value
	 *
	 * @return boolean
	 * @access public
	 */
	public function isApproved(){
		if($this->getKey(0) === '1'){
			return true;
		}
		return false;
	}


	/**
	 * Returns the response body
	 *
	 * @return string
	 * @access public
	 */
	public function responseMessage(){
		return $this->getKey(3);
	}
}
?>