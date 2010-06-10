<?php
/**
 * @package  Authorize.net
 * @author   Michael Botsko, Botsko.net, LLC
 * @license  Mozilla Public License, 1.1
 *
 * This class was developed using PHP5 to interact with the Authorize.net
 * Automated Recurring Billing payment gateway.
 *
 * http://developer.authorize.net/api/arb/'
 *
 * Error code details:
 * http://developer.authorize.net/tools/arberrorcodes/
 */

/**
 * @package Authorize.net
 */
class Authorizenet_recurring extends Authorizenet {

	/**
	 * @var string Which mode are using (create, update, cancel)
	 * @access private
	 */
	private $_mode = 'create';

	/**
	 * @var string Holds live payment API url
	 * @access private
	 */
	const URL_LIVE = 'https://api.authorize.net/xml/v1/request.api';

	/**
	 * @var string Holds test payment API url
	 * @access private
	 */
	const URL_TEST = 'https://apitest.authorize.net/xml/v1/request.api';


	/**
	 * Initializes the class, merges the transaction array with the defaults
	 *
	 * @param array $trxn
	 * @access public
	 */
	public function  __construct($trxn){

		$defaults = array(
			// These all must be provided by user
			'x_login'				=> '',
			'x_tran_key'			=> '',
			'x_ref_id'				=> '',
			'x_subsc_id'			=> false,
			'x_subsc_name'			=> '',
			'x_length'				=> '',
			'x_unit'				=> '',
			'x_start_date'			=> '',
			'x_total_occurrences'	=> '',
			'x_trial_occurrences'	=> '',
			'x_trial_amount'		=> '',
			'x_first_name'			=> '',
			'x_last_name'			=> '',
			'x_card_num'			=> '',
			'x_amount'				=> '',
			'x_exp_date'			=> ''
		);

		$this->fields = array_merge($defaults, $trxn);

		if($this->fields['x_subsc_id']){
			$this->_mode = 'update';
		}
	}


	/**
	 * Set the transaction mode to cancel
	 */
	public function cancel(){
		$this->_mode = 'cancel';
	}


	/**
	 * Forces a few parameters if settings currently require them
	 *
	 * @access private
	 */
	protected function forceParameters(){
		if($this->debug){
			// none here
		}
	}


	/**
	 * Builds the query of our transaction parameters to be passed using cURL
	 *
	 * @return string
	 * @access private
	 */
	protected function buildXML(){

	
		// Build the xml for a subscription create request
		if($this->_mode == 'create'){

			$root_elmnt = 'ARBCreateSubscriptionRequest';

			$base_arr = array(
				'merchantAuthentication' => array (
					'name' => $this->fields['x_login'],
					'transactionKey' => $this->fields['x_tran_key'],
				),
				'refId' => $this->fields['x_ref_id'],
				'subscription' => array(
					'name' => $this->fields['x_subsc_name'],
					'paymentSchedule' => array(
						'interval' => array(
							'length' => $this->fields['x_length'],
							'unit' => $this->fields['x_unit']
						),
						'startDate' => $this->fields['x_start_date'],
						'totalOccurrences' => $this->fields['x_total_occurrences'],
						'trialOccurrences' => $this->fields['x_trial_occurrences']
					),
					'amount' => $this->fields['x_amount'],
					'trialAmount' => $this->fields['x_trial_amount'],
					'payment' => array(
						'creditCard' => array(
							'cardNumber' => $this->fields['x_card_num'],
							'expirationDate' => $this->fields['x_exp_date']
						)
					),
					'billTo' => array(
						'firstName' => $this->fields['x_first_name'],
						'lastName' => $this->fields['x_last_name']
					)
				)
			);
		}

		// Build the xml for an update request
		if($this->_mode == 'update'){

			$root_elmnt = 'ARBUpdateSubscriptionRequest';

			$base_arr = array(
				'merchantAuthentication' => array (
					'name' => $this->fields['x_login'],
					'transactionKey' => $this->fields['x_tran_key'],
				),
				'refId' => $this->fields['x_ref_id'],
				'subscriptionId' => $this->fields['x_subsc_id'],
				'subscription' => array()
			);

			// Update the amount if provided
			if(isset($this->fields['x_amount']) && !empty($this->fields['x_amount'])){
				$base_arr['subscription']['amount'] = $this->fields['x_amount'];
			}

			// Update the credit card if provided
			if(isset($this->fields['x_card_num']) && !empty($this->fields['x_card_num'])
					&& isset($this->fields['x_exp_date']) && !empty($this->fields['x_exp_date'])){
				$base_arr['subscription']['payment']['creditCard'] = array(
										'cardNumber' => $this->fields['x_card_num'],
										'expirationDate' => $this->fields['x_exp_date']
									);
			}
		}


		// Build the xml for a cancel request
		if($this->_mode == 'cancel'){

			$root_elmnt = 'ARBCancelSubscriptionRequest';

			$base_arr = array(
				'merchantAuthentication' => array (
					'name' => $this->fields['x_login'],
					'transactionKey' => $this->fields['x_tran_key'],
				),
				'refId' => $this->fields['x_ref_id'],
				'subscriptionId' => $this->fields['x_subsc_id']
			);
		}

		// Build the final XML
		$xml = new Xml;
		$post_xml = $xml->arrayToXml($base_arr, $root_elmnt);
		$post_xml = str_replace('<'.$root_elmnt, '<'.$root_elmnt.' xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd"', $post_xml);
		return $post_xml;

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
		curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->buildXML());
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$this->response = curl_exec($ch);

		// remove xmlns so that the relative uri doesn't cause errors in simplexml
		$this->response = str_replace('xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd"', '', $this->response);
		$this->response = simplexml_load_string($this->response);

		if (curl_errno($ch)) {
			return false;
		}
		else {
			curl_close ($ch);
		}

		return true;

	}


	/**
	 * Returns a complete approved or not value
	 *
	 * @return boolean
	 * @access public
	 */
	public function isApproved(){
		if($this->resultCode() == 'Ok'){
			return true;
		}
		return false;
	}


	/**
	 *
	 * @return <type>
	 */
	public function refId(){
		return $this->response->refId;
	}


	/**
	 *
	 * @return <type>
	 */
	public function resultCode(){
		return $this->response->messages->resultCode;
	}


	/**
	 *
	 * @return <type>
	 */
	public function responseCode(){
		return $this->response->messages->message->code;
	}


	/**
	 *
	 * @return <type>
	 */
	public function responseMessage(){
		return $this->response->messages->message->text;
	}


	/**
	 *
	 * @return <type>
	 */
	public function subscriptionId(){
		return $this->response->subscriptionId;
	}


	/**
	 *
	 * @return <type>
	 */
	public function createOrderHash(){
		$base = implode(':', $this->fields);
		$base .= $this->response->refId.$this->response->messages->message->code.$this->response->subscriptionId;
		return strtoupper(substr(sha1($base), 5, 6));
	}


	/**
	 *
	 * @return <type> 
	 */
	public function getTrxnMode(){
		return $this->_mode;
	}
}
?>