<?php
/**
 * Jarvis Cinema Bot for Telegram
 * get information about seances from http://kinoafisha.ua/
 *
 *
 * @author T1m_One <barkhatov.inc@gmail.com>
 * @version 1.0
 */
class Cinema {
	public $cinemaList = [
		'ТРЦ Gulliver' => 'http://kinoafisha.ua/cinema/kiev/oskar-v-trc-Gulliver',
		'Ультрамарин' => 'http://kinoafisha.ua/cinema/kiev/batterfljaj-ultramarin',
		'Большевик' => 'http://kinoafisha.ua/cinema/kiev/bolshevik',
		'Блокбастер' => 'http://kinoafisha.ua/cinema/kiev/blokbaster-kino',
		'Sky Mall' => 'http://kinoafisha.ua/cinema/kiev/megapleks',
		'Дрим Таун' => 'http://kinoafisha.ua/cinema/kiev/oscar',
		'Караван' => 'http://kinoafisha.ua/cinema/kiev/multipleks-v-karavane',
		'De Luxe' => 'http://kinoafisha.ua/cinema/kiev/batterfljaj-de-luxe',
		'Украина' => 'http://kinoafisha.ua/cinema/kiev/ukraina-',
		'ТРЦ Украина' => 'http://kinoafisha.ua/cinema/kiev/odessa-kino',
	];
	
	public $message;
		
	public function getCinemaList() {
		return $this->cinemaList;
	}
	
	public function getSeances($cinema) {
		$url = $this->cinemaList[$cinema];
		$ch = curl_init();
		$timeout = 5;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$html = curl_exec($ch);
		curl_close($ch);
		
		$dom = new DOMDocument();
		
		@$dom->loadHTML($html);
		
		$this->message .= trim($dom->getElementsByTagName('h2')->item(0)->nodeValue)." ".trim($dom->getElementsByTagName('h2')->item(0)->parentNode->getElementsByTagName('span')->item(1)->nodeValue)."\n\n";
		
		$tables = $dom->getElementsByTagName('table');
		$trs = $tables->item(1)->getElementsByTagName('tr');
		
		foreach ($trs as $tr) {
		    $tds = $tr->getElementsByTagName('td');
		    $this->message .= trim($tds->item(0)->nodeValue);
		    
		    if ($tds->item(1)) {
			    $this->message .= " ";
			    $spans = $tds->item(1)->getElementsByTagName('span');
			    foreach ($spans as $span) {
					$this->message .= trim($span->nodeValue)." ";
			    }
			    $this->message .= "\n";
		    }
		    $this->message .= "\n";
		}
	}
	
	public function getMessage() {
		return $this->message;
	}
}

define('BOT_TOKEN', '185088638:AAH7orHM69bjf4USKIRtC-aHonpG2jKkOig');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');
define('WEBHOOK_URL', 'https://jarviscinemabot.herokuapp.com/');

function apiRequestWebhook($method, $parameters) {
	if (!is_string($method)) {
		error_log("Method name must be a string\n");
		return false;
	}
	
	if (!$parameters) {
		$parameters = array();
	} else if (!is_array($parameters)) {
		error_log("Parameters must be an array\n");
		return false;
	}
	
	$parameters['method'] = $method;
	
	header("Content-Type: application/json");
	echo json_encode($parameters);
	return true;
}

function exec_curl_request($handle) {
	$response = curl_exec($handle);
	
	if ($response === false) {
		$errno = curl_errno($handle);
		$error = curl_error($handle);
		error_log("Curl returned error $errno: $error\n");
		curl_close($handle);
		return false;
	}
	
	$http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
	curl_close($handle);
	
	if ($http_code >= 500) {
		// do not wat to DDOS server if something goes wrong
		sleep(10);
		return false;
	} else if ($http_code != 200) {
		$response = json_decode($response, true);
		error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
		if ($http_code == 401) {
			throw new Exception('Invalid access token provided');
		}
		return false;
	} else {
		$response = json_decode($response, true);
		if (isset($response['description'])) {
			error_log("Request was successfull: {$response['description']}\n");
		}
		$response = $response['result'];
	}
	
	return $response;
}

function apiRequest($method, $parameters) {
	if (!is_string($method)) {
		error_log("Method name must be a string\n");
		return false;
	}
	
	if (!$parameters) {
		$parameters = array();
	} else if (!is_array($parameters)) {
		error_log("Parameters must be an array\n");
		return false;
	}
	
	foreach ($parameters as $key => &$val) {
		// encoding to JSON array parameters, for example reply_markup
		if (!is_numeric($val) && !is_string($val)) {
			$val = json_encode($val);
		}
	}
	$url = API_URL.$method.'?'.http_build_query($parameters);
	
	$handle = curl_init($url);
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($handle, CURLOPT_TIMEOUT, 60);
	
	return exec_curl_request($handle);
}

function apiRequestJson($method, $parameters) {
	if (!is_string($method)) {
		error_log("Method name must be a string\n");
		return false;
	}
	
	if (!$parameters) {
		$parameters = array();
	} else if (!is_array($parameters)) {
		error_log("Parameters must be an array\n");
		return false;
	}
	
	$parameters['method'] = $method;
	
	$handle = curl_init(API_URL);
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($handle, CURLOPT_TIMEOUT, 60);
	curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
	curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
	
	return exec_curl_request($handle);
}

function processMessage($message) {
	// process incoming message
	$message_id = $message['message_id'];
	$chat_id = $message['chat']['id'];
	if (isset($message['text'])) {
		// incoming text message
		$text = $message['text'];
		
		$cinema = new Cinema;
		
		$keyboard = array();
		
		foreach ($cinema->getCinemaList() as $name => $url) {
			$keyboard[] = array($name);	
		}
	
		if (strpos($text, '/start') === 0) {
			apiRequestJson('sendMessage', array(
				'chat_id' => $chat_id, 
				'text' => "Добро пожаловать!\nЯ бот Jarvis ".json_decode('"\ud83d\ude0e"').", я показываю расписание сеансов в кинотеатрах ".json_decode('"\ud83c\udfa5"')." Киева",
				'reply_markup' => array(
					'keyboard' => $keyboard,
					'one_time_keyboard' => true,
					'resize_keyboard' => true
				)
			));
		} else if (array_key_exists($text, $cinema->getCinemaList())) {
			$cinema->getSeances($text);
			apiRequest('sendMessage', array('chat_id' => $chat_id, 'text' => $cinema->getMessage()));
		} else if (strpos($text, '/stop') === 0) {
			// stop now
		} else {
			apiRequestWebhook('sendMessage', array('chat_id' => $chat_id, 'reply_to_message_id' => $message_id, 'text' => 'Cool'));
		}
	} else {
		apiRequest('sendMessage', array('chat_id' => $chat_id, 'text' => 'I understand only text messages'));
	}
}

if (php_sapi_name() == 'cli') {
	// if run from console, set or delete webhook
	apiRequest('setWebhook', array('url' => isset($argv[1]) && $argv[1] == 'delete' ? '' : WEBHOOK_URL));
	exit;
}

$content = file_get_contents('php://input');
$update = json_decode($content, true);

if (!$update) {
	// receive wrong update, must not happen
	exit;
}

if (isset($update['message'])) {
	processMessage($update['message']);
}