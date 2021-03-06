<?php
/*
Attributes:
	raw_data -> For Logging of raw request from Telegram servers(users)
	decoded_data -> making them available for further process (group/supergroup events)
	type -> define which kind of request has been sent to the BOT
	
	method -> Depends on the response processed in processText() method (sendMessage for example)
	action -> related to method. Depends on the response processed. (typing, find_location etc)
	
	isInlineQuery -> boolean value, indicating if current request is an Inline type or Regular.
	inlineQuery -> contains all the field inside inline_query , coming from Telegram Servers
*/
include 'getFile.php';

class TelegramBot
{
	private $API_URL = 'https://api.telegram.org/bot';
	private $TOKEN = 'YOUR_TOKEN_HERE';
	
	
	protected $raw_data; //Json Data
	protected $decoded_data; //Json-Decoded Data
	protected $isInlineQuery = false;	//Initialized to false. Used for checking if incoming query requires inline processing
	protected $inlineQuery;
	
	protected $method;
	protected $action;
	protected $type;
	
	private $fh;
	private $query;
	private $result;
	
	
	//
	// MAGIC METHODS
	//
	
	//On Instantiation, get Webhook data and define the kind of response to give back
	public function __construct()
	{
		//get data from Webhook call
		$this->raw_data = file_get_contents("php://input");
		//decode data
		$this->decoded_data = json_decode($this->raw_data, true);
		//Set attributes and Default actions
		$this->method = "sendMessage";
		$this->action = "typing";
		//Get data
		if (isset($this->decoded_data["message"]["text"]))
		{	//Get data and type (photo , text or other)
			$this->text = $this->decoded_data["message"]["text"];
			$this->type = "text";
		}else if(isset($this->decoded_data["message"]["photo"]))
		{
			$this->type = "photo";
		}else if(isset($this->decoded_data["message"]["location"]))
		{
			$this->type = "location";	
		}else if(isset($this->decoded_data["message"]["video"]))
		{
			$this->type = "video";
		}else{
			$this->type = "unknown";
		}
		//Inline query or regular query ?
		if (isset($this->decoded_data["inline_query"])) 
		{	//inline_query is present instead of message field in case of an inline request
			$this->isInlineQuery = isset($this->decoded_data["inline_query"]) ? true : false;
			$this->inlineQuery = $this->decoded_data["inline_query"];	//Contains id,from,query and offset
		}
	}
	
	//To define what to do when unset($this)
	public function __destruct()
	{
		//Log what's happened
		$this->fh = new logger("newdebug.txt");
		$this->fh->lwrite("Request:\n".$this->raw_data);
		foreach($this->query as $key => $value)
		{
			$string .= $key." => ".$value."\n";
		}
		$this->fh->lwrite("Sent:\n".$string);
		$this->fh->lwrite("Using method:\t".$this->method);
		$this->fh->lwrite("Received: \n".$this->result);
		unset($this->fh);
	}
	
	//when printed, return this
	public function __toString()
	{
		return "Telegram Bot Client coded by Nightfox Nicita";
	}
	
	//
	// SETTERS
	//
	public function setAction($action)
	{	//Custom bot that inherits this class, must set the kind of action to perform.
		$this->action = $action;
		return True;
	}
	
	public function setMethod($method)
	{
		//sendMessage is the default.
		//Inline bots adds answerInlineQuery.
		$this->method = $method;
		return True;
	}
	
	//
	// GETTERS
	//
	public function getReqType()
	{
		return $this->type;
	}
	
	//Get Bot Action
	public function getAction()
	{
		return $this->action;
	}
	
	public function getWebhookCall()
	{
		return $this->raw_data;
	}
	
	public function getInline()
	{	//Returns a boolean value, indicating if incoming query is Inline or not.
		return $this->isInlineQuery;	
	}
	
	
	//
	// METHODS
	//
	
	
	public function getReq($reqArray)
	{	//Retrieve data for processing the response
		$response = array();
		
		foreach($reqArray as $key)
		{
			switch($key)
			{
				case 'chat_id':
					$response['chat_id'] = $this->decoded_data['message']['chat']['id'];
				break;
				
				case 'username':
					$response['username'] = $this->decoded_data['message']['from']['username'];
				break;
				
				case 'text':
					$response['text'] = $this->decoded_data['message']['text'];
				break;
					
				//Inline Bots
				//Results field is required too. It takes an array of InlineQueryResult. Check official API documentation
				//Last response for InlineQuery has: inline_query_id, cache_time, results.
				case 'inline_query_id':
					$response['inline_query_id'] = $this->inlineQuery['id'];
				break;
				
				case 'cache_time':	//Usually must be set by the custom bot
					$response['cache_time'] = 86400;	//Default value set here
				break;
			}
		}
		return $response;
	}
	
	
	//SendChatAction(curl_handler, "action")
	public function sendChatAction($ch, $chat_id)
	{	//send action of the bot(typing, sending data etc..)
		$API_URL = $this->API_URL . $this->TOKEN ."/";
		curl_setopt($ch,CURLOPT_URL, $API_URL . "sendChatAction?chat_id=".urlencode($chat_id)."&action=".$this->action);
		return curl_exec($ch);
	}
	
	//Send BOT Response
	public function sendResponse($ch,$query)
	{
		$this->query = $query;// DEBUG
		$API_URL = $this->API_URL . $this->TOKEN . "/".$this->method;
		curl_setopt($ch,CURLOPT_URL, $API_URL);
		curl_setopt($ch,CURLOPT_POST,true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$query);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch,CURLOPT_TIMEOUT,10000); //10 sec before letting curl post die
		$this->result = curl_exec($ch);
		return $this->result;
	}
	
	
	//Create reply_markup object
	public function getKeyboard($row)
	{	//Creates a keyboard.
		$keyboard = array(
			'keyboard' => array(
				$row
			)
		);
		//Json encode , as API states
		return json_encode($keyboard);
	}
	
	//Emoticon string from unicode
	public function uniToEmoji($unichr)
	{	//Usage \u2b50 for istance , uniToEmoji(0x2B50)
		return iconv('UCS-4LE', 'UTF-8', pack('V', $unichr);
	}
					 
	public function getInlineQueryResult($type,$id,$title,$text)
	{	//"results" field is a regular array of those elements
		$element = array("type" => $type,
						"id" => $id,
						"title" => $title,
						"message_text" => $text);
		return $element;
	}
					 

}


?>
