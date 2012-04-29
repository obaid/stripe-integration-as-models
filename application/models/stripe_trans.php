<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Created 2012-04-20 by Patrick Spence
 *
 */
/** 
 * assumes latest Stripe api library is installed in APPPATH.'third_party' 
 */
require_once(APPPATH.'third_party/stripe.php');

class Stripe_trans extends CI_Model {
	
	protected $private_key; // private key for accessing stripe account
	
	/**
	 * constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->config->load('charge');
		$this->private_key = $this->config->item('stripe_secret_key');
		$this->set_api_key();
	} // __construct
	
	/**
	 * sets the private API key to enable transactions 
	 */
	function set_api_key() {
		Stripe::setApiKey($this->private_key);
	}
	
	public function init($config = array()) {
		if (isset($config['private_key'])) {
			$this->private_key = $config['private_key'];
		}
		$this->set_api_key();
		
	} // init
	
	/**
	 * Get a single record by creating a WHERE clause with
	 * a value for your primary key
	 *
	 * @param string $primary_value The value of your primary key
	 * @return object or array
	 */
	public function get($transaction_id) {
		try {
			$ch = Stripe_Charge::retrieve($transaction_id);
			$data['created'] = date('Y-m-d H:i:s', $ch->created);
			$data['amount'] = $ch->amount;
			$data['refunded'] = $ch->refunded;
			$data['invoice'] = $ch->invoice;
			$data['amount_refunded'] = $ch->amount_refunded;
			$data['fee'] = $ch->fee;
			$data['failure_message'] = $ch->failure_message;
			$data['currency'] = $ch->currency;
			$data['object'] = $ch->object;
			$data['livemode'] = $ch->livemode;
			$data['description'] = $ch->description;
			$data['paid'] = $ch->paid;
			$data['id'] = $ch->id;
			$data['customer'] = $ch->customer;
			$data['disputed'] = $ch->disputed;
			$data['card'] = $this->card_to_array($ch->card);
			
			$data['error'] = FALSE;
			$data['data_object'] = $ch;
			return $data;
		} catch (Exception $e) {
			$data['error'] = TRUE;
			$data['message'] = $e->getMessage();
			return $data;
		}
	}

	/**
	 * Get last (n) charges up to 100 (default).  Cannot return more than 100 at a time.   Data
	 * returned is sorted with most recent first.
	 *
	 * @return array - function returns associative array from stripe, we cant get objects
	 */
	public function get_all($num_charges = 100, $offset = 0) {
		try {
			$ch = Stripe_Charge::all(array(
				'count' => $num_charges,
				'offset' => $offset
				));
			$data['error'] = FALSE;
			foreach ($ch->data as $record) {
				$raw_data[] = $this->charge_to_array($record);
			}
			$data['data'] = $raw_data;
		} catch (Exception $e) {
			$data['error'] = TRUE;
			$data['data'] = array();
		}
		return $data;
	}

	/**
	 * Return a count of every transaction, up to the last 100!
	 *
	 * @return integer
	 */
	public function count_all() {
		$charges = $this->get_all();
		return count($charges);
	}


	/**
	 * inserts a transaction into the system.  i.e. a charge of a card.
	 * @param string $token token generated by a call to stripe to create a token,  usually by the
	 *					  javascript library
	 * @param string $description name/email, etc used to describe this charge
	 * @param INT $amount Amount in PENNIES to charge
	 * @return array of information regarding charge, and/or error flag
	 */
	public function insert($token, $description, $amount) {
		try {
			$charge = Stripe_Charge::create(array(
				'amount' => $amount,
				'currency' => 'usd',
				'card' => $token,
				'description' => $description
			));
			$data['card'] = $this->card_to_array($charge->card);
			$data['amount'] = $charge->amount;
			$data['paid'] = $charge->paid;
			$data['fee'] = $charge->fee;
			$data['description'] = $charge->description;
			$data['id'] = $charge->id;
			$data['failure_message'] = $charge->failure_message;
			$data['error'] = FALSE; // check first when looking at results
		} catch(Exception $e) {
			
			$data['error'] = TRUE;
			$data['message'] = $e->getMessage();
			$data['code'] = $e->getCode();
		}
		return $data;	
	}
	
	/**
	 * wrapper for function insert
	 * @param type $token
	 * @param type $description
	 * @param type $amount
	 * @return type 
	 */
	function charge($token, $description, $amount) {
		return $this->insert($token, $customer, $amount);
	}


	/**
	 * charges using customer token instead of card token.
	 *
	 * 
	 * @param string $customer customer token from Stripe
	 * @param string $description description of this charge
	 * @param int $amount amount in PENNIES to charge
	 * @return type 
	 */
	function charge_customer($customer, $description, $amount) {
		try {
			$charge = Stripe_Charge::create(array(
				'amount' => $amount,
				'currency' => 'usd',
				'customer' => $customer,
				'description' => $description
			));
			$data['last_4'] = $charge->card['last4'];
			$data['amount'] = $charge->amount;
			$data['paid'] = $charge->paid;
			$data['fee'] = $charge->fee;
			$data['description'] = $charge->description;
			$data['id'] = $charge->id;
			$data['card_id'] = $charge->card->id;
			$data['failure_message'] = $charge->failure_message;
			$data['cvc_check'] = $charge->card->cvc_check;
			$data['error'] = FALSE; // check first when looking at results
		} catch(Exception $e) {
			
			$data['error'] = TRUE;
			$data['message'] = $e->getMessage();
			$data['code'] = $e->getCode();
		}
		return $data;
	} // charge
	
	
	
	/**
	 * Similar to insert(), just passing an array to insert
	 * multiple rows at once. Returns an array of insert IDs.
	 *
	 * @param array $data Array of arrays to insert
	 * @return array
	 */
	public function insert_many($data) {
		$ids = array();

		foreach ($data as $row) {
			$ids[] = $this->insert($row, $skip_validation);
		}

		return $ids;
	}


	/**
	 * returns chunk of all transactions dictated by $limit and $offset
	 * @param int $limit
	 * @param int $offset
	 * @return type array/object
	 */
	public function get_limit($limit, $offset = 0) {
		return $this->get_all($limit, $offset);
	}// get_limit

	/**
	 * Refunds a transaction
	 * @param string $transaction_id Stripe transaction token
	 * @param int $amount amount in PENNIES to refund, or 'ALL' to indicate a full refund of remaining money on transaction
	 *
	 */
	function refund($transaction_id, $amount = 'all') {
		$transaction = $this->get($transation_id);
		if (! $transaction['error']) {
			if ($amount == 'all') {
				$amount = $transaction['amount'];
			}
			// $ch = Stripe_Charge::retrieve($transaction_id);	
			try {
				$response = $transaction['data_object']->refund(array('amount' => $amount));
				$data['extended_message'] = '<pre>' . print_r($response, TRUE) . '</pre>';
				$data['amount_refunded'] = $response->amount_refunded;
				$data['error'] = FALSE;
			} catch (Exception $e) {
				$data['error'] = TRUE;
				$data['message'] = $e->getMessage();
			}
			return $data;
		} else {
			return $transaction;
		}
	} // refund

	/**
	 * converts charge object to array format.
	 */
	function charge_to_array($charge) {
		$data = array(
			'id' => $charge->id,
			'invoice' => $charge->invoice,
			'card' => $this->card_to_array($charge->card),
			'livemode' => $charge->livemode,
			'amount' => $charge->amount,
			'failure_message' => $charge->failure_message,
			'fee' => $charge->fee,
			'currency' => $charge->currency,
			'paid' => $charge->paid,
			'description' => $charge->description,
			'disputed' => $charge->disputed,
			'object' => $charge->object,
			'refunded' => $charge->refunded,
			'created' => date('Y-m-d H:i:s', $charge->created),
			'customer' => $charge->customer,
			'amount_refunded' => $charge->amount_refunded,
		);
		return $data;
	} // charge_to_array
	
	/**
	 * converts card object to array
	 * @param type $card 
	 */
	function card_to_array($card) {
		$data = array(
				'address_country' => $card->address_country,
				'type' => $card->type,
				'address_zip_check' => $card->address_zip_check,
				'fingerprint' => $card->fingerprint,
				'address_state' => $card->address_state,
				'exp_month' => $card->exp_month,
				'address_line1_check' => $card->address_line1_check,
				'country' => $card->country,
				'last4' => $card->last4,
				'exp_year' => $card->exp_year,
				'address_zip' => $card->address_zip,
				'object' => $card->object,
				'address_line1' => $card->address_line1,
				'name' => $card->name,
				'address_line2' => $card->address_line2,
				'id' => $card->id,
				'cvc_check' => $card->cvc_check,
			);
		return $data;
	}
}// class stripe