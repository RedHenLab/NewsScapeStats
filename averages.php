<?php

function arithmetic_mean($a) {
 return array_sum($a)/count($a);
}

function geometric_mean($a) {
 foreach($a as $i=>$n) $mul = $i == 0 ? $n : $mul*$n;
 return pow($mul,1/count($a));
}

function harmonic_mean($a) {
 $sum = 0;
 foreach($a as $n) $sum += 1 / $n;
 return (1/$sum)*count($a);
}

function median($a) {
 if (!count($a)) return 0;
 sort($a,SORT_NUMERIC); 
 return (count($a) % 2) ? 
  $a[floor(count($a)/2)] : 
  ($a[floor(count($a)/2)] + $a[floor(count($a)/2) - 1]) / 2;
}

function modal_score($a) {
 $quant = array();
 foreach($a as $n) $quant["$n"]++;
 $max = 0;
 $mode = 0;
 foreach($quant as $key=>$n) {
  if($n>$max) {
   $max = $n;
   $mode = $key;
  }
 }
 return $mode;
}

function average($arr) {
  if (!count($arr)) return 0;

  $sum = 0;
  for ($i = 0; $i < count($arr); $i++) {
    $sum += $arr[$i];
  }

  return $sum / count($arr);
}

function variance($arr) {
  if ((!count($arr)) || (count($arr) == 1)) return 0;

  $mean = average($arr);

  $sos = 0;    // Sum of squares
  for ($i = 0; $i < count($arr); $i++) {
    $sos += ($arr[$i] - $mean) * ($arr[$i] - $mean);
  }

  return $sos / (count($arr)-1);  // denominator = n-1; i.e. estimating based on sample
                                  // n-1 is also what MS Excel takes by default in the
                                  // VAR function
}

function stdev($arr) {
  if (!count($arr)) return 0;

  $variance = variance($arr);

  $stdev = sqrt($variance);

  return $stdev;
}

?>
