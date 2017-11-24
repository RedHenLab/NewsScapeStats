<?php

/* CheckCCTiming.php
 * 
 * Author: Peter Broadwell <broadwell@library.ucla.edu>
 * Date: 15 February, 2014
 */

//require "/home/broadwell/NewsScapeStats/TNAQuery.php";

include "averages.php";

ini_set("auto_detect_line_endings", true);

date_default_timezone_set("UTC");

$timingMessages = "";

function getFullTimestamp($dateStr, $originFileName = "") {

  global $timingMessages;

  $dateArray = explode('.', $dateStr);
  $intDate = $dateArray[0];
  $decimals = "";

  if (count($dateArray) == 2) {
    $decimals = "." . $dateArray[1];
  }

  $dateObj = DateTime::createFromFormat('YmdHis', $intDate);

  if ($dateObj == false) {
//    echo "ERROR parsing date string " . $dateStr . "\n";
    $timingMessages .= 'ERROR parsing date string "' . $dateStr . '" ' . $originFileName . "\n";
    return 0;
  }

  $intTimestamp = $dateObj->getTimestamp();

  return (($intTimestamp . $decimals) + 0);
}
/*
function addToHash($key, $val, &$hash) {
  if (array_key_exists($key, $hash)) {
    $hash[$key][] = $val;
  } else {
    $hash[$key] = array($val);
  }
}
function matrixIncrement($first, $second, &$hash) {
  if (array_key_exists($first, $hash)) {
    if (isset ($hash[$first][$second])) {
      $hash[$first][$second] += 1;
    } else {
      $hash[$first][$second] = 1;
    }
  } else {
    $hash[$first] = array();
    $hash[$first][$second] = 1;
  }
}
*/
function durToSeconds ($dur, $filePath) { // $dur must be a string like "H:mm:ss.hh"

  global $timingMessages;

  // 00:59:50
  // 00:59:50.00

  $parse = array();
  if (preg_match ('/^(?<hours>[\d]{1}):(?<mins>[\d]{2}):(?<secs>[\d]{2})\.(?<subseconds>[\d]{1,})$/',$dur,$parse)) {
//    return (int) $parse['hours'] * 3600 + (int) $parse['mins'] * 60 + (int) $parse['secs'] + (int) $parse['subseconds'];
    return (int) $parse['hours'] * 3600 + (int) $parse['mins'] * 60 + (int) $parse['secs'];
  } else if (preg_match ('/^(?<hours>[\d]{2}):(?<mins>[\d]{2}):(?<secs>[\d]{2})$/',$dur,$parse)) {
  return (int) $parse['hours'] * 3600 + (int) $parse['mins'] * 60 + (int) $parse['secs'];
  } else if (preg_match ('/^(?<hours>[\d]{2}):(?<mins>[\d]{2}):(?<secs>[\d]{2})\.(?<subseconds>[\d]{2,})$/',$dur,$parse)) {
    return (int) $parse['hours'] * 3600 + (int) $parse['mins'] * 60 + (int) $parse['secs'];
  } else {
    //cho "Error converting duration: " . $dur . " for " . $filePath . "\n";
    $timingMessages .= "Error converting duration: " . $dur . " for " . $filePath . "\n";
    return 0;
  }
}

function checkTiming() {

global $timingMessages;

$totalVideoFiles = 0;
$totalOCRFiles = 0;
$totalMetadataFiles = 0;

$missingDurTags = 0;
$missingVideoFiles = 0;
$durVideoMismatches = 0;
$lengthDurMismatches = 0;
$anomalousGaps = 0;
$gapsWithOverrun = 0;
$missingCaptions = 0;

$totalMetadataDuration = 0;
$totalVideoDuration = 0;
$totalMetadataLength = 0;

$VideoFileDuration = array(); // ffmpeg -i output in seconds for each .mp4 file
$MetadataDurValue = array(); // Value of DUR field in seconds for each .txt file
$MetadataLength = array(); // Seconds from first timestamp to last for each .txt

$endDate = DateTime::createFromFormat('Y-m-d', date("Y-m-d", time()));
$endDate->modify('-24 hours');

$endYear = $endDate->format('Y');
$endMonth = $endDate->format('m');
$endDay = $endDate->format('d');

//$endYear = 2014;
//$endMonth = 5;
//$endDay = 21;

$startDate = clone $endDate;
$startDate->modify('-1 weeks');

$startYear = $startDate->format('Y');
$startMonth = $startDate->format('m');
$startDay = $startDate->format('d');

//$startYear = 2014;
//$startMonth = 5;
//$startDay = 1;

$startTimestamp = $startYear . sprintf("%02d",$startMonth) . sprintf("%02d",$startDay);
$endTimestamp = $endYear . sprintf("%02d",$endMonth) . sprintf("%02d",$endDay);

$LAtz = new DateTimeZone('America/Los_Angeles');
$UTCtz = new DateTimeZone('GMT');

$yearsDir = new DirectoryIterator('/mnt/isilon/tv/');
$allYears = array();
foreach ($yearsDir as $yearinfo) {
  if ($yearinfo->isDot())
    continue;
  if ($yearinfo->isDir()) { // && ($yearinfo->getFilename() == "2014"))
    $year = $yearinfo->getFilename();
    if (($year < $startYear) || ($year > $endYear) || ($year == "tv"))
      continue;
    $allYears[] = $year;
  }
}
sort($allYears);
foreach ($allYears as $year) {
  $allMonths = array();
  $monthsDir = new DirectoryIterator('/mnt/isilon/tv/' . $year . '/');
  foreach ($monthsDir as $monthinfo) {
    if ($monthinfo->isDot())
      continue;
    if ($monthinfo->isDir()) { // && ($monthinfo->getFilename() == "2009-08"))
      $month = $monthinfo->getFilename();
      $monthIntArray = explode('-', $month);
      $monthInt = array_pop($monthIntArray);
      if ((($year == $startYear) && ($monthInt < $startMonth)) ||
          (($year == $endYear) && ($monthInt > $endMonth))) {
        continue;
      }
      $allMonths[] = $month;
    }
  }
  sort($allMonths);
  foreach ($allMonths as $month) {
    $monthIntArray = explode('-', $month);
    $monthInt = array_pop($monthIntArray);
    $allDays = array();
    $daysDir = new DirectoryIterator('/mnt/isilon/tv/' . $year . '/' . $month . '/');
    foreach ($daysDir as $dayinfo) {
      if ($dayinfo->isDot())
        continue;
      if ($dayinfo->isDir()) {
        $day = $dayinfo->getFilename();
        $dayIntArray = explode('-', $day);
        $dayInt = array_pop($dayIntArray);
        if ((($year == $startYear) && ($monthInt == $startMonth) && ($dayInt < $startDay)) || (($year == $endYear) && ($monthInt == $endMonth) && ($dayInt > $endDay)))
          continue;
        $allDays[] = $day;
      }
    }
    sort($allDays);
    foreach($allDays as $day) {
      $allFiles = array();
      $dateDir = new DirectoryIterator('/mnt/isilon/tv/' . $year . '/' . $month . '/' . $day . '/');
//      echo "Looking at day " . $day . "\n";
      foreach ($dateDir as $dateinfo) {
        if ($dateinfo->isDot())
          continue;
        if ($dateinfo->isFile())
          $allFiles[] = $dateinfo->getPathName();
      }
      sort($allFiles);
      foreach($allFiles as $filepath) {
        $dateinfo = new SplFileInfo($filepath);
        $filename = $dateinfo->getFilename();
//          $filepath = $dateinfo->getPathname();
        $extensionArray = explode('.', $filename);
        $fileroot = $extensionArray[0];
        $extension = end($extensionArray);
        $nameArray = explode('_', $fileroot);
        if (count($nameArray) < 4) 
//          echo "WARNING: problem parsing filename to get network: " . $filename . "\n";
          $timingMessages .= "WARNING: problem parsing filename to get network: " . $filename . "\n";
        $dateSection = implode('', array_slice($nameArray, 0, 2));
        $thisTimestamp = str_replace('-', '', $dateSection);
        $thisTimestamp = str_replace('_', '', $thisTimestamp);

        if (strlen($thisTimestamp) != 12) {
//          echo "ERROR: malformed timestamp: " . $thisTimestamp . " for file " . $filepath . "\n";
          $timingMessages .= "ERROR: malformed timestamp: " . $thisTimestamp . " for file " . $filepath . "\n";
        } else if ($thisTimestamp < $startTimestamp) {
//                echo "skipping " . $filename . ", thisTimestamp is " . $thisTimestamp . ", startTimestamp is " . $startTimestamp . "\n";
          continue;
        }

        $timeOverrun = false;

//        $extension = $dateinfo->getExtension();
        if ($extension == "txt") {
//          echo "looking at metadata file " . $filepath . "\n";
          $totalMetadataFiles++;
          $filebase = $dateinfo->getBasename('.txt');

          $thisNetwork = $nameArray[3];

          $thisSeriesArray = array_slice($nameArray, 4);
          $thisSeries = implode("_", $thisSeriesArray);

          /* XXX This could be broken out into its own module */

          $textContents = file($dateinfo->getPathName());
          if (($textContents === false) || (count($textContents) == 0))
            continue;

          $prevLine = "";
          $prevLineStart = 0;

          $lineDurations = array();
          $lineGaps = array();

          $durFound = false;
          $durSeconds = 0;
          $firstTimestamp = 0;
          $lastTimestamp = 0;
          $penultimateTimestamp = 0;

          $captionsFound = false;

          foreach($textContents as $textLine) {

            $textLine = rtrim($textLine);

            if (preg_match('/^DUR\|(.*?)$/', $textLine, $matches)) {
              $durFound = true;
              $progDur = $matches[1];
              $durSeconds = durToSeconds($progDur, $filename);
              $MetadataDurValue[$filebase] = $durSeconds;
              $videoSeconds = 0;
              if (isset($VideoFileDuration[$filebase]))
                $videoSeconds = $VideoFileDuration[$filebase];

              if ($videoSeconds == 0) {
//                echo "MISSING OR EMPTY VIDEO FILE: " . $filebase . "\n";
                $timingMessages .= "MISSING OR EMPTY VIDEO FILE: " . $filebase . "\n";
                $missingVideoFiles++;
              } else {
                $vidMetAvg = arithmetic_mean(array($durSeconds, $videoSeconds));
                if (abs($durSeconds - $videoSeconds) > ($vidMetAvg * .1)) {
//                  echo "VIDEO LENGTH MISMATCH FOR " . $filebase . ": video file is " . $videoSeconds . "s, DUR value is " . $durSeconds . "s\n";
                  $timingMessages .= "VIDEO LENGTH MISMATCH FOR " . $filebase . ": video file is " . $videoSeconds . "s, DUR value is " . $durSeconds . "s\n";
                  $durVideoMismatches++;
                }
              }
              $totalMetadataDuration += $durSeconds;
            }
            if (preg_match('/^TOP\|(.*?)\|(.*?)$/', $textLine, $matches)) {
              $progTop = $matches[1];
              $firstTimestamp = getFullTimestamp($progTop, $filebase . ".txt");
              $progName = $matches[2];
            }
            if (preg_match('/^([0-9\.]*?)\|([0-9\.]*?)\|(CC|\d\d\d).*?$/', $textLine, $matches)) {
              $captionsFound = true;
              $lineStart = getFullTimestamp($matches[1], $filebase . ".txt");
              if ($firstTimestamp == 0)
                $firstTimestamp = $lineStart;
              $penultimateTimestamp = $lineStart;
              $lineEnd = getFullTimestamp($matches[2], $filebase . ".txt");
              $lastTimestamp = $lineEnd;
  
              if ($prevLineStart != 0) {
                $lineGapTime = $lineStart - $prevLineStart; // XXX check if neg?
                $lineGaps[$prevLine . ' => ' . $textLine] = $lineGapTime;
              }
              $lineDuration = $lineEnd - $lineStart; // XXX check if negative?
              $lineDurations[$textLine] = $lineDuration;
  
              $prevLine = $textLine;
              $prevLineStart = $lineStart;
            }
            /* Do anything with the END tag? */
          }

          $metadataLength = $lastTimestamp - $firstTimestamp;
          
          if ($durFound == false) {
            $missingDurTags++;
//            echo "WARNING: NO DUR TAG FOUND IN  " . $filebase . ".txt\n";
            $timingMessages .= "WARNING: NO DUR TAG FOUND IN  " . $filebase . ".txt\n";
          }

          if (($captionsFound == false) && ($thisNetwork != "Tolo") && ($thisNetwork != "TV5") && ($thisNetwork != "CampaignAds") && ($thisSeries != "BBC_Persian")) {
            $missingCaptions++;
            $timingMessages .= "NO CAPTIONS FOR " . $filebase . ".txt\n";
          }

          if ($metadataLength <= 0)
            continue; // This usually happens when there's no CC text
          
          $totalMetadataLength += $metadataLength;
  
          if (isset($VideoFileDuration[$filebase]) && ($VideoFileDuration[$filebase] > 0) && ($durSeconds > 0)) {
            $videoSeconds = $VideoFileDuration[$filebase];
            $refDuration = arithmetic_mean(array($durSeconds, $videoSeconds));
          } else if (isset($VideoFileDuration[$filebase]) && ($VideoFileDuration[$filebase] > 0)) {
            $refDuration = $VideoFileDuration[$filebase];
          } else if ($durSeconds > 0) {
            $refDuration = $durSeconds;
          } else {
//            "WARNING for " . $filebase . ": no timing data available to compare with metadata file length\n";
            $refDuration = 0;
          }
          if ($refDuration > 0) {
            $vidMetAvg = arithmetic_mean(array($refDuration, $metadataLength));
            if (($metadataLength - $refDuration) > ($vidMetAvg * .1)) {
//              echo "EXCESSIVE METADATA DURATION FOR " . $filebase . ": difference between TOP/start and final timestamp is " . $metadataLength . "s, should be around " . $refDuration . "s\n";
              $timingMessages .= "EXCESSIVE METADATA DURATION FOR " . $filebase . ": difference between TOP/start and final timestamp is " . $metadataLength . "s, should be around " . $refDuration . "s\n";
        
              $lengthDurMismatches++;
              $timeOverrun = true;
            }
          }
  
          /* Now analyze line durations and gaps for outliers */
          $avgLineDuration = median(array_values($lineDurations));
          $avgLineGap = median(array_values($lineGaps));
          $stdevLineDuration = stdev(array_values($lineDurations));
          $stdevLineGap = stdev(array_values($lineGaps));
          
       /*
          // XXX maybe skip the last line, since it's usually messed up?
          foreach ($lineDurations as $textLine => $lineDuration) {
            // check for negatives later ($lineDuration < 0) ||
       //            if (abs($lineDuration - $avgLineDuration) > (5 * $stdevLineDuration))
            if (abs($lineDuration - $avgLineDuration) > 1200)
//              echo $filename . " ANOMALOUS LINE DURATION " . $lineDuration . "s, avg is " . $avgLineDuration . ":\n" . $textLine . "\n";
              $timingMessages .= $filename . " ANOMALOUS LINE DURATION " . $lineDuration . "s, avg is " . $avgLineDuration . ":\n" . $textLine . "\n";
          } */
          foreach ($lineGaps as $textLine => $lineGap) {
            // ($lineGap < 0) || 
          //            if (abs($lineGap - $avgLineGap) > (5 * $stdevLineGap))
            if (abs($lineGap - $avgLineGap) > 1200) {
              if ($timeOverrun == true)  {
//                echo "ANOMALOUS GAP IN " . $filename . ": " . $lineGap . "s, avg is " . $avgLineGap . ":\n" . $textLine . "\n";
                $timingMessages .= "ANOMALOUS GAP IN " . $filename . ": " . $lineGap . "s, avg is " . $avgLineGap . ":\n" . $textLine . "\n";
                $gapsWithOverrun++;
              }
              $anomalousGaps++;
            }
          }
        } else if ($extension == "mp4") { // oops
          $totalVideoFiles++;
          $filebase = $dateinfo->getBasename('.mp4');

          $videoDuration = 0;
          // Get the duration of the actual video, to compare to the closed captions
          $durationCommand = "ffmpeg -i " . $filepath . " 2>&1 | grep Duration | cut -d ' ' -f 4 | sed s/,//";
          ob_start();
          $videoDuration = system($durationCommand);
          ob_end_clean();
    //   echo "videoDuration for " . $filepath . " is " . $videoDuration . "\n";
          $videoTime = durToSeconds($videoDuration, $filename);
          if ($videoTime > 0) {
//            echo "ERROR: video time is " . $videoTime . " for " . $filepath . "\n";
            $VideoFileDuration[$filebase] = $videoTime;
            $totalVideoDuration += $videoTime;
          }
        } else if ($extension == "ocr") {
        }
      }
    }
  }
}
/*
echo "Total video file duration: " . $totalVideoDuration . "s\n";
echo "Total metadata duration (from DUR tags): " . $totalMetadataDuration . "s\n";
echo "Total metadata length (from timestamps): " . $totalMetadataLength . "s\n";

echo "Missing video files: " . $missingVideoFiles . "\n";
echo "Metadata files missing DUR tag: " . $missingDurTags . "\n";
echo "Video file length and DUR tag mismatches: " . $durVideoMismatches . "\n";
echo "Metadata timestamps exceed video length: " . $lengthDurMismatches . "\n";
echo "Anomalous gaps in timestamps: " .$anomalousGaps . "\n";
echo "Records with anomalous gaps AND excessive CC length: " . $gapsWithOverrun . "\n";
*/

if ($timingMessages == "")
  return "NO TIMING ERRORS FOUND IN METADATA FROM LAST 7 DAYS\n";
else
  return "RESULTS FROM METADATA CHECKS FOR LAST 7 DAYS:\n" . $timingMessages;
}

// Uncomment for standalone execution
//echo checkTiming();

?>
