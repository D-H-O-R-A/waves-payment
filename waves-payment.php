<?php
/**
 * Plugin Name: Waves Payment
 * Plugin URI: 
 * Description: Plugin para pagamento com Waves blockchain em woocommerce. TestNet e MainNet estão disponiveis para uso.
 * Author: Diego H. O. R. Antunes
 * Author URI: https://diegohorantunes.web.app
 * Version: 0.1.0
 * Text Domain: waves-payment
 * Domain Path: /i18n/languages/
 *
 * @package   waves-payment
 * @author    Diego H. O. R. Antunes
 * @category  Payment
 *
 * This waves gateway forks the WooCommerce core "Cheque" payment gateway to create another waves payment method.
 */
defined( 'ABSPATH' ) or exit;
global $woocommerce;
global $wpdb;
$table_name = $wpdb->prefix.'multi_address';

require __DIR__ . '/support/Keccak.php';

require __DIR__ . '/vendor/autoload.php';

use deemru\WavesKit;
use StephenHill\Base58;

use function deemru\curve25519\rnd;

//create new account
//$account = $wk->randomSeed(15);

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 0.1.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + waves gateway
 */

function waves_payment_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Waves_Payment';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'waves_payment_add_to_gateways' );

function reduce_stock_processing($order_id) {
	wc_reduce_stock_levels($order_id);
}
add_action('woocommerce_order_status_processing', 'reduce_stock_processing');

//script js
function ava_test_init() {
    wp_enqueue_script( 'waves-p.js', plugins_url( '/index.js', __FILE__ ));
}
add_action('wp_enqueue_scripts','ava_test_init');

//jquery
function jquery_init() {
    wp_enqueue_script( 'jquery.js', plugins_url( '/jquery.js', __FILE__ ));
}
add_action('wp_enqueue_scripts','jquery_init');

/**
 * Adds plugin page links
 * 
 * @since 0.1.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */

function waves_payment_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=waves-payment' ) . '">' . __( 'Configure', 'waves-payment' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'waves_payment_gateway_plugin_links' );

/**
 * Waves Payment Gateway
 *
 * Provides an Waves Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Waves_Payment
 * @extends		WC_Payment_Gateway
 * @version		0.1.0
 * @package		WooCommerce/Classes/Payment
 * @author 		Diego H O R Antunes
 */

add_action( 'plugins_loaded', 'wc_waves_paymant_init', 11 );

function wc_waves_paymant_init() {

	class WC_Waves_Payment extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'waves-payment';
			$this->icon               = apply_filters('woocommerce_waves_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'waves', 'waves-payment' );
			$this->method_description = __( 'Receba pagamento através da blockchain da Waves em seu Woocommerce.', 'waves-payment' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
            $this->mode         = $this->get_option('mode');
            $this->address      = $this->get_option('address');
			$this->currency     = $this->get_Option('currency');
            $this->node         = $this->get_option('node');
            $this->testnode     = $this->get_option('testnode');
			$this->temp         = $this->get_option('temp');
			$this->matcherteste = $this->get_option('matcherteste');
			$this->testnetAsset = $this->get_option('testnetAsset');
			$this->mainnetAsset = $this->get_option('mainnetAsset');
			$this->configAsset  = $this->get_option('configAsset');


			//configurações do site
			$this->configsessao = $this->get_option('configsessao');
			$this->uso          = $this->get_option("uso");
			$this->queryButton  = $this->get_option('queryButton');


			//banco de dados sql
			$this->url          = $this->get_option('url');
			$this->sqluser      = $this->get_option('sqluser');
			$this->sqlpass      = $this->get_option('sqlpass');
			$this->tabela       = $this->get_option('tabela');
			$this->tabelanome   = $this->get_option('tabelanome');
			$this->sqlsessao    = $this->get_option('sqlsessao');

			//mainnet - 1 a 10 tokens

			//token1
			$this->token1m      = $this->get_option('token1m');
			$this->fee1m          = $this->get_option('fee1m');
			$this->assetName1m    = $this->get_option('assetName1m');
			$this->assetId1m      = $this->get_option('assetId1m');
			$this->decimals1m     = $this->get_option('decimals1m');

			//token2
			$this->token2m      = $this->get_option('token2m');
			$this->fee2m          = $this->get_option('fee2m');
			$this->assetName2m    = $this->get_option('assetName2m');
			$this->assetId2m      = $this->get_option('assetId2m');
			$this->decimals2m     = $this->get_option('decimals2m');

			//token3
			$this->token3m      = $this->get_option('token3m');
			$this->fee3m          = $this->get_option('fee3m');
			$this->assetName3m    = $this->get_option('assetName3m');
			$this->assetId3m      = $this->get_option('assetId3m');
			$this->decimals3m     = $this->get_option('decimals3m');

			//token4
			$this->token4m      = $this->get_option('token4m');
			$this->fee4m          = $this->get_option('fee4m');
			$this->assetName4m    = $this->get_option('assetName4m');
			$this->assetId4m      = $this->get_option('assetId4m');
			$this->decimals4m     = $this->get_option('decimals4m');

			//token5
			$this->token5m      = $this->get_option('token5m');
			$this->fee5m          = $this->get_option('fee5m');
			$this->assetName5m    = $this->get_option('assetName5m');
			$this->assetId5m      = $this->get_option('assetId5m');
			$this->decimals5m     = $this->get_option('decimals5m');

			//token6
			$this->token6m      = $this->get_option('token6m');
			$this->fee6m          = $this->get_option('fee6m');
			$this->assetName6m    = $this->get_option('assetName6m');
			$this->assetId6m      = $this->get_option('assetId6m');
			$this->decimals6m     = $this->get_option('decimals6m');

			//token7
			$this->token7m      = $this->get_option('token7m');
			$this->fee7m          = $this->get_option('fee7m');
			$this->assetName7m    = $this->get_option('assetName7m');
			$this->assetId7m      = $this->get_option('assetId7m');
			$this->decimals7m     = $this->get_option('decimals7m');

			//token8
			$this->token8m      = $this->get_option('token8m');
			$this->fee8m          = $this->get_option('fee8m');
			$this->assetName8m    = $this->get_option('assetName8m');
			$this->assetId8m      = $this->get_option('assetId8m');
			$this->decimals8m     = $this->get_option('decimals8m');

			//token9
			$this->token9m      = $this->get_option('token9m');
			$this->fee9m          = $this->get_option('fee9m');
			$this->assetName9m    = $this->get_option('assetName9m');
			$this->assetId9m      = $this->get_option('assetId9m');
			$this->decimals9m     = $this->get_option('decimals9m');

			//token10
			$this->token10m      = $this->get_option('token10m');
			$this->fee10m          = $this->get_option('fee10m');
			$this->assetName10m    = $this->get_option('assetName10m');
			$this->assetId10m      = $this->get_option('assetId10m');
			$this->decimals10m     = $this->get_option('decimals10m');			  

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  
			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_offline_form_fields', array(

				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'waves-payment' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Waves Payment', 'waves-payment' ),
					'default' => 'yes'
				),
                
				//config para o site

				'configsessao' => array(
					'title'       => __( 'Config.', 'waves-payment' ),
					'type'        => 'title',
					'description' => __( 'Configurações para definir titulo, descrição, intruções a serem enviadas para e-mail do comprador, Total de Assets, endereço da blockchain da Waves ao qual irá receber o pagamento de valores da loja, ativar/desativar modo testnet, query do botão finalizar, tempo máximo para o pagamento em segundos ( temporizador ), url do nó testnet e url do nó mainnet. Altere somente se souber o que está fazendo.', 'waves-payment' ),
					'desc_tip'    => true,
				),

				'title' => array(
					'title'       => __( 'Title', 'waves-payment' ),
					'type'        => 'text',
					'description' => __( 'Isso controla o título da forma de pagamento que o cliente vê durante a finalização da compra.', 'waves-payment' ),
					'default'     => __( 'Waves Payment', 'waves-payment' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Descrição da forma de pagamento que o cliente verá em sua finalização da compra.', 'waves-payment' ),
					'default'     => __( '', 'waves-payment' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Instruções que serão adicionadas à página de agradecimento e aos e-mails.', 'waves-payment' ),
					'default'     => '',
					'desc_tip'    => true,
				),

				'address' => array(
					'title'       => __( 'Endereço Waves', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Endereço Waves ao qual irá receber os pagamentos da loja.', 'waves-payment' ),
					'default'     => '',
					'desc_tip'    => true,
				),

				'mode' => array(
					'title'       => __( 'Modo de operação', 'waves-payment' ),
                    'label'       => 'Ativar modo TESTNET',
					'type'        => 'checkbox',
					'description' => __( 'Defina o modo de operação para pagamento na blockchain. Para testes na plataforma de pagamento coloque "TESTNET", para pagamento final coloque "MAINNET"', 'waves-payment' ),
					'default'     => 'yes',
					'desc_tip'    => true,
				),

				'currency' => array(
					'title'       => __( 'Moeda fiduciaria a ser utilizada', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque abaixo a moeda fiduciaria a ser utilizada para a conversão de valores. ( BRL, USD, EUR, RUP, NGN, JPY, RMB )', 'waves-payment' ),
					'default'     => 'BRL',
					'desc_tip'    => true,
				),

				'uso' => array(
					'title'       => __('Total de Assets', 'waves-payment'),
					'type'        => 'textarea',
					'description' => __('Coloque a quantidade de token a ser aceita como forma de pagamento em seu website. O limite máximo de token é 10', 'waves-payment' ),
					'default'     => '1',
					'desc_tip'    => true,
				),

				'queryButton' => array(
					'title'       => __( 'Query do botão finalizar', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque aqui os dados query do DOM do botão de finalizar para a orientação do próprio. Esse dado é necessário para caso o layout customizado não conduza com o query usado. O padrão é `button[type="submit"]#place_order`', 'waves-payment' ),
					'default'     => 'button[type="submit"]#place_order',
					'desc_tip'    => true,
				),
				
				'temp' => array(
					'title'       => __( 'Temporizador', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque abaixo o tempo necessáro para efetuar o pagamento. O tempo tem que ser colocado em segundos, o padrão é 20 minutos (1200 segundos).', 'waves-payment' ),
					'default'     => '1200',
					'desc_tip'    => true,
				),

				'testnode' => array(
					'title'       => __( 'Testnet Node URL', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'URL do nó rest Testnet da Waves Blockchain.  NÃO ALTERE CASO NÃO SAIBA O QUE ESTÁ FAZENDO.', 'waves-payment' ),
					'default'     => 'https://nodes-testnet.wavesnodes.com/',
					'desc_tip'    => true,
				),

				'node' => array(
					'title'       => __( 'Mainnet Node URL', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'URL do nó rest Mainnet da Waves Blockchain.  NÃO ALTERE CASO NÃO SAIBA O QUE ESTÁ FAZENDO.', 'waves-payment' ),
					'default'     => 'https://nodes.wavesnodes.com/',
					'desc_tip'    => true,
				),

                //Configurações de assets
				'configAsset' => array(
					'title'       => __( 'Configurações dos Assets', 'waves-payment' ),
					'type'        => 'title',
					'description' => __( 'Preencha abaixo todos os campos para a quantidade de token que sera usada, a qual precisa ser definida nas configurações, para a rede escolhida ( mainnet ou testnet ), fee ( quantia definida para o token no Sponsorship ), decimals ( quantidade de casas decimais de cada token ), nome ( nome ou sigla do token ) e assetId ( id do asset na blockchain ) para o modo de operação escolhido. AVISO: Para o matcher, use SOMENTE moedas com pares USDN e que tenham ordens de compra e venda próximas. Exemplo: EGG/USDN ou BTC/USDN', 'waves-payment' ),
					'desc_tip'    => true,
				),

				//token1
				'token1m' => array(
					'title'       => __( 'Token 1', 'waves-payment' ),
					'type'        => 'title',
					'desc_tip'    => true,
				),

				'assetId1m' => array(
					'title'       => __( 'Asset Id', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Id do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'WAVES',
					'desc_tip'    => true,
				),

				'assetName1m' => array(
					'title'       => __( 'Nome do Asset', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Nome do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'Waves',
					'desc_tip'    => true,
				),

				'decimals1m' => array(
					'title'       => __( 'Decimais do Token', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade de decimais existente no token ao qual será usado.', 'waves-payment' ),
					'default'     => '8',
					'desc_tip'    => true,
				),

				'fee1m' => array(
					'title'       => __( 'Fee token Sponsorship', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade que será paga como taxa Sponsorship para transferencia de valores, como devolução ou pagamento para a conta matriz. A taxa da moeda Waves é 0.001', 'waves-payment' ),
					'default'     => '0.001',
					'desc_tip'    => true,
				),

				//token2
				'token2m' => array(
					'title'       => __( 'Token 2', 'waves-payment' ),
					'type'        => 'title',
					'desc_tip'    => true,
				),

				'assetId2m' => array(
					'title'       => __( 'Asset Id', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Id do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'WAVES',
					'desc_tip'    => true,
				),

				'assetName2m' => array(
					'title'       => __( 'Nome do Asset', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Nome do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'Waves',
					'desc_tip'    => true,
				),

				'decimals2m' => array(
					'title'       => __( 'Decimais do Token', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade de decimais existente no token ao qual será usado.', 'waves-payment' ),
					'default'     => '8',
					'desc_tip'    => true,
				),

				'fee2m' => array(
					'title'       => __( 'Fee token Sponsorship', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade que será paga como taxa Sponsorship para transferencia de valores, como devolução ou pagamento para a conta matriz. A taxa da moeda Waves é 0.001', 'waves-payment' ),
					'default'     => '0.001',
					'desc_tip'    => true,
				),

				//token3
				'token3m' => array(
					'title'       => __( 'Token 3', 'waves-payment' ),
					'type'        => 'title',
					'desc_tip'    => true,
				),

				'assetId3m' => array(
					'title'       => __( 'Asset Id', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Id do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'WAVES',
					'desc_tip'    => true,
				),

				'assetName3m' => array(
					'title'       => __( 'Nome do Asset', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Nome do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'Waves',
					'desc_tip'    => true,
				),

				'decimals3m' => array(
					'title'       => __( 'Decimais do Token', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade de decimais existente no token ao qual será usado.', 'waves-payment' ),
					'default'     => '8',
					'desc_tip'    => true,
				),

				'fee3m' => array(
					'title'       => __( 'Fee token Sponsorship', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade que será paga como taxa Sponsorship para transferencia de valores, como devolução ou pagamento para a conta matriz. A taxa da moeda Waves é 0.001', 'waves-payment' ),
					'default'     => '0.001',
					'desc_tip'    => true,
				),

				//token4
				'token4m' => array(
					'title'       => __( 'Token 4', 'waves-payment' ),
					'type'        => 'title',
					'desc_tip'    => true,
				),

				'assetId4m' => array(
					'title'       => __( 'Asset Id', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Id do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'WAVES',
					'desc_tip'    => true,
				),

				'assetName4m' => array(
					'title'       => __( 'Nome do Asset', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Nome do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'Waves',
					'desc_tip'    => true,
				),

				'decimals4m' => array(
					'title'       => __( 'Decimais do Token', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade de decimais existente no token ao qual será usado.', 'waves-payment' ),
					'default'     => '8',
					'desc_tip'    => true,
				),

				'fee4m' => array(
					'title'       => __( 'Fee token Sponsorship', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade que será paga como taxa Sponsorship para transferencia de valores, como devolução ou pagamento para a conta matriz. A taxa da moeda Waves é 0.001', 'waves-payment' ),
					'default'     => '0.001',
					'desc_tip'    => true,
				),

				//token5
				'token5m' => array(
					'title'       => __( 'Token 5', 'waves-payment' ),
					'type'        => 'title',
					'desc_tip'    => true,
				),

				'assetId5m' => array(
					'title'       => __( 'Asset Id', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Id do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'WAVES',
					'desc_tip'    => true,
				),

				'assetName5m' => array(
					'title'       => __( 'Nome do Asset', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Nome do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'Waves',
					'desc_tip'    => true,
				),

				'decimals5m' => array(
					'title'       => __( 'Decimais do Token', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade de decimais existente no token ao qual será usado.', 'waves-payment' ),
					'default'     => '8',
					'desc_tip'    => true,
				),

				'fee5m' => array(
					'title'       => __( 'Fee token Sponsorship', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade que será paga como taxa Sponsorship para transferencia de valores, como devolução ou pagamento para a conta matriz. A taxa da moeda Waves é 0.001', 'waves-payment' ),
					'default'     => '0.001',
					'desc_tip'    => true,
				),

				//token6
				'token6m' => array(
					'title'       => __( 'Token 6', 'waves-payment' ),
					'type'        => 'title',
					'desc_tip'    => true,
				),

				'assetId6m' => array(
					'title'       => __( 'Asset Id', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Id do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'WAVES',
					'desc_tip'    => true,
				),

				'assetName6m' => array(
					'title'       => __( 'Nome do Asset', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Nome do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'Waves',
					'desc_tip'    => true,
				),

				'decimals6m' => array(
					'title'       => __( 'Decimais do Token', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade de decimais existente no token ao qual será usado.', 'waves-payment' ),
					'default'     => '8',
					'desc_tip'    => true,
				),

				'fee6m' => array(
					'title'       => __( 'Fee token Sponsorship', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade que será paga como taxa Sponsorship para transferencia de valores, como devolução ou pagamento para a conta matriz. A taxa da moeda Waves é 0.001', 'waves-payment' ),
					'default'     => '0.001',
					'desc_tip'    => true,
				),

				//token7
				'token7m' => array(
					'title'       => __( 'Token 7', 'waves-payment' ),
					'type'        => 'title',
					'desc_tip'    => true,
				),

				'assetId7m' => array(
					'title'       => __( 'Asset Id', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Id do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'WAVES',
					'desc_tip'    => true,
				),

				'assetName7m' => array(
					'title'       => __( 'Nome do Asset', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Nome do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'Waves',
					'desc_tip'    => true,
				),

				'decimals7m' => array(
					'title'       => __( 'Decimais do Token', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade de decimais existente no token ao qual será usado.', 'waves-payment' ),
					'default'     => '8',
					'desc_tip'    => true,
				),

				'fee7m' => array(
					'title'       => __( 'Fee token Sponsorship', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade que será paga como taxa Sponsorship para transferencia de valores, como devolução ou pagamento para a conta matriz. A taxa da moeda Waves é 0.001', 'waves-payment' ),
					'default'     => '0.001',
					'desc_tip'    => true,
				),

				//token8
				'token8m' => array(
					'title'       => __( 'Token 8', 'waves-payment' ),
					'type'        => 'title',
					'desc_tip'    => true,
				),

				'assetId8m' => array(
					'title'       => __( 'Asset Id', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Id do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'WAVES',
					'desc_tip'    => true,
				),

				'assetName8m' => array(
					'title'       => __( 'Nome do Asset', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Nome do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'Waves',
					'desc_tip'    => true,
				),

				'decimals8m' => array(
					'title'       => __( 'Decimais do Token', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade de decimais existente no token ao qual será usado.', 'waves-payment' ),
					'default'     => '8',
					'desc_tip'    => true,
				),

				'fee8m' => array(
					'title'       => __( 'Fee token Sponsorship', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade que será paga como taxa Sponsorship para transferencia de valores, como devolução ou pagamento para a conta matriz. A taxa da moeda Waves é 0.001', 'waves-payment' ),
					'default'     => '0.001',
					'desc_tip'    => true,
				),

				//token9
				'token9m' => array(
					'title'       => __( 'Token 9', 'waves-payment' ),
					'type'        => 'title',
					'desc_tip'    => true,
				),

				'assetId9m' => array(
					'title'       => __( 'Asset Id', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Id do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'WAVES',
					'desc_tip'    => true,
				),

				'assetName9m' => array(
					'title'       => __( 'Nome do Asset', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Nome do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'Waves',
					'desc_tip'    => true,
				),

				'decimals9m' => array(
					'title'       => __( 'Decimais do Token', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade de decimais existente no token ao qual será usado.', 'waves-payment' ),
					'default'     => '8',
					'desc_tip'    => true,
				),

				'fee9m' => array(
					'title'       => __( 'Fee token Sponsorship', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade que será paga como taxa Sponsorship para transferencia de valores, como devolução ou pagamento para a conta matriz. A taxa da moeda Waves é 0.001', 'waves-payment' ),
					'default'     => '0.001',
					'desc_tip'    => true,
				),

				//token10
				'token10m' => array(
					'title'       => __( 'Token 10', 'waves-payment' ),
					'type'        => 'title',
					'desc_tip'    => true,
				),

				'assetId10m' => array(
					'title'       => __( 'Asset Id', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Id do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'WAVES',
					'desc_tip'    => true,
				),

				'assetName10m' => array(
					'title'       => __( 'Nome do Asset', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Nome do ativo ao qual deseja aceitar como pagamento.', 'waves-payment' ),
					'default'     => 'Waves',
					'desc_tip'    => true,
				),

				'decimals10m' => array(
					'title'       => __( 'Decimais do Token', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade de decimais existente no token ao qual será usado.', 'waves-payment' ),
					'default'     => '8',
					'desc_tip'    => true,
				),

				'fee10m' => array(
					'title'       => __( 'Fee token Sponsorship', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque a quantidade que será paga como taxa Sponsorship para transferencia de valores, como devolução ou pagamento para a conta matriz. A taxa da moeda Waves é 0.001', 'waves-payment' ),
					'default'     => '0.001',
					'desc_tip'    => true,
				),				

				//sql banco de dados

				'sqlsessao' => array(
					'title'       => __( 'Banco de dados SQL', 'waves-payment' ),
					'type'        => 'title',
					'description' => __( 'Coloque abaixo todos os dados para o acesso do seu banco de dados SQL.', 'waves-payment' ),
					'desc_tip'    => true,
				),

				'url' => array(
					'title'       => __( 'sql url', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque abaixo a url do seu banco de dados sql.', 'waves-payment' ),
					'default'     => 'localhost',
					'desc_tip'    => true,
				),

				'sqluser' => array(
					'title'       => __( 'usuário sql', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque abaixo o usuário admin do seu banco de dados sql.', 'waves-payment' ),
					'default'     => 'root',
					'desc_tip'    => true,
				),

				'sqlpass' => array(
					'title'       => __( 'senha sql', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque abaixo a senha do usuário admin do seu banco de dados sql.', 'waves-payment' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				
				'tabela' => array(
					'title'       => __( 'banco de dados sql', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque abaixo o nome do banco de dados ao qual irá ficar os endereços e as seed phrases dos endereços que iram ser gerados aletóriamente para pagamento.', 'waves-payment' ),
					'default'     => 'bancodeteste',
					'desc_tip'    => true,
				),

				'tabelanome' => array(
					'title'       => __( 'tabela banco de dados sql', 'waves-payment' ),
					'type'        => 'textarea',
					'description' => __( 'Coloque abaixo o nome da tabela do banco de dados ao qual irá ficar os endereços e as seed phrases dos endereços que iram ser gerados aletóriamente para pagamento.', 'waves-payment' ),
					'default'     => 'all_address',
					'desc_tip'    => true,
				)
			) );
		}

		public function payment_fields() {
			//script necessários
			echo "<script src='https://unpkg.com/waves-api@0.24.2/dist/waves-api.min.js'></script><script src='https://momentjs.com/downloads/moment.js'></script>";
			echo '<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
			// ok, let's display some description before the payment form
			// if ( $this->description ) {
				//price
				$base58t = new Base58();
				$amount2 = WC()->cart->total;
				//create account for user
				$wk = new WavesKit($this->mode != 'no' ? "T" : "W");
				$account = $wk->randomSeed();
				$wk->setSeed($account);
				$binaryAddress = $wk->getAddress(true);
				$waddress = $base58t->encode($binaryAddress);
				// you can instructions for test mode, I mean test card numbers etc.
					$this->description  = trim( $this->description );
					echo "
					<script>
					console.log(`{$waddress}`)
					//variaveis globais
					var decimals;
					var ddc;
					var dd;
					var contentAdd;
					var topay;
					var addsss = '{$this->address}';
					var fiduciaria = '{$this->currency}'
					var mode = '{$this->mode}' != 'no' ? WavesAPI.TESTNET_CONFIG : WavesAPI.MAINNET_CONFIG;
					var Waves = WavesAPI.create(mode);
					var newConfig = {

						// The byte allowing to distinguish networks (mainnet, testnet, devnet, etc)
						networkByte: '{$this->mode}' != 'no' ? Waves.constants.TESTNET_BYTE  : Waves.constants.MAINNET_BYTE ,
					
						// Node and Matcher addresses, no comments here
						nodeAddress: '{$this->mode}' != 'no' ? '{$this->testnode}' : '{$this->node}',
						matcherAddress: '{$this->mode}' != 'no' ? 'https://matcher-testnet.waves.exchange/matcher' : 'https://matcher.waves.exchange/matcher',
					
						// If a seed phrase length falls below that value an error will be thrown
						minimumSeedLength: 50
					
					};
					Waves.config.set(newConfig);
					var seed = Waves.Seed.fromExistingPhrase('{$account}')
					var address = seed.address;
					$('#address').text(address);
					$('#imga').css('background-image','url(https://chart.googleapis.com/chart?chs=500x500&cht=qr&chl=' + address + ')' );
					$(window).on('unload',function(){
						$('{$this->queryButton}').click();
					}); 
					das({$this->decimals1m});
					dd = {
						decimals: '{$this->decimals1m}',
						id: '{$this->assetId1m}',
					}
					topay = '{$amount2}';
					pricepay(dd)			
                    startTimer({$this->temp});
					ddc = {
						id: '{$this->assetId1m}',
						address: '{$this->address}',
						fee: {$this->fee1m},
						name: '{$this->assetName1m}',
						query: '{$this->queryButton}'
					}
					contentAdd = [{
						id: '{$this->assetId1m}',
						fee: '{$this->fee1m}',
						name: '{$this->assetName1m}',
						decimals: '{$this->decimals1m}',
						query: '{$this->queryButton}'

					},{
						id: '{$this->assetId2m}',
						fee: '{$this->fee2m}',
						name: '{$this->assetName2m}',
						decimals: '{$this->decimals2m}',
						query: '{$this->queryButton}'

					},{
						id: '{$this->assetId3m}',
						fee: '{$this->fee3m}',
						name: '{$this->assetName3m}',
						decimals: '{$this->decimals3m}',
						query: '{$this->queryButton}'

					},{
						id: '{$this->assetId4m}',
						fee: '{$this->fee4m}',
						name: '{$this->assetName4m}',
						decimals: '{$this->decimals4m}',
						query: '{$this->queryButton}'

					},{
						id: '{$this->assetId5m}',
						fee: '{$this->fee5m}',
						name: '{$this->assetName5m}',
						decimals: '{$this->decimals5m}',
						query: '{$this->queryButton}'

					},{
						id: '{$this->assetId6m}',
						fee: '{$this->fee6m}',
						name: '{$this->assetName6m}',
						decimals: '{$this->decimals6m}',
						query: '{$this->queryButton}'

					},{
						id: '{$this->assetId7m}',
						fee: '{$this->fee7m}',
						name: '{$this->assetName7m}',
						decimals: '{$this->decimals7m}',
						query: '{$this->queryButton}'

					},{
						id: '{$this->assetId8m}',
						fee: '{$this->fee8m}',
						name: '{$this->assetName8m}',
						decimals: '{$this->decimals8m}',
						query: '{$this->queryButton}'

					},{
						id: '{$this->assetId9m}',
						fee: '{$this->fee9m}',
						name: '{$this->assetName9m}',
						decimals: '{$this->decimals9m}',
						query: '{$this->queryButton}'

					},{
						id: '{$this->assetId10m}',
						fee: '{$this->fee10m}',
						name: '{$this->assetName10m}',
						decimals: '{$this->decimals10m}',
						query: '{$this->queryButton}'

					}];
					addsss = '{$this->address}'
					contSelect(contentAdd, '{$this->uso}')
					verificar(ddc)
					</script>";
					$conn = mysqli_connect($this->url, $this->sqluser, $this->sqlpass, $this->tabela);
					if (!$conn) {
						die("Connection failed: " . mysqli_connect_error());
				    }
					$tn = $this->tabelanome;
					$sql = "INSERT INTO $tn (address, seed) VALUES ('{$waddress}', '{$account}')";
					mysqli_query($conn,$sql)or die (mysqli_error($conn));
					mysqli_close($conn);

				echo wpautop( wp_kses_post( $this->description ) );

			// I will echo() the form, but you can close PHP tags and print it directly in HTML
			echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
		 
			// Add this action hook if you want your custom payment gateway to support it
			do_action( 'woocommerce_credit_card_form_start', $this->id );
		 
			// I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
			echo "<div class='form-row form-row-wide'>
				   <select id='contSelect' name='moedas' onchange='javascript:muddd(this)' style='border-radius: 10px; border: 1px solid #e0e0e0; box-shadow: -2px 2px 2px 0px #e0e0e0; background: #fff; padding: 10px; color: #333;'>
				   </select>
				   <div a1 id='contentAllSec' style='flex-direction: column;'>
				   <div a1 id='imga' style='width: 100%; height:; background-position: center; background-size: contain; background-repeat: no-repeat;'></div>
				   <p a1 id='valor' style='width: 100%; padding: 10px; font-size: 20px; display: flex; align-items: center; flex-direction: column;'></p>
				   <p a1 id='address' onclick='copy(this)' onselect='copy(this)' style='color: #333; word-break: break-all; word-wrap: break-word; text-align: center;padding-top:10px;font-size:16px'></p>
				   <p a1 id='contador' style='wwidth: 100%;
				   padding: 10px;
				   font-size: 20px;
				   display: flex;
				   align-items: center;
				   flex-direction: column;
				   text-align: center;'></p>
				   </div>
				   <p a2 style='display: none; height: 200px; border: 3px solid #e0e0e0; border-radius: 10px; border-style: dotted; margin-top: 20px; padding: 10px; flex-direction: row; flex-wrap: nowrap; align-content: center; justify-content: center; align-items: center; text-align: center;'>Preencha todos os campos necessários para realizar o pagamento.</p>
				</div>
				<div class='clear'></div>";
		 
			do_action( 'woocommerce_credit_card_form_end', $this->id );
		 
			echo "<div class='clear'></div></fieldset>";
		 
		}

		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}
	
	
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
			  echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
			}
		  }
	
	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {

				if($_COOKIE['tx']){
					$order = new WC_Order( $order_id ); 

					// The text for the note
					$note = __("TX: {$_COOKIE['tx']}, Address: {$_COOKIE['address']}, Seed Phrase: {$_COOKIE['seed']}");
				   
					// Add the note
					$order->add_order_note( $note );
				   
					// Save the data
					$order->save();			
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'completed', __( 'Pagamento realizado com sucesso!', 'waves-payment' ) );
			
			// Reduce stock levels
			// $order->wc_reduce_stock_levels();
			
			// Remove cart
			WC()->cart->empty_cart();
			
			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url( $order )
			);
				}else{
					echo '<script>Swal.fire("Pagamento pendente", "Realize o pagamento e espere ele ser confirmado no site, não saia nem feche a página caso esteja esperando um pagamento pendente.","warning")</script>';
				};		

		}
	
  } // end 
}