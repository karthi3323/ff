<?php

class PDF_Rotate extends FPDF {
    var $angle=0;

    function Rotate($angle,$x=-1,$y=-1)
    {
        if($x==-1)
            $x=$this->x;
        if($y==-1)
            $y=$this->y;
        if($this->angle!=0)
            $this->_out('Q');
        $this->angle=$angle;
        if($angle!=0)
        {
            $angle*=M_PI/180;
            $c=cos($angle);
            $s=sin($angle);
            $cx=$x*$this->k;
            $cy=($this->h-$y)*$this->k;
            $this->_out(sprintf('q %.5f %.5f %.5f %.5f %.2f %.2f cm 1 0 0 1 %.2f %.2f cm',$c,$s,-$s,$c,$cx,$cy,-$cx,-$cy));
        }
    }

    function _endpage()
    {
        if($this->angle!=0)
        {
            $this->angle=0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
}

class PDF extends PDF_Rotate {
    function Header() {    
        $this->SetDisplayMode('fullwidth');  
        $this->setTitle('Report File');
        // $this->Image('../../img/favicon.png', 10, 10, 10);  	
        $y_axis_initial = 6;
    }
    function Footer() {	}
}

//Number to Words

	$nwords = array(  "", "One", "Two", "Three", "Four", "Five", "Six", 
	      	  "Seven", "Eight", "Nine", "Ten", "Eleven", "Twelve", "Thirteen", 
	      	  "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", 
	     	  "Nineteen", "Twenty", 30 => "Thirty", 40 => "Forty",
                     50 => "Fifty", 60 => "Sixty", 70 => "Seventy", 80 => "Eighty",
                     90 => "Ninety" );
	function int_to_words($x)
	{
		$x = str_replace(',','',$x);
		$pos = strpos((string)$x, ".");
		if ($pos !== false) { $decimalpart= substr($x, $pos+1, 2); $x = substr($x,0,$pos); }
		$tmp_str_rtn = number_to_words ($x);
		if ($decimalpart>0){
			$tmp_str_rtn .= ' and '  . number_to_words ($decimalpart) . ' paise';
		}
		return   $tmp_str_rtn;
	} 

	function number_to_words ($x)
	{
		 global $nwords; 
		 if(!is_numeric($x))
		 {
			 $w = '#';
		 }else if(fmod($x, 1) != 0)
		 {
			 $w = '#';
		 }else{
			 if($x < 0)
			 {
				 $w = 'minus ';
				 $x = -$x;
			 }else{
				 $w = '';
			 }
			 if($x < 21)
			 {
				 $w .= $nwords[$x];
			 }else if($x < 100)
			 {
				 $w .= $nwords[10 * floor($x/10)];
				 $r = fmod($x, 10);
				 if($r > 0)
				 {
					 $w .= ' '. $nwords[$r];
				 }
			 } else if($x < 1000)
			 {
			
				 $w .= $nwords[floor($x/100)] .' Hundred';
				 $r = fmod($x, 100);
				 if($r > 0)
				 {
					 $w .= ' '. number_to_words($r);
				 }
			 } else if($x < 100000)
			 {
				$w .= number_to_words(floor($x/1000)) .' Thousand';
				 $r = fmod($x, 1000);
				 if($r > 0)
				 {
					 $w .= ' ';
					 if($r < 100)
					 {
						 $w .= ' ';
					 }
					 $w .= number_to_words($r);
				 }
			 } else if($x < 10000000){
				 $w .= number_to_words(floor($x/100000)) .' Lakh';
				 $r = fmod($x, 100000);
				 if($r > 0)
				 {
					 $w .= ' ';
					 if($r < 100)
					 {
						 $word .= ' ';
					 }
					 $w .= number_to_words($r);
				 }
			 } else {
				 $w .= number_to_words(floor($x/10000000)) .' Crore';
				 $r = fmod($x, 10000000);
				 if($r > 0)
				 {
					 $w .= ' ';
					 if($r < 100)
					 {
						 $word .= ' ';
					 }
					 $w .= number_to_words($r);
				 }
			 }
		 }
		 return $w;
	}

	function makecomma($input)
	{
		// This function is written by some anonymous person - I got it from Google
		if(strlen($input)<=2)
		{ return $input; }
		$length=substr($input,0,strlen($input)-2);
		$formatted_input = makecomma($length).",".substr($input,-2);
		return $formatted_input;
	}

	function formatInIndianStyle($num)
	{
		// This is my function
		if(strstr($num, 'E')) {
			list($significand, $exp) = explode('E', $num);
			list($void, $decimal) = explode('.', "$significand");
			$decimal_len = strlen("$decimal");
			$exp = str_replace('+', '', "$exp");
			$exp -= $decimal_len;
			$append = '';
			for($i = 1; $i <= $exp; $i++) {
				$append .= '0';
			}
			$tmp = str_replace('.', '', "$significand");
			$reconsctructed = "$tmp" . "$append";
			$num = $reconsctructed;
		}
		
		$pos = strpos((string)$num, ".");
		if ($pos === false) { $decimalpart="00";}
		else { $decimalpart= substr($num, $pos+1, 2); $num = substr($num,0,$pos); }

		if(strlen($num)>3 & strlen($num) <= 12){
					$last3digits = substr($num, -3 );
					$numexceptlastdigits = substr($num, 0, -3 );
					$formatted = makecomma($numexceptlastdigits);
					$stringtoreturn = $formatted.",".$last3digits.".".$decimalpart ;
		}elseif(strlen($num)<=3){
					$stringtoreturn = $num.".".$decimalpart ;
		}elseif(strlen($num)>12){
					$stringtoreturn = number_format($num, 2);
		}

		if(substr($stringtoreturn,0,2)=="-,"){$stringtoreturn = "-".substr($stringtoreturn,2);}

		return $stringtoreturn;
	}