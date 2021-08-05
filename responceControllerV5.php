<?php
	/**
	*	@description	IndexCMS Engine API Component
	*	@category	API
	*	@package	IndexCMS
	*	@author		dev1lroot@protonmail.com
	*	@copyright	2021 David Eichendorf (C)
	*	@license	AGPL 3.0
	*	@version	5.0
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
		private function escape($value)
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
		private function cmp($value,$type)
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
		private function operators($ops)
		{
			$out = [];
			if(strpos($ops, "|"))
			{
				$args = explode("|", $ops);
			}
			else
			{
				$args = [$ops];
			}
			foreach ($args as $id => $cmp)
			{
				if(strpos($cmp, ":"))
				{
					$param = explode(":",$cmp)[0];
					$value = explode(":",$cmp)[1];
				}
				else
				{
					$param = $cmp;
					$value = "";
				}
				$out = array_merge($out,[$param => $value]);
			}
			return $out;
		}
		public function val($method,$name,$args)
		{
			$result = NULL;
			$args = $this->operators($args);
			$errors = [];
			$io = $this->io($method);
			$errors = [];
			// COMPARATORS //
			if(array_key_exists("default",$args))
			{
				$result = $args["default"];
			}
			if(array_key_exists("required",$args))
			{
				if(!$this->has($method,$name))
				{
					array_push($errors,[
						"field" => $name,
						"method" => $method,
						"compare" => "required",
						"defined" => true
					]);
				}
			}
			if(array_key_exists("type",$args))
			{
				if(!$this->cmp($this->get[$name],$args["type"]))
				{
					array_push($errors,[
						"field" => $name,
						"method" => $method,
						"compare" => "type",
						"defined" => $args["type"]
					]);
				}
			}
			if(array_key_exists("minlen",$args))
			{
				if($args["minlen"] > strlen($io[$name]))
				{
					array_push($errors,[
						"field" => $name,
						"method" => $method,
						"compare" => "minlen",
						"defined" => $args["minlen"]
					]);
				}
			}
			if(array_key_exists("maxlen",$args))
			{
				if(strlen($io[$name]) > $args["maxlen"])
				{
					array_push($errors,[
						"field" => $name,
						"method" => $method,
						"compare" => "maxlen",
						"defined" => $args["maxlen"]
					]);
				}
			}
			if(array_key_exists("minval",$args))
			{
				if(intval($args["minval"]) > intval($io[$name]))
				{
					array_push($errors,[
						"field" => $name,
						"method" => $method,
						"compare" => "minval",
						"defined" => $args["minval"]
					]);
				}
			}
			if(array_key_exists("maxval",$args))
			{
				if(intval($io[$name]) > intval($args["maxval"]))
				{
					array_push($errors,[
						"field" => $name,
						"method" => $method,
						"compare" => "maxval",
						"defined" => $args["maxval"]
					]);
				}
			}
			if(count($errors) == 0)
			{
				$result = $this->escape($io[$name]);
			}
			else
			{
				if(array_key_exists("required",$args))
				{
					$this->errors = array_merge($this->errors,$errors);
					$this->terminate();
				}
				else
				{
					return $result;
				}
			}
		}
		public function terminate()
		{
			die($this->respond());
		}
		private function io($method)
		{
			if(strtolower($method) == "post")
			{
				return $this->post;
			}
			else
			{
				return $this->get;
			}
		}
		public function has($method,$name)
		{
			return array_key_exists($name,$this->io($method));
		}
		public function report($error)
		{
			array_push($this->errors, $error);
		}
		public function result($result)
		{
			$this->result = $result;
			$this->timing["process"] = time();
		}
		public function respond()
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
