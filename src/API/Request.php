<?php

/**
 * Class MC4WP_API_Request
 *
 * @api
 */
class MC4WP_API_Request {

	/**
	 * @var string Either "subscribe" or "unsubscribe"
	 */
	public $type;

	/**
	 * @var string MailChimp list ID
	 */
	public $list_id;

	/**
	 * @var string Email address
	 */
	public $email;

	/**
	 * @var array Additional merge fields (optional, only for "subscribe" types)
	 */
	public $merge_vars = array();

	/**
	 * @var array Additional config settings (optional)
	 */
	public $config = array(
		'email_type' => 'html',
		'double_optin' => true,
		'send_welcome' => false,
		'update_existing' => false,
		'replace_interests' => false,
		'send_goodbye' => true,
		'send_notification' => false,
		'delete_member' => false,
		'auto_format_fields' => true
	);

	/**
	 * @var MC4WP_API_Response|null
	 */
	public $response;

	/**
	 * @var array Additional info to bind to this request (internal)
	 */
	public $extra;

	/**
	 * @param       $type
	 * @param       $list_id
	 * @param       $email
	 * @param array $merge_vars
	 * @param array $config
	 * @param array $extra
	 */
	public function __construct( $type, $list_id, $email, array $merge_vars, array $config, $extra = array() ) {
		$this->type = $type;
		$this->list_id = $list_id;
		$this->email = $email;
		$this->merge_vars = $merge_vars;
		$this->config = array_merge( $this->config, $config );
		$this->extra = $extra;

		if( $this->config['auto_format_fields'] ) {
			$this->format_fields();
		}
	}

	/**
	 * Fix field formatting for special fields like "birthday" and "address"
	 */
	public function format_fields() {
		$list = MC4WP_MailChimp_List::make( $this->list_id );

		foreach( $this->merge_vars as $field_tag => $field_value ) {
			$field_type = $list->get_field_type_by_tag( $field_tag );

			switch( $field_type ) {

				// birthday fields need to be MM/DD for the MailChimp API
				case 'birthday':
					$field_value = (string) date( 'm/d', strtotime( $field_value ) );
					break;

				// auto-format if addr1 is not set (ie: field was not broken up in multiple fields)
				case 'address':
					if( ! isset( $field_value['addr1'] ) ) {

						// addr1, addr2, city, state, zip, country
						$address_pieces = explode( ',', $field_value );

						// try to fill it.... this is a long shot
						$field_value = array(
							'addr1' => $address_pieces[0],
							'city'  => ( isset( $address_pieces[1] ) ) ?   $address_pieces[1] : '',
							'state' => ( isset( $address_pieces[2] ) ) ?   $address_pieces[2] : '',
							'zip'   => ( isset( $address_pieces[3] ) ) ?   $address_pieces[3] : '',
						);

					}

					break;
			}

			// update field value
			$this->merge_vars[ $field_tag ] = $field_value;
		}
	}

	/**
	 * Process the request
	 *
	 * @return bool
	 */
	public function process() {

		$api = mc4wp_get_api();

		if( $this->type === 'subscribe' ) {
			$success = $api->subscribe( $this->list_id, $this->email, $this->merge_vars, $this->config['email_type'], $this->config['double_optin'], $this->config['update_existing'], $this->config['replace_interests'], $this->config['send_welcome'] );
		} else {
			$success = $api->unsubscribe( $this->list_id, $this->email, $this->config['send_goodbye'], $this->config['send_notification'], $this->config['delete_member'] );
		}

		if( $success ) {
			// store user email in a cookie
			MC4WP_Tools::remember_email( $this->email );
		}

		// convert API response to our own response object
		$response = new MC4WP_API_Response( $this->type, $success, $api->get_last_response() );
		$this->response = $response;

		/**
		 * @api
		 * @action 'mc4wp_request_processed'
		 *
		 * @param Request
		 * @param Response
		 */
		do_action( 'mc4wp_request_processed', $this, $response );

		return $response;
	}

	/**
	 * @param string $type
	 * @param string $email
	 * @param string $list_id
	 * @param array  $merge_vars
	 * @param array  $config
	 *
	 * @return MC4WP_API_Request
	 */
	public static function create( $type, $list_id, $email, array $merge_vars, array $config ) {
		$request = new self( $type, $list_id, $email, $merge_vars, $config );
		return $request;
	}


	/**
	 * @return array
	 */
	public function __toArray() {
		return (array) $this;
	}

	/**
	 * @param $data
	 *
	 * @return MC4WP_API_Request
	 */
	public static function __fromArray( $data ) {
		$request = new self( $data['type'], $data['list_id'], $data['email'], $data['data'], $data['config'] );
		return $request;
	}


}