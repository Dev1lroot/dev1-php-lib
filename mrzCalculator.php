<?php
/**
	*	@description	ICAO-9303 Machine Readable Travel Document, MRZ - Machine Readable Zone - Generator
	*	@category	GOVERNMENT
	*	@package	ICAO
	*	@author		dev1lroot@protonmail.com
	*	@copyright	2021 David Eichendorf (C)
	*	@license	AGPL 3.0
	*	@version	1.0
*/
class MRZCALC
{
    var $td1;
    var $td2;
    var $cc;
    var $type;

    function __construct($username,$type,$countrycode)
    {
        $td1 = "P".strtoupper($type.$countrycode).strtoupper($username);
        while(44 > strlen($td1))
        {
            $td1 = $td1."<";
        }
        $this->td1  = $td1;
        $this->cc   = $countrycode;
        $this->type = $type;
    }
    private function control($input)
    {
        $digit = 1;
        $summa = 0;
        for ($i=0; $i < strlen($input); $i++)
        { 
            if($digit > 3)
            {
                $digit = 1;
            }
            if($digit == 1)
            {
                $multiply = 7;
            }
            if($digit == 2)
            {
                $multiply = 3;
            }
            if($digit == 3)
            {
                $multiply = 1;
            }
            $summa = $summa + intval(intval(substr($input,$i,1)) * $multiply);
            $digit++;
        }
        return substr($summa,-1);
    }
    function issue($number,$date_of_birth,$gender)
    {
        while(9 > strlen($number))
        {
            $number = "0".$number;
        }
        $date_of_birth = gmdate("ymd", $date_of_birth);
        $date_of_expiry = gmdate("ymd", time() + (3600 * 24 * 7));
        $this->td2 = $number.$this->control($number).$this->cc.$date_of_birth.$this->control($date_of_birth).$gender.$date_of_expiry.$this->control($date_of_expiry);
        while(42 > strlen($this->td2))
        {
            $this->td2 .= "<";
        }
        $this->td2 .= "0" . $this->control(preg_replace("/[^0-9]/","",$this->td2));
    }
    function get()
    {
        return [$this->td1,$this->td2];
    }
}
