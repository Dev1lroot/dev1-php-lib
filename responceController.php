<?php
	/**
	* @description IndexCMS API Controller Main
	*	@category    API
	*	@package	   IndexCMS
	*	@author	     dev1lroot@protonmail.com
	*	@copyright   2021 David Eichendorf (C)
	*	@license	   AGPL 3.0
	*	@version	   4.1
	*/
	class API
	{
		var $action;
		var $access;
		var $status;
		var $errors;
		var $fields;
		var $get;
		var $post;
		var $admin;
		var $timing;
		function __construct($action,$get,$post)
		{
			ob_start();
			$this->result = [];
			$this->errors = [];
			$this->action = $action;
			$this->get = $get;
			$this->post = $post;
			$this->timing["begin"] = time();
			$this->debug = true;
		}
		function escape($value)
		{
			if(is_array($value))
			{
				return $value;
			}
			else
			{
				$value = trim($value);
				if(strpos($value,"'") || strpos($value,'"') || strpos($value,"`") || strpos($value,"\\"))
				{
					$value = addslashes($value);
				}
				return $value;
			}
		}
		function compare($value,$type)
		{
			switch ($type)
			{
				case 'int':
					if(strpos($value, ".")) return false;
					if(is_numeric($value) && strlen($value) > 0 && strlen($value) < 12)
						return true;
					break;

				case 'intlist':
					if(strlen($value) == 0) return true;
					if(!preg_match('/[^0-9\,]/',$value))
						return true;
					break;

				case 'string':
					if(strlen($value) == 0) return true;
					if(!preg_match('/[^A-Za-z0-9_-]/',$value))
						return true;
					break;

				case 'address':
					if(strlen($value) == 0) return true;
					if(!preg_match('/[^\/\?\=\&\:\.A-Za-z0-9_-]/',$value))
						return true;
					break;

				case 'unicode':
					if(strlen($value) == 0) return true;
					if(!preg_match('/[\'\"\`\<\>]/',$value))
						return true;
					break;
				
				default:
					return true;
					break;
			}
			return false;
		}
		function terminate()
		{
			die($this->respond());
		}
		function val($method,$name,$type)
		{
			if($method == "get")
			{
				if(!array_key_exists($name,$this->get))
				{
					$this->report("для выполнения этого запроса необходимо передать: ".strtoupper($method).":".strtoupper($name)." типа: ".strtoupper($type));
					$this->terminate();
				}
				else
				{
					if($this->compare($this->get[$name],$type))
					{
						return $this->escape($this->get[$name]);
					}
					else
					{
						$this->report(strtoupper($method).":".strtoupper($name)." не соответствует требованиям типа: ".strtoupper($type));
						$this->terminate();
					}
				}
			}
			else
			{
				if(!array_key_exists($name,$this->post))
				{
					$this->report("для выполнения этого запроса необходимо передать: ".strtoupper($method).":".strtoupper($name)." типа: ".strtoupper($type));
					$this->terminate();
				}
				else
				{
					if($this->compare($this->post[$name],$type))
					{
						return $this->escape($this->post[$name]);
					}
					else
					{
						$this->report(strtoupper($method).":".strtoupper($name)." не соответствует требованиям типа: ".strtoupper($type));
						$this->terminate();
					}
				}
			}
		}
		function report($error)
		{
			array_push($this->errors, $error);
		}
		function result($result)
		{
			$this->result = $result;
			$this->timing["process"] = time();
		}
		function respond()
		{
			$this->timing["print"] = time();
			ob_clean();
			if(count($this->errors) == 0)
			{
				$this->status = "success";
			}
			else
			{
				$this->status = "failure";
			}
			if($this->debug)
			{
				return json_encode([
					"action" => $this->action,
					"status" => $this->status,
					"fields" => [
						"GET" => $this->get, 
						"POST" => $this->post
					],
					"timing" => $this->timing,
					"errors" => $this->errors,
					"result" => $this->result
				],JSON_UNESCAPED_UNICODE);
			}
			else
			{
				return json_encode([
					"action" => $this->action,
					"status" => $this->status,
					"errors" => $this->errors,
					"result" => $this->result
				],JSON_UNESCAPED_UNICODE);
			}
		}
	}
?>
