<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2012-2015 Wooppay
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @copyright   Copyright (c) 2012-2015 Wooppay
 * @author      Chikabar
 * @version     1.1.6
 */

class WC_Gateway_Wooppay_Wallet extends WC_Payment_Gateway
{
public $debug = 'yes';
	public function __construct()
	{
		$this->id = 'wooppay_wallet';
		$this->icon = apply_filters('woocommerce_wooppay_icon', plugins_url() . '/wooppay-1.1.6/assets/images/btnWP.png');
		$this->has_fields = false;
		$this->method_title = __('WOOPPAY', 'Wooppay');
		$this->init_form_fields();
		$this->init_settings();
		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];
		$this->instructions = $this->get_option('instructions');
		$this->enable_for_methods = $this->get_option('enable_for_methods', array());

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
		add_action('woocommerce_api_wc_gateway_wooppay', array($this, 'check_response'));
	}

	public function check_response()
	{
		if (isset($_REQUEST['id_order']) && isset($_REQUEST['key'])) {
			$order = wc_get_order((int)$_REQUEST['id_order']);
			if ($order && $order->key_is_valid($_REQUEST['key'])) {
				try {
					include_once('WooppaySoapClient.php');
					$client = new WooppaySoapClient($this->get_option('api_url'));
					if ($client->login($this->get_option('api_username'), $this->get_option('api_password'))) {
						$orderPrefix = $this->get_option('order_prefix');
						$serviceName = $this->get_option('service_name');
						$orderId = $order->id;
						if ($orderPrefix) {
							$orderId = $orderPrefix . '_' . $orderId;
						}
						$invoice = $client->createInvoice($orderId, '', '', $order->order_total, $serviceName);
						if ($client->getOperationData((int)$invoice->response->operationId)->response->records[0]->status == WooppayOperationStatus::OPERATION_STATUS_DONE) {
							$order->update_status('completed', __('Payment completed.', 'woocommerce'));
							die('{"data":1}');
						}
					}
				} catch (Exception $e) {
					$this->add_log($e->getMessage());
					wc_add_notice(__('Wooppay error:', 'woocommerce') . $e->getMessage() . print_r($order, true), 'error');
				}
			} else
				$this->add_log('Error order key: ' . print_r($_REQUEST, true));
		} else
			$this->add_log('Error call back: ' . print_r($_REQUEST, true));
		die('{"data":1}');
	}

	/* Admin Panel Options.*/
	public function admin_options()
	{
		?>
		<h3><?php _e('Wooppay', 'wooppay_wallet'); ?></h3>
		<table class="form-table">
			<?php $this->generate_settings_html(); ?>
		</table> <?php
	}

	/* Initialise Gateway Settings Form Fields. */
	public function init_form_fields()
	{
		global $woocommerce;

		$shipping_methods = array();

		if (is_admin())
			foreach ($woocommerce->shipping->load_shipping_methods() as $method) {
				$shipping_methods[$method->id] = $method->get_title();
			}

		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'wooppay_wallet'),
				'type' => 'checkbox',
				'label' => __('Enable Wooppay Gateway', 'wooppay_wallet'),
				'default' => 'no'
			),
			'title' => array(
				'title' => __('Title', 'wooppay_wallet'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'wooppay_wallet'),
				'desc_tip' => true,
				'default' => __('Плати с Wooppay. Получай кешбэк 4%', 'wooppay_wallet')
			),
			'description' => array(
				'title' => __('Description', 'wooppay_wallet'),
				'type' => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.', 'wooppay_wallet'),
				'default' => __('Оплата с помощью кредитной карты или кошелька Wooppay', 'wooppay_wallet')
			),
			'instructions' => array(
				'title' => __('Instructions', 'wooppay_wallet'),
				'type' => 'textarea',
				'description' => __('Instructions that will be added to the thank you page.', 'wooppay_wallet'),
				'default' => __('Введите все необходимые данные и вас перенаправит на портал Wooppay для оплаты', 'wooppay_wallet')
			),
			'api_details' => array(
				'title' => __('API Credentials', 'wooppay_wallet'),
				'type' => 'title',
			),
			'api_url' => array(
				'title' => __('API URL', 'wooppay_wallet'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay_wallet'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_wallet')
			),
			'api_username' => array(
				'title' => __('API Username', 'wooppay_wallet'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay_wallet'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_wallet')
			),
			'api_password' => array(
				'title' => __('API Password', 'wooppay_wallet'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay_wallet'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_wallet')
			),
			'order_prefix' => array(
				'title' => __('Order prefix', 'wooppay_wallet'),
				'type' => 'text',
				'description' => __('Order prefix', 'wooppay_wallet'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_wallet')
			),
			'service_name' => array(
				'title' => __('Service name', 'wooppay_wallet'),
				'type' => 'text',
				'description' => __('Service name', 'wooppay_wallet'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_wallet')
			),
		);

	}

	function process_payment($order_id)
	{
		include_once('WooppaySoapClient.php');
		global $woocommerce;
		$order = new WC_Order($order_id);
		try {
			$client = new WooppaySoapClient($this->get_option('api_url'));
			if ($client->login($this->get_option('api_username'), $this->get_option('api_password'))) {
				$requestUrl = WC()->api_request_url('WC_Gateway_Wooppay_Wallet') . '?id_order=' . $order_id . '&key=' . $order->order_key;
				$backUrl = $this->get_return_url($order);
				$orderPrefix = $this->get_option('order_prefix');
				$serviceName = $this->get_option('service_name');
				$invoice = $client->createInvoice(1, $orderPrefix . '_' . $order->id, $backUrl, $requestUrl, $order->order_total, $serviceName, 'Оплата заказа №' . $order->id, '', '', $order->billing_email, $order->billing_phone, 32);
				$woocommerce->cart->empty_cart();
				$order->update_status('pending', __('Payment Pending.', 'woocommerce'));
				//$order->payment_complete($invoice->response->operationId);
				return array(
					'result' => 'success',
					'redirect' => $invoice->response->operationUrl
				);
			}
		} catch (Exception $e) {
			$this->add_log($e->getMessage());
			wc_add_notice(__('Wooppay error:', 'woocommerce') . $e->getCode(), 'error');
		}
	}

	function thankyou()
	{
		echo $this->instructions != '' ? wpautop($this->instructions) : '';
	}

	function add_log($message) {
		if ($this->debug == 'yes') {
			if (empty($this->log))
				$this->log = new WC_Logger();
			$this->log->add('Wooppay', $message);
		}
	}
}
