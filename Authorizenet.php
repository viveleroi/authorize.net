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

		// execute API calls
		$ch = curl_init( ($this->debug ? self::URL_LIVE : self::URL_TEST ) );
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

	// @todo add specific get functions


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
	public function getResponseReason(){
		return $this->getKey(3);
	}

/**
Sample response array:
'Response Code' => string '1' (length=1)
'Response Subcode' => string '1' (length=1)
'Response Reason Code' => string '1' (length=1)
'Response Reason Text' => string '(TESTMODE) This transaction has been approved.' (length=46)
'Approval Code' => string '000000' (length=6)
'AVS Result Code' => string 'P' (length=1)
'Transaction ID' => string '0' (length=1)
'Invoice Number' => string '' (length=0)
'Description' => string '' (length=0)
'Amount' => string '75.00' (length=5)
'Method' => string 'CC' (length=2)
'Transaction Type' => string 'auth_only' (length=9)
'Customer ID' => string '' (length=0)
'Cardholder First Name' => string 'John' (length=4)
'Cardholder Last Name' => string 'Smith' (length=5)
'Company' => string '' (length=0)
'Billing Address' => string '1234 West Main St.' (length=18)
'City' => string 'Some City' (length=9)
'State' => string 'CA' (length=2)
'Zip' => string '12345' (length=5)
'Country' => string 'US' (length=2)
'Phone' => string '555-555-5555' (length=12)
'Fax' => string '' (length=0)
'Email' => string 'someone@somedomain.com' (length=22)
'Ship to First Name' => string '' (length=0)
'Ship to Last Name' => string '' (length=0)
'Ship to Company' => string '' (length=0)
'Ship to Address' => string '' (length=0)
'Ship to City' => string '' (length=0)
'Ship to State' => string '' (length=0)
'Ship to Zip' => string '' (length=0)
'Ship to Country' => string '' (length=0)
'Tax Amount' => string '' (length=0)
'Duty Amount' => string '' (length=0)
'Freight Amount' => string '' (length=0)
'Tax Exempt Flag' => string '' (length=0)
'PO Number' => string '' (length=0)
'MD5 Hash' => string 'F8B3EFAAD9428554CC27C140B7648EFA' (length=32)
'Card Code (CVV2/CVC2/CID) Response Code' => string '' (length=0)
'Cardholder Authentication Verification Value (CAVV) Response Code' => string '' (length=0)
*/

}
?>