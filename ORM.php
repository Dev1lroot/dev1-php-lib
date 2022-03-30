<?php
class Model
{
    var $q;
    var $db;
    var $name;
    var $query;
    var $columns;
    public function __construct($db,$name,$columns)
    {
        $this->db = $db;
        $this->name = $name;
        $this->query = [];
        $this->errors = [];
        $this->columns = $columns;

        if($this->db->connect_error)
        {
            $this->errors[] = ["origin" => "database", "report" => $this->db->connect_error, "code" => $this->db->connect_errno];
        }
    }
    public function export_array($what)
    {
        if(!isset($what[0]) || !is_array($what))
        {
            $w = [];
            $w[] = $what;
            $what = $w;
        }
        return $what;
    }
    public function is_field($what)
    {
        return in_array($what, array_keys($this->columns));
    }
    public function _insert($what)
    {
        $k = [];
        $v = [];
        if(!isset($what[0]))
        {
            $what = $this->export_array($what);
        }
        if(is_array($what))
        {
            foreach ($what as $id => $r)
            {
                foreach (array_keys($r) as $key)
                {
                    if($this->is_field($key))
                    {
                        $v[$id][] = $r[$key];
                        $k[$id][] .= $key;
                    }
                    else
                    {
                        $this->errors[] = ["origin" => "framework", "report" => "unknown column {$key}", "code" => 1];
                    }
                }
            }
        }
        return (object)["k"=>$k,"v"=>$v];
    }
    public function insert($what)
    {
        $output = [];
        $hashes = [];
        $params = $this->_insert($what);
        foreach($params->k as $id => $key)
        {
            $hashes[]  = md5(json_encode($key));
        }
        if(count(array_unique($hashes)) == 1)  // is map
        {
            $r = [];
            foreach ($params->v as $id => $values)
            {
                $r[] = "('".implode("','",$values)."')";
            }
            $output = "INSERT INTO `{$this->name}` (`".implode("`,`",$params->k[0])."`) VALUES ".implode(",",$r).";";
        }
        return $output;
    }
    public function all()
    {
        $this->query[] = "SELECT * FROM `{$this->name}`";
        return $this;
    }
    public function select($what)
    {
        $this->query[] = "SELECT `".implode("`,`",$this->export_array($what))."` FROM `{$this->name}`";
        return $this;
    }
    public function where($what)
    {
        $this->query[] = "WHERE ".implode(" AND ",$this->export_array($what));
        return $this;
    }
    public function left_join($what,$on)
    {
        $this->query[] = "LEFT JOIN `{$what}` ON ".implode(" AND ",$this->export_array($on));
        return $this;
    }
    public function order($by,$mode)
    {
        $this->query[] = "ORDER BY {$by} {$mode}";
        return $this;
    }
    public function limit($number)
    {
        $this->query[] = "LIMIT {$number}";
        return $this;
    }
    public function query($query)
    {
        $this->query = $query;
        return $this;
    }
    public function getQuery()
    {
        $this->q = implode(" ",$this->export_array($this->query));
        return $this->q;
    }
    public function getStatus()
    {
        $r = $this->db->query($this->getQuery());
        if($r)
        {
            return true;
        }
        else
        {
            $this->errors[] = ["origin" => "database", "report" => $this->db->error, "code" => $this->db->errno];
        }
        return false;
    }
    public function getRows()
    {
        $r = $this->db->query($this->getQuery());
        if($r)
        {
            if(mysqli_num_rows($r) > 0)
            {
                return (object) $r->fetch_all(MYSQLI_ASSOC);
            }
        }
        else
        {
            $this->errors[] = ["origin" => "database", "report" => $this->db->error, "code" => $this->db->errno];
        }
        return [];
    }
    public function getRow()
    {
        $r = $this->db->query($this->getQuery());
        if($r)
        {
            if(mysqli_num_rows($r) > 0)
            {
                return (object) $r->fetch_assoc();
            }
        }
        else
        {
            $this->errors[] = ["origin" => "database", "report" => $this->db->error, "code" => $this->db->errno];
        }
        return [];
    }
    public function preventInjection($value,$type)
    {
        if($type == "int")
        {
            return intval($value);
        }
        return $value;
    }
}
class ORM
{
    var $models;
    var $db;
    public function __construct($db)
    {
        $this->db = $db;
        $this->models = [];
    }
    public function blueprint($string)
    {
        $m = [];
        $t = [];
        $d = [];
        foreach (explode("\n",$string) as $id => $r)
        {
            if(preg_match("/^(?<name>\w*)(\:)(|(| *)(extends)( *)(?<parent>[\w\, ]*))$/", $r, $m))
            {
                $m = (object) $m;
                if(isset($m->name))
                {
                    if(!isset($m->parent))
                    {
                        $m->parent = "";
                    }
                    $t[] = ["name" => trim($m->name), "parent" => trim($m->parent), "columns" => []];
                }
                $m = [];
            }
            if(preg_match("/^(\t|[ ]{4})(?<column>(?<name>\w*)( *)(?<type>\w*)( *)(?<size>\d*))$/", $r, $m))
            {
                $m = (object) $m;
                if(isset($m->name))
                {
                    if(in_array(strtolower(trim($m->type)), ["int","bigint","tinyint","varchar","text","double"]))
                    {
                        $t[count($t)-1]["columns"][$m->name] = ["type" => $m->type, "size" => $m->size, "flag" => []];
                    }
                }
                $m = [];
            }
            if(preg_match("/^(\t\t|[ ]{8})(?<flag>\w*)$/", $r, $m))
            {
                $m = (object) $m;
                if(isset($m->flag))
                {
                    $n = array_keys($t[count($t)-1]["columns"])[count(array_keys($t[count($t)-1]["columns"]))-1];
                    $t[count($t)-1]["columns"][$n]["flag"][] = trim(strtoupper($m->flag));
                }
                $m = [];
            }
        }
        foreach ($t as $id => $r)
        {
            $r = (object) $r;
            $d[$r->name] = $r->columns;
        }
        foreach ($t as $id => $r)
        {
            $r = (object) $r;
            $c = $r->columns;
            if(strlen($r->parent))
            {
                foreach (array_reverse(explode(",",$r->parent)) as $parent)
                {
                    $parent = trim($parent);
                    if(isset($d[$parent]))
                    {
                        $c = array_merge($d[$parent], $c);
                    }
                }
            }
            $this->models[$r->name] = new Model($this->db,$r->name,$c);
        }
        $this->models = (object) $this->models;
    }
}
