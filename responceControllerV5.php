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
		var $timing;

		var $get;
		var $post;

		var $admin;

		var $flow;
		var $code;
		function __construct($action,$get,$post,$db = null)
		{
			ob_start();
			$this->db = $db;
			$this->flow = 0;
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
				if($this->db != NULL)
				{
					$value = $this->db->mysqli_real_escape_string($value);
				}
				else
				{
					if(strpos($value,"'") || strpos($value,'"') || strpos($value,"`") || strpos($value,"\\"))
					{
						$value = addslashes($value);
					}
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
					$value = substr($cmp,strlen($param) + 1,strlen($cmp) - strlen($param));
				}
				else
				{
					$param = $cmp;
					$value = true;
				}
				$out = array_merge($out,[$param => $value]);
			}
			return $out;
		}
		private function errorFormat($name,$method,$args,$arg_name)
		{
			return [
				"field_name" => $name,
				"field_type" => $method,
				"error_name" => $arg_name,
				"error_data" => $args[$arg_name],
				"error_code" => "0x".$this->flow."A".$this->code
			];
		}
		private function errorCode($field)
		{
			//
		}
		public function upd($method,$name,$value)
		{
			if($method == "get")
			{
				$this->get[$name] = $value;
			}
			else
			{
				$this->post[$name] = $value;
			}
		}
		public function val($method,$name,$args)
		{
			$this->flow++;
			$this->code = 0;
			$result = NULL;
			$args = $this->operators($args);
			$errors = [];
			$io = $this->io($method);

			// COMPARATORS //
			if(array_key_exists("default",$args))
			{
				$result = $args["default"];
			}
			if(array_key_exists("strict",$args))
			{
				
			}
			if(array_key_exists("required",$args))
			{
				if(!$this->has($method,$name))
				{
					$this->code++;
					$errors[] = $this->errorFormat($name,$method,$args,"required");
				}
			}
			if(!array_key_exists("raw",$args))
			{
				$this->upd($method,$name,htmlspecialchars($io[$name]));
			}
			if(array_key_exists("re",$args))
			{
				if(!preg_match($args["re"], $io[$name]))
				{
					$this->code++;
					$errors[] = $this->errorFormat($name,$method,$args,"re");
				}
			}
			if(array_key_exists("type",$args))
			{
				if(!$this->cmp($io[$name],$args["type"]))
				{
					$this->code++;
					$errors[] = $this->errorFormat($name,$method,$args,"type");
				}
			}
			if(array_key_exists("minlen",$args))
			{
				if($args["minlen"] > strlen($io[$name]))
				{
					$this->code++;
					$errors[] = $this->errorFormat($name,$method,$args,"minlen");
				}
			}
			if(array_key_exists("maxlen",$args))
			{
				if(strlen($io[$name]) > $args["maxlen"])
				{
					$this->code++;
					$errors[] = $this->errorFormat($name,$method,$args,"maxlen");
				}
			}
			if(array_key_exists("minval",$args))
			{
				if(intval($args["minval"]) > intval($io[$name]))
				{
					$this->code++;
					$errors[] = $this->errorFormat($name,$method,$args,"minval");
				}
			}
			if(array_key_exists("maxval",$args))
			{
				if(intval($io[$name]) > intval($args["maxval"]))
				{
					$this->code++;
					$errors[] = $this->errorFormat($name,$method,$args,"maxval");
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
