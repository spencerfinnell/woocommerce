<?php
/**
 * WooCommerce Emails Class
 *
 * @class 		woocommerce
 * @package		WooCommerce
 * @category	Class
 * @author		WooThemes
 */
class woocommerce_email {
	
	private $_from_address;
	private $_from_name;
	
	/** constructor */
	function __construct() {
		$this->_from_name 		= get_option('woocommerce_email_from_name');
		$this->_from_address	= get_option('woocommerce_email_from_address');

		/**
		 * Email Header + Footer
		 **/
		add_action('woocommerce_email_header', array(&$this, 'email_header'));
		add_action('woocommerce_email_footer', array(&$this, 'email_footer'));

		/**
		 * Add order meta to email templates
		 **/
		add_action('woocommerce_email_after_order_table', array(&$this, 'order_meta'), 10, 2);
		
		/**
		 * Hooks for sending emails during store events
		 **/
		add_action('woocommerce_low_stock_notification', array(&$this, 'low_stock'));
		add_action('woocommerce_no_stock_notification', array(&$this, 'no_stock'));
		add_action('woocommerce_product_on_backorder_notification', array(&$this, 'backorder'));
		 
		add_action('woocommerce_order_status_pending_to_processing_notification', array(&$this, 'new_order'));
		add_action('woocommerce_order_status_pending_to_completed_notification', array(&$this, 'new_order'));
		add_action('woocommerce_order_status_pending_to_on-hold_notification', array(&$this, 'new_order'));
		add_action('woocommerce_order_status_failed_to_processing_notification', array(&$this, 'new_order'));
		add_action('woocommerce_order_status_failed_to_completed_notification', array(&$this, 'new_order'));
		
		add_action('woocommerce_order_status_pending_to_processing_notification', array(&$this, 'customer_processing_order'));
		add_action('woocommerce_order_status_pending_to_on-hold_notification', array(&$this, 'customer_processing_order'));
		
		add_action('woocommerce_order_status_completed_notification', array(&$this, 'customer_completed_order'));
		
		add_action('woocommerce_new_customer_note_notification', array(&$this, 'customer_note'));
		
		// Let 3rd parties unhook the above via this hook
		do_action( 'woocommerce_email', $this );
	}
	
	function get_from_name() {
		return $this->_from_name;
	}
	
	function get_from_address() {
		return $this->_from_address;
	}
	
	function get_content_type() {
		return 'text/html';
	}
	
	function email_header() {
		woocommerce_get_template('emails/email_header.php', false);
	}
	
	function email_footer() {
		woocommerce_get_template('emails/email_footer.php', false);
	}
	
	/**
	 * Wraps a message in the woocommerce mail template
	 **/
	function wrap_message( $email_heading, $message ) {		
		// Buffer
		ob_start();
	
		do_action('woocommerce_email_header');
		
		echo wpautop(wptexturize( $message ));
		
		do_action('woocommerce_email_footer');
		
		// Get contents
		$message = ob_get_clean();
		
		return $message;
	}
	
	function send( $to, $subject, $message, $headers = '', $attachments = "" ) {	
		if (!$headers) $headers = "Content-Type: text/html\r\n";
		
		add_filter( 'wp_mail_from', array(&$this, 'get_from_address') );
		add_filter( 'wp_mail_from_name', array(&$this, 'get_from_name') );
		add_filter( 'wp_mail_content_type', array(&$this, 'get_content_type') );
		
		// Send the mail	
		wp_mail( $to, $subject, $message, $headers, $attachments );
		
		// Unhook
		remove_filter( 'wp_mail_from', array(&$this, 'get_from_address') );
		remove_filter( 'wp_mail_from_name', array(&$this, 'get_from_name') );
		remove_filter( 'wp_mail_content_type', array(&$this, 'get_content_type') );
	}

	/**
	 * New order
	 **/
	function new_order( $order_id ) {
		global $order, $email_heading;
		
		$order = new woocommerce_order( $order_id );
		
		$email_heading = __('New Customer Order', 'woocommerce');
		
		$subject = apply_filters( 'woocommerce_email_subject_new_order', sprintf( __( '[%s] New Customer Order (# %s)', 'woocommerce' ), get_bloginfo('name' ), $order_id ), $order );
		
		// Buffer
		ob_start();
		
		// Get mail template
		woocommerce_get_template('emails/new_order.php', false);
		
		// Get contents
		$message = ob_get_clean();
	
		// Send the mail	
		$this->send( get_option('woocommerce_new_order_email_recipient'), $subject, $message );
	}

	/**
	 * Processing Order
	 **/
	function customer_processing_order( $order_id ) {
		global $order, $email_heading;
		
		$order = new woocommerce_order( $order_id );

		$email_heading = __('Order Received', 'woocommerce');
		
		$subject = apply_filters( 'woocommerce_email_subject_customer_procesing_order', sprintf( __( '[%s] Order Received', 'woocommerce' ), get_bloginfo( 'name' ) ), $order );
		
		// Buffer
		ob_start();
		
		// Get mail template
		woocommerce_get_template('emails/customer_processing_order.php', false);
		
		// Get contents
		$message = ob_get_clean();
		
		// Attachments
		$attachments = apply_filters('woocommerce_customer_processing_order_attachments', '');
	
		// Send the mail	
		$this->send( $order->billing_email, $subject, $message, false, $attachments );
	}

	/**
	 * Completed Order
	 **/
	function customer_completed_order( $order_id ) {
		global $order, $email_heading;
		
		$order = new woocommerce_order( $order_id );
		
		if ($order->has_downloadable_item) :
			$subject		= __('[%s] Order Complete/Download Links', 'woocommerce');
			$email_heading 	= __('Order Complete/Download Links', 'woocommerce');
		else :
			$subject		= __('[%s] Order Complete', 'woocommerce');
			$email_heading 	= __('Order Complete', 'woocommerce');
		endif;
		
		$email_heading = apply_filters( 'woocommerce_completed_order_customer_notification_subject', $email_heading );
	
		$subject = apply_filters( 'woocommerce_email_subject_customer_completed_order', sprintf( $subject, get_bloginfo( 'name' ) ), $order );
		
		// Buffer
		ob_start();
		
		// Get mail template
		woocommerce_get_template('emails/customer_completed_order.php', false);
		
		// Get contents
		$message = ob_get_clean();
		
		// Attachments
		$attachments = apply_filters('woocommerce_customer_completed_order_attachments', '');
	
		// Send the mail	
		$this->send( $order->billing_email, $subject, $message, false, $attachments );
	}

	/**
	 * Pay for order
	 **/
	function customer_pay_for_order( $pay_for_order ) {
		global $order, $email_heading;
		
		$order = $pay_for_order;
		
		$email_heading = sprintf(__('Invoice for Order #%s', 'woocommerce'), $order->id);
	
		$subject = apply_filters( 'woocommerce_email_subject_customer_pay_for_order', sprintf( __( '[%s] Pay for Order', 'woocommerce' ), get_bloginfo( 'name' ) ), $order );
	
		// Buffer
		ob_start();
		
		// Get mail template
		woocommerce_get_template('emails/customer_pay_for_order.php', false);
		
		// Get contents
		$message = ob_get_clean();
		
		// Attachments
		$attachments = apply_filters('woocommerce_customer_pay_for_order_attachments', '');
		
		// Send the mail	
		$this->send( $order->billing_email, $subject, $message, $attachments );
	}

	/**
	 * Customer notes
	 **/
	function customer_note( $args ) {
		global $order, $email_heading, $customer_note;
		
		$defaults = array(
			'order_id' => '',
			'customer_note'	=> ''
		);

		$args = wp_parse_args( $args, $defaults );

		extract( $args );
		
		if (!$order_id || !$customer_note) return;
		
		$order = new woocommerce_order( $order_id );
		
		$email_heading = __('A note has been added to your order', 'woocommerce');
		
		$subject = apply_filters( 'woocommerce_email_subject_customer_note', sprintf( __( '[%s] A note has been added to your order', 'woocommerce' ), get_bloginfo( 'name' ) ), $order );
		
		// Buffer
		ob_start();
		
		// Get mail template
		woocommerce_get_template('emails/customer_note_notification.php', false);
		
		// Get contents
		$message = ob_get_clean();
	
		// Send the mail	
		$this->send( $order->billing_email, $subject, $message );
	}
	
	/**
	 * Low stock notification email
	 **/
	function low_stock( $product ) {
		$_product = new woocommerce_product($product);
	
		$subject = apply_filters( 'woocommerce_email_subject_low_stock', sprintf( '[%s] %s', get_bloginfo( 'name' ), __( 'Product low in stock', 'woocommerce' ) ), $_product );
		
		$message = $this->wrap_message( 
			__('Product low in stock', 'woocommerce'),
			'#' . $_product->id .' '. $_product->get_title() . ' ('. $_product->sku.') ' . __('is low in stock.', 'woocommerce')
		);
	
		// Send the mail
		$this->send( get_option('woocommerce_stock_email_recipient'), $subject, $message );
	}
	
	/**
	 * No stock notification email
	 **/
	function no_stock( $product ) {
		$_product = new woocommerce_product($product);
		
		$subject = apply_filters( 'woocommerce_email_subject_no_stock', sprintf( '[%s] %s', get_bloginfo( 'name' ), __( 'Product out of stock', 'woocommerce' ) ), $_product );
		
		$message = $this->wrap_message( 
			__('Product out of stock', 'woocommerce'),
			'#' . $_product->id .' '. $_product->get_title() . ' ('. $_product->sku.') ' . __('is out of stock.', 'woocommerce')
		);
	
		// Send the mail
		$this->send( get_option('woocommerce_stock_email_recipient'), $subject, $message );
	}
	
	
	/**
	 * Backorder notification email
	 **/
	function backorder( $args ) {
	
		$defaults = array(
			'product' => '',
			'quantity' => '',
			'order_id' => ''
		);

		$args = wp_parse_args( $args, $defaults );

		extract( $args );
		
		if (!$product || !$quantity) return;
	
		$_product = new woocommerce_product($product);
		
		$subject = apply_filters( 'woocommerce_email_subject_backorder', sprintf( '[%s] %s', get_bloginfo( 'name' ), __( 'Product Backorder', 'woocommerce' ) ), $_product );
	
		$message = $this->wrap_message( 
			__('Product Backorder', 'woocommerce'),
			sprintf(__('%s units of #%s %s (%s) have been backordered in order #%s.', 'woocommerce'), $quantity, $_product->id, $_product->get_title(), $_product->sku, $order_id )
		);
	
		// Send the mail
		$this->send( get_option('woocommerce_stock_email_recipient'), $subject, $message );
	}

	/**
	 * Add order meta to email templates
	 **/
	function order_meta( $order, $sent_to_admin ) {
		
		$meta = array();
		$show_fields = apply_filters('woocommerce_email_order_meta_keys', array('coupons'), $sent_to_admin);
	
		if ($order->customer_note) :
			$meta[__('Note:', 'woocommerce')] = wptexturize($order->customer_note);
		endif;
		
		if ($show_fields) foreach ($show_fields as $field) :
			
			$value = get_post_meta( $order->id, $field, true );
			if ($value) $meta[ucwords(esc_attr($field))] = wptexturize($value);
			
		endforeach;
		
		if (sizeof($meta)>0) :
			echo '<h2>'.__('Order information', 'woocommerce').'</h2>';
			foreach ($meta as $key=>$value) :
				echo '<p><strong>'.$key.':</strong> '.$value.'</p>';
			endforeach;
		endif;
	}
	
	/**
	 * Customer new account welcome email
	 **/
	function customer_new_account( $user_id, $plaintext_pass ) {
		 global $user_login, $user_pass, $blogname;
		
		if (!$user_id || !$plaintext_pass) return;
		
		$user = new WP_User($user_id);
		
		$user_login = stripslashes($user->user_login);
		$user_email = stripslashes($user->user_email);
		$user_pass 	= $plaintext_pass;
		 
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
		
		$subject		= apply_filters( 'woocommerce_email_subject_customer_new_account', sprintf( __( 'Your account on %s', 'woocommerce'), $blogname ), $user );
		$email_heading 	= __('Your account details', 'woocommerce');
	
		// Buffer
		ob_start();
		
		// Get mail template
		woocommerce_get_template('emails/customer_new_account.php', false);
		
		// Get contents
		$message = ob_get_clean();
	
		// Send the mail	
		$this->send( $user_email, $subject, $message );
	}

}