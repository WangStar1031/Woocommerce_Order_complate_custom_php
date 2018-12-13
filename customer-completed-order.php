<?php
/**
 * Customer completed order email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/customer-completed-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates/Emails/Plain
 * @version 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo '= ' . esc_html( $email_heading ) . " =\n\n";

/* translators: %s: Customer first name */
echo sprintf( esc_html__( 'Hi %s,', 'woocommerce' ), esc_html( $order->get_billing_first_name() ) ) . "\n\n";
/* translators: %s: Site title */
echo sprintf( esc_html__( 'Your %s order has been marked complete on our side.', 'woocommerce' ), esc_html( wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) ) ) . "\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

function makeEncryptKey($_keyword){
	if( $_keyword == "")return "";
	$_key1 = crypt(time(), "");
	$_key2 = crypt($_keyword, "");
	$_key3 = "";//crypt(date("Ymd"), "");
	$key =  $_key1 . $_key2 . $_key3;
	$key = str_replace("$", "", $key);
	$key = str_replace(".", "", $key);
	$key = str_replace("/", "", $key);
	return $key;
}
function makeOneProduct($_productInfo, $_preExp){
		$product = new \stdClass;
		$product->product_cat = $_productInfo->cat_name;
		$product->productName = $_productInfo->name;
		$product->token = $_productInfo->token;
		$quantity = intval($_productInfo->quantity);
		if( strcasecmp( $_productInfo->cat_name, "Subscription") == 0){
			$year = intval($quantity / 10);
			$expMonth = $year * 12 + $quantity - $year * 10;
			$expPeriod = " + " . $expMonth . " month";
			if( $_preExp != null){
				$product->limit = date("Y-m-d", strtotime($_preExp . $expPeriod));
			} else{
				$product->limit = date("Y-m-d", strtotime(date("Y-m-d") . $expPeriod));
			}
		} else{
			$count_per_buying = 20;
			if( $_preExp != null){
				$product->limit = $count_per_buying * $quantity + $_preExp;
			} else{
				$product->limit = $count_per_buying * $quantity;
			}
			$product->unlimited = false;
		}
		return $product;
}

function updateUser( $_eMail, $_lstToken){
	$fName = $_SERVER['DOCUMENT_ROOT'] . "/JRA/logs/users/" . $_eMail;
	$contents = file_get_contents($fName);
	$user = json_decode($contents);
	$productDetails = [];
	$arrProducts = $user->productDetails;
	$arrRetVal = [];
	foreach ($_lstToken as $tokenVal) {
		$isInclude = false;
		$limit = null;
		foreach ($arrProducts as $value) {
			if( strcasecmp( $tokenVal->name, $value->productName) == 0 && strcasecmp($tokenVal->cat_name, $value->product_cat) == 0 ){
				$limit = $value->limit;
				$newVal = makeOneProduct($tokenVal, $limit);
				$value->token = $newVal->token;
				$value->limit = $newVal->limit;
				$arrRetVal[] = $newVal;
				$isInclude = true;
			}
		}
		if( $isInclude == false){
			$newVal = makeOneProduct($tokenVal, null);
			$arrProducts[] = $newVal;
			$arrRetVal[] = $newVal;
		}
	}
	unlink($fName);
	file_put_contents($fName, json_encode($user));
	return $arrRetVal;
}
function registerUser( $_firstName, $_lastName, $_eMail, $_lstToken){
	$fName = $_SERVER['DOCUMENT_ROOT'] . "/JRA/logs/users/" . $_eMail;
	if( file_exists($fName)){
		return updateUser($_eMail, $_lstToken);
	}
	$user = new \stdClass;
	$user->firstName = $_firstName;
	$user->lastName = $_lastName;
	$user->eMail = $_eMail;
	$user->userPass = $_eMail;
	$user->productDetails = [];
	foreach ($_lstToken as $value) {
		$user->productDetails[] = makeOneProduct($value, null);
	}
	file_put_contents($fName, json_encode($user));
	return $user->productDetails;
}

$first_name = $order->get_billing_first_name();
$last_name = $order->get_billing_last_name();
$email_address = $order->billing_email;

$items = $order->get_items();
$lstProductItems = [];
foreach ( $items as $item ) {
	$productItem = new \stdClass();
    $product_name = $item->get_name();
	$product_id = $item->get_product_id();
	$item_quantity = $item->get_quantity();
	$item_total = $item->get_total();
	$terms = wp_get_post_terms( $product_id, 'product_cat');
	$product_cat_name = "";
	if( count($terms)){
		$product_cat_name = $terms[0]->name;
	}
	if( $product_cat_name != ""){
		$product_cat_name = trim($product_cat_name);
	}
	$productItem->name = $product_name;
	$productItem->quantity = $item_quantity;
	$productItem->cat_name = $product_cat_name;
	$keyword = $product_cat . $product_name . $email_address;
	$token = makeEncryptKey($keyword);
	$productItem->token = $token;
	$lstProductItems[] = $productItem;
}

$products = registerUser($first_name, $last_name, $email_address, $lstProductItems);
foreach ($products as $value) {
	echo "Product Name : " . $value->productName . ", Product Category : " . $value->product_cat . ", limit: " . $value->limit . ", Token : " . $value->token . "\n";
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

echo esc_html__( 'Thanks for shopping with us.', 'woocommerce' ) . "\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
