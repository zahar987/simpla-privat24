<?php

/**
 * Simpla CMS
 *
 * @author 		Dmitry Zakharov
 *
 * К этому скрипту обращается Privat24 в процессе оплаты
 *
 */

// Работаем в корневой директории
chdir ('../../');
require_once('api/Simpla.php');
$simpla = new Simpla();

$simpla->db->query("SELECT id FROM __payment_methods WHERE module = 'privat24' AND ENABLED = 1 ORDER BY position ASC LIMIT 1");
$privat24_id = intval($simpla->db->result('id'));

if (!$privat24_id) {
	die('Privat24 payment module is not configured!');
}



$settings = $simpla->payment->get_payment_settings($privat24_id);

$pass = trim($settings['privat24_pass']);
$signature = sha1(md5(htmlspecialchars_decode($_POST['payment'], ENT_QUOTES)  . $pass));

$info = explode('&', htmlspecialchars_decode($_POST['payment'], ENT_QUOTES));
foreach ($info as $value) {
	$z = explode('=', $value);
	$data[$z[0]] = $z[1];
} 


if($signature == $_POST['signature'] AND 'ok' == $data["state"]) {
	$order_info = $simpla->orders->get_order(intval($data['order']));

	if ( empty($order_info)) {
		die('ERROR:  нет такого заказа!');
	}

	if($order_info->paid) {
		die('Этот заказ уже оплачен');
	}

	// Установим статус оплачен
	$simpla->orders->update_order(intval($order_info->id), array('paid'=>1));

	// Отправим уведомление на email
	$simpla->notify->email_order_user(intval($order_info->id));
	$simpla->notify->email_order_admin(intval($order_info->id));

	// Спишем товары  
	$simpla->orders->close(intval($order_info->id));

	// Перенаправим пользователя на страницу заказа
	header('Location: '.$simpla->request->root_url.'/order/'.$order_info->url);

	exit();
} else {		
	die('ERROR: не совпадает crc!');
}