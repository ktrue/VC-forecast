<?php 
// VC-forecast.php script by Ken True - webmaster@saratoga-weather.org
//    Forecast from visualcrossing.com - based on DS-forecast.php V1.11 - 27-Dec-2022
//
// Version 1.00 - 16-Nov-2018 - initial release
// Version 1.01 - 17-Nov-2018 - added wind unit translation, fixed -0 temp display, added alerts (only English available)
// Version 1.02 - 19-Nov-2018 - added Updated: and Forecast by: display/translations
// Version 1.03 - 29-Nov-2018 - added fixes for summaries with embedded UTF symbols.
// Version 1.04 - 04-Dec-2018 - added Serbian (sr) language support
// Version 1.05 - 08-Dec-2018 - added optional current conditions display box, cloud-cover now used for better icon choices
// Version 1.06 - 05-Jan-2019 - fixed Hebrew forecast display for Saratoga template
// Version 1.07 - 07-Jan-2019 - formatting fix for Hebrew display in Saratoga template
// Version 1.08 - 15-Jan-2019 - added check for good JSON return before saving cache file
// Version 1.09 - 23-Jan-2019 - added hourly forecast and tabbed display
// Version 1.10 - 19-Jan-2022 - fix for PHP 8.1 Deprecated errata
// Version 1.11 - 27-Dec-2022 - fixes for PHP 8.2
// Version 2.00 - 07-Feb-2023 - rewrite to use Visualcrossing API for weather forecasts
//
$Version = "VC-forecast.php (ML) Version 2.00 - 07-Feb-2023";
//
// error_reporting(E_ALL);  // uncomment to turn on full error reporting
//
// script available at http://saratoga-weather.org/scripts.php
//  
// you may copy/modify/use this script as you see fit,
// no warranty is expressed or implied.
//
// This script parses the visualcrossing.com forecast JSON API and loads icons/text into
//  arrays so you can use them in your weather website.  
//
//
// output: creates XHTML 1.0-Strict HTML page (or inclusion)
//
// Options on URL:
//
//   inc=Y            - omit <HTML><HEAD></HEAD><BODY> and </BODY></HTML> from output
//   heading=n        - (default)='y' suppress printing of heading (forecast city/by/date)
//   icons=n          - (default)='y' suppress printing of the icons+conditions+temp+wind+UV
//   text=n           - (default)='y' suppress printing of the periods/forecast text
//
//
//  You can also invoke these options directly in the PHP like this
//
//    $doIncludeVC = true;
//    include("VC-forecast.php");  for just the text
//  or ------------
//    $doPrintVC = false;
//    include("VC-forecast.php");  for setting up the $VCforecast... variables without printing
//
//  or ------------
//    $doIncludeVC = true;
//    $doPrintConditions = true;
//    $doPrintHeadingVC = true;
//    $doPrintIconsVC = true;
//    $doPrintTextVC = false
//    include("VC-forecast.php");  include mode, print only heading and icon set
//
// Variables returned (useful for printing an icon or forecast or two...)
//
// $VCforecastcity 		- Name of city from VC Forecast header
//
// The following variables exist for $i=0 to $i= number of forecast periods minus 1
//  a loop of for ($i=0;$i<count($VCforecastday);$i++) { ... } will loop over the available 
//  values.
//
// $VCforecastday[$i]	- period of forecast
// $VCforecasttext[$i]	- text of forecast 
// $VCforecasttemp[$i]	- Temperature with text and formatting
// $VCforecastpop[$i]	- Number - Probabability of Precipitation ('',10,20, ... ,100)
// $VCforecasticon[$i]   - base name of icon graphic to use
// $VCforecastcond[$i]   - Short legend for forecast icon 
// $VCforecasticons[$i]  - Full icon with Period, <img> and Short legend.
// $VCforecastwarnings = styled text with hotlinks to advisories/warnings
// $VCcurrentConditions = table with current conds at point close to lat/long selected
//
// Settings ---------------------------------------------------------------
// REQUIRED: a visualcrossing.com API KEY.. sign up at https://visualcrossing.com/
$VCAPIkey = 'specify-for-standalone-use-here'; // use this only for standalone / non-template use
// NOTE: if using the Saratoga template, add to Settings.php a line with:
//    $SITE['VCAPIkey'] = 'your-api-key-here';
// and that will enable the script to operate correctly in your template
//
$iconDir ='./forecast/images/';	// directory for carterlake icons './forecast/images/'
$iconType = '.jpg';				// default type='.jpg' 
//                        use '.gif' for animated icons fromhttp://www.meteotreviglio.com/
//
// The forecast(s) .. make sure the first entry is the default forecast location.
// The contents will be replaced by $SITE['VCforecasts'] if specified in your Settings.php

$VCforecasts = array(
 // Location|lat,long  (separated by | characters)
'Saratoga, CA, USA|37.27465,-122.02295',
'Auckland, NZ|-36.910,174.771', // Awhitu, Waiuku New Zealand
'Assen, NL|53.02277,6.59037',
'Blankenburg, DE|51.8089941,10.9080649',
'Cheyenne, WY, USA|41.144259,-104.83497',
'Carcassonne, FR|43.2077801,2.2790407',
'Braniewo, PL|54.3793635,19.7853585',
'Omaha, NE, USA|41.19043,-96.13114',
'Johanngeorgenstadt, DE|50.439339,12.706085',
'Athens, GR|37.97830,23.715363',
'Haifa, IL|32.7996029,34.9467358',
); 

//
$maxWidth = '640px';                      // max width of tables (could be '100%')
$maxIcons = 8;                           // max number of icons to display
$maxForecasts = 15;                       // max number of Text forecast periods to display
$maxForecastLegendWords = 4;              // more words in forecast legend than this number will use our forecast words 
$numIconsInFoldedRow = 8;                 // if words cause overflow of $maxWidth pixels, then put this num of icons in rows
$autoSetTemplate = true;                  // =true set icons based on wide/narrow template design
$cacheFileDir = './';                     // default cache file directory
$cacheName = "VC-forecast-json.txt";      // locally cached page from VC
$refetchSeconds = 99993600;                   // cache lifetime (3600sec = 60 minutes)
//
// Units: 
// base: SI units (K,m/s,hPa,mm,km)
// metric: same as base, except that temp in C and windSpeed and windGust are in kilometers per hour
// uk: same as metric, except that nearestStormDistance and visibility are in miles, and windSpeed and windGust in miles per hour
// us: Imperial units (F,mph,inHg,in,miles)
// 
$showUnitsAs  = 'metric'; // ='us' for imperial, , ='metric' for metric, ='uk' for UK
//
$charsetOutput = 'ISO-8859-1';        // default character encoding of output
//$charsetOutput = 'UTF-8';            // for standalone use if desired
$lang = 'en';	// default language
$foldIconRow = false;  // =true to display icons in rows of 5 if long texts are found
$timeFormat = 'Y-m-d H:i T';  // default time display format

$showConditions = true; // set to true to show current conditions box

// ---- end of settings ---------------------------------------------------

// overrides from Settings.php if available
global $SITE;
if (isset($SITE['VCforecasts']))   {$VCforecasts = $SITE['VCforecasts']; }
if (isset($SITE['VCAPIkey']))	{$VCAPIkey = $SITE['VCAPIkey']; } // new V3.00
if (isset($SITE['VCshowUnitsAs'])) { $showUnitsAs = $SITE['VCshowUnitsAs']; }
if (isset($SITE['fcsticonsdir'])) 	{$iconDir = $SITE['fcsticonsdir'];}
if (isset($SITE['fcsticonstype'])) 	{$iconType = $SITE['fcsticonstype'];}
if (isset($SITE['xlateCOP']))	{$xlateCOP = $SITE['xlateCOP'];}
if (isset($LANGLOOKUP['Chance of precipitation'])) {
  $xlateCOP = $LANGLOOKUP['Chance of precipitation'];
}
if (isset($SITE['charset']))	{$charsetOutput = strtoupper($SITE['charset']); }
if (isset($SITE['lang']))		{$lang = $SITE['lang'];}
if (isset($SITE['cacheFileDir']))     {$cacheFileDir = $SITE['cacheFileDir']; }
if (isset($SITE['foldIconRow']))     {$foldIconRow = $SITE['foldIconRow']; }
if (isset($SITE['RTL-LANG']))     {$RTLlang = $SITE['RTL-LANG']; }
if (isset($SITE['timeFormat']))   {$timeFormat = $SITE['timeFormat']; }
if (isset($SITE['VCshowConditions'])) {$showConditions = $SITE['VCshowConditions'];} // new V1.05
// end of overrides from Settings.php
//
// -------------------begin code ------------------------------------------

$RTLlang = ',he,jp,cn,';  // languages that use right-to-left order

if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
   //--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   
   readfile($filenameReal);
   exit;
}

$Status = "<!-- $Version on PHP ".phpversion()." -->\n";

$VCcurrentConditions = ''; // HTML for table of current conditions
//------------------------------------------------

if(preg_match('|specify|i',$VCAPIkey)) {
	print "<p>Note: the VC-forecast.php script requires an API key from Visualcrossing.com to operate.<br/>";
	print "Visit <a href=\"https://Visualcrossing.com\">Visualcrossing.com</a> to ";
	print "register for an API key.</p>\n";
	if( isset($SITE['fcsturlVC']) ) {
		print "<p>Insert in Settings.php an entry for:<br/><br/>\n";
		print "\$SITE['VCAPIkey'] = '<i>your-key-here</i>';<br/><br/>\n";
		print "replacing <i>your-key-here</i> with your VC API key.</p>\n";
	}
	return;
}
/*
iconSet=icons1 (default)
Icon id	Weather Conditions
snow	Amount of snow is greater than zero
rain	Amount of rainfall is greater than zero
fog	Visibility is low (lower than one kilometer or mile)
wind	Wind speed is high (greater than 30 kph or mph)
cloudy	Cloud cover is greater than 90% cover
partly-cloudy-day	Cloud cover is greater than 20% cover during day time.
partly-cloudy-night	Cloud cover is greater than 20% cover during night time.
clear-day	Cloud cover is less than 20% cover during day time
clear-night	Cloud cover is less than 20% cover during night time

iconSet=icons2
Icon id	Weather Conditions
snow	Amount of snow is greater than zero
snow-showers-day	Periods of snow during the day
snow-showers-night	Periods of snow during the night
thunder-rain	Thunderstorms throughout the day or night
thunder-showers-day	Possible thunderstorms throughout the day
thunder-showers-night	Possible thunderstorms throughout the night
rain	Amount of rainfall is greater than zero
showers-day	Rain showers during the day
showers-night	Rain showers during the night
fog	Visibility is low (lower than one kilometer or mile)
wind	Wind speed is high (greater than 30 kph or mph)
cloudy	Cloud cover is greater than 90% cover
partly-cloudy-day	Cloud cover is greater than 20% cover during day time.
partly-cloudy-night	Cloud cover is greater than 20% cover during night time.
clear-day	Cloud cover is less than 20% cover during day time
clear-night	Cloud cover is less than 20% cover during night time
*/
$NWSiconlist = array(
// visualcrossing.com ICON definitions
  'clear-day' => 'skc.jpg',
  'clear-night' => 'nskc.jpg',
  'rain' => 'ra.jpg',
  'showers-day' => 'shra.jpg',
  'showers-night' => 'nshra.jpg',
  'snow' => 'sn.jpg',
  'snow-showers-day' => 'sn.jpg',
  'snow-showers-night' => 'nsn.jpg',
  'sleet' => 'fzra.jpg',
  'wind' => 'wind.jpg',
  'fog' => 'fg.jpg',
  'cloudy' => 'ovc.jpg',
  'partly-cloudy-day' => 'sct.jpg',
  'partly-cloudy-night' => 'nsct.jpg',
  'hail' => 'ip.jpg',
  'thunderstorm' => 'tsra.jpg',
  'thunder-showers-day' => 'hi_tsra.jpg',
  'thunder-showers-night' => 'hi_ntsra.jpg',
  'tornado' => 'tor.jpg',
	'wind' => 'wind.jpg',
	);
//

$windUnits = array(
 'us' => 'mph',
 'metric' => 'km/h',
 'uk' => 'mph'
);
$UnitsTab = array(
 'metric' => array('T'=>'&deg;C','W'=>'km/s','P'=>'hPa','R'=>'mm','D'=>'km'),
 'uk' => array('T'=>'&deg;C','W'=>'mph','P'=>'mb','R'=>'mm','D'=>'mi'),
 'us' => array('T'=>'&deg;F','W'=>'mph','P'=>'inHg','R'=>'in','D'=>'mi'),
);

if(isset($UnitsTab[$showUnitsAs])) {
  $Units = $UnitsTab[$showUnitsAs];
} else {
	$Units = $UnitsTab['si'];
}

if(!function_exists('langtransstr')) {
	// shim function if not running in template set
	function langtransstr($input) { return($input); }
}

if(!function_exists('json_last_error')) {
	// shim function if not running PHP 5.3+
	function json_last_error() { return('- N/A'); }
	$Status .= "<!-- php V".phpversion()." json_last_error() stub defined -->\n";
	if(!defined('JSON_ERROR_NONE')) { define('JSON_ERROR_NONE',0); }
	if(!defined('JSON_ERROR_DEPTH')) { define('JSON_ERROR_DEPTH',1); }
	if(!defined('JSON_ERROR_STATE_MISMATCH')) { define('JSON_ERROR_STATE_MISMATCH',2); }
	if(!defined('JSON_ERROR_CTRL_CHAR')) { define('JSON_ERROR_CTRL_CHAR',3); }
	if(!defined('JSON_ERROR_SYNTAX')) { define('JSON_ERROR_SYNTAX',4); }
	if(!defined('JSON_ERROR_UTF8')) { define('JSON_ERROR_UTF8',5); }
}

VC_loadLangDefaults (); // set up the language defaults

if($charsetOutput == 'UTF-8') {
	foreach ($VClangCharsets as $l => $cs) {
		$VClangCharsets[$l] = 'UTF-8';
	}
	$Status .= "<!-- charsetOutput UTF-8 selected for all languages. -->\n";
	$Status .= "<!-- VClangCharsets\n".print_r($VClangCharsets,true)." \n-->\n";	
}

$VCLANG = 'en'; // Default to English for API
$lang = strtolower($lang); 	
if( isset($VClanguages[$lang]) ) { // if $lang is specified, use it
	$SITE['lang'] = $lang;
	$VCLANG = $VClanguages[$lang];
	$charsetOutput = (isset($VClangCharsets[$lang]))?$VClangCharsets[$lang]:$charsetOutput;
}

if(isset($_GET['lang']) and isset($VClanguages[strtolower($_GET['lang'])]) ) { // template override
	$lang = strtolower($_GET['lang']);
	$SITE['lang'] = $lang;
	$VCLANG = $VClanguages[$lang];
	$charsetOutput = (isset($VClangCharsets[$lang]))?$VClangCharsets[$lang]:$charsetOutput;
}

$doRTL = (strpos($RTLlang,$lang) !== false)?true:false;  // format RTL language in Right-to-left in output
if(isset($SITE['copyr']) and $doRTL) { 
 // running in a Saratoga template.  Turn off $doRTL
 $Status .= "<!-- running in Saratoga Template. doRTL set to false as template handles formatting -->\n";
 $doRTL = false;
}
if(isset($doShowConditions)) {$showConditions = $doShowConditions;}
if($doRTL) {$RTLopt = ' style="direction: rtl;"'; } else {$RTLopt = '';}; 

// get the selected forecast location code
$haveIndex = '0';
if (!empty($_GET['z']) && preg_match("/^[0-9]+$/i", htmlspecialchars($_GET['z']))) {
  $haveIndex = htmlspecialchars(strip_tags($_GET['z']));  // valid zone syntax from input
} 

if(!isset($VCforecasts[0])) {
	// print "<!-- making NWSforecasts array default -->\n";
	$VCforecasts = array("Saratoga|37.27465,-122.02295"); // create default entry
}

if(isset($useUTF8) and $useUTF8) {
	$charsetOutput = 'UTF-8';
	$Status .= "<!-- useUTF8 enabled -->\n";
}

if($charsetOutput == 'UTF-8') {
	foreach ($VClangCharsets as $l => $cs) {
		$VClangCharsets[$l] = 'UTF-8';
	}
	$Status .= "<!-- charsetOutput UTF-8 selected for all languages. -->\n";
	$Status .= "<!-- VClangCharsets\n".var_export($VClangCharsets,true)." \n-->\n";	
}

if(stripos($timeFormat,'g') !== false) {
	$showAMPMtime = true;
	$Status .= "<!-- timeFormat='$timeFormat'. timeline hours displayed as am/pm -->\n";
} else {
	$showAMPMtime = false;
	$Status .= "<!-- timeFormat='$timeFormat'. timeline hours displayed as 24hr time -->\n";
}

if(!headers_sent()) {
	header('Content-type: text/html,charset='.$charsetOutput);
}

//  print "<!-- NWSforecasts\n".print_r($VCforecasts,true). " -->\n";
// Set the default zone. The first entry in the $SITE['NWSforecasts'] array.
list($Nl,$Nn) = explode('|',$VCforecasts[0].'|||');
$FCSTlocation = $Nl;
$VC_LATLONG = $Nn;

if(!isset($VCforecasts[$haveIndex])) {
	$haveIndex = 0;
}

// locations added to the drop down menu and set selected zone values
$dDownMenu = '';
for ($m=0;$m<count($VCforecasts);$m++) { // for each locations
  list($Nlocation,$Nname) = explode('|',$VCforecasts[$m].'|||');
  $seltext = '';
  if($haveIndex == $m) {
    $FCSTlocation = $Nlocation;
    $VC_LATLONG = $Nname;
	$seltext = ' selected="selected" ';
  }
  $dDownMenu .= "     <option value=\"$m\"$seltext>".langtransstr($Nlocation)."</option>\n";
}

// build the drop down menu
$ddMenu = '';

// create menu if at least two locations are listed in the array
if (isset($VCforecasts[0]) and isset($VCforecasts[1])) {
	$ddMenu .= '<tr align="center">
      <td style="font-size: 14px; font-family: Arial, Helvetica, sans-serif">
      <script type="text/javascript">
        <!--
        function menu_goto( menuform ){
         selecteditem = menuform.logfile.selectedIndex ;
         logfile = menuform.logfile.options[ selecteditem ].value ;
         if (logfile.length != 0) {
          location.href = logfile ;
         }
        }
        //-->
      </script>
     <form action="" method="get">
     <p><select name="z" onchange="this.form.submit()"'.$RTLopt.'>
     <option value=""> - '.langtransstr('Select Forecast').' - </option>
' . $dDownMenu .
		$ddMenu . '     </select></p>
     <div><noscript><pre><input name="submit" type="submit" value="'.langtransstr('Get Forecast').'" /></pre></noscript></div>
     </form>
    </td>
   </tr>
';
}

$Force = false;

if (isset($_REQUEST['force']) and  $_REQUEST['force']=="1" ) {
  $Force = true;
}

$doDebug = false;
if (isset($_REQUEST['debug']) and strtolower($_REQUEST['debug'])=='y' ) {
  $doDebug = true;
}
$showTempsAs = ($showUnitsAs == 'us')? 'F':'C';
$Status .= "<!-- temps in $showTempsAs -->\n";

$fileName = "https://weather.visualcrossing.com/VisualCrossingWebServices/rest/services/timeline/$VC_LATLONG/" .
      "?key=$VCAPIkey&include=days,hours,alerts,current&lang=$VCLANG&unitGroup=$showUnitsAs&iconSet=icons2";

if ($doDebug) {
  $Status .= "<!-- VC URL: $fileName -->\n";
}


if ($autoSetTemplate and isset($_SESSION['CSSwidescreen'])) {
	if($_SESSION['CSSwidescreen'] == true) {
	   $maxWidth = '900px';
	   $maxIcons = 8;
	   $maxForecasts = 8;
	   $numIconsInFoldedRow = 7;
	   $Status .= "<!-- autoSetTemplate using ".$SITE['CSSwideOrNarrowDefault']." aspect. -->\n";	
	}
	if($_SESSION['CSSwidescreen'] == false) {
	   $maxWidth = '640px';
	   $maxIcons = 8;
	   $maxForecasts = 8;
	   $numIconsInFoldedRow = 7;
	   $Status .= "<!-- autoSetTemplate using ".$SITE['CSSwideOrNarrowDefault']." aspect. -->\n";	
	}
}

$cacheName = $cacheFileDir . $cacheName;
$cacheName = preg_replace('|\.txt|is',"-$haveIndex-$showUnitsAs-$lang.txt",$cacheName); // unique cache per language used

$APIfileName = $fileName; 

if($showConditions) {
	$refetchSeconds = 15*60; // shorter refresh time so conditions will be 'current'
}

if (! $Force and file_exists($cacheName) and filemtime($cacheName) + $refetchSeconds > time()) {
      $html = implode('', file($cacheName)); 
      $Status .= "<!-- loading from $cacheName (" . strlen($html) . " bytes) -->\n"; 
  } else { 
      $Status .= "<!-- loading from $APIfileName. -->\n"; 
      $html = VC_fetchUrlWithoutHanging($APIfileName,false); 
	  
    $RC = '';
	if (preg_match("|^HTTP\/\S+ (.*)\r\n|",$html,$matches)) {
	    $RC = trim($matches[1]);
	}
	$Status .= "<!-- RC=$RC, bytes=" . strlen($html) . " -->\n";
	if (preg_match('|30\d|',$RC)) { // handle possible blocked redirect
	   preg_match('|Location: (\S+)|is',$html,$matches);
	   if(isset($matches[1])) {
		  $sURL = $matches[1];
		  if(preg_match('|opendns.com|i',$sURL)) {
			  $Status .= "<!--  NOT following to $sURL --->\n";
		  } else {
			$Status .= "<!-- following to $sURL --->\n";
		
			$html = VC_fetchUrlWithoutHanging($sURL,false);
			$RC = '';
			if (preg_match("|^HTTP\/\S+ (.*)\r\n|",$html,$matches)) {
				$RC = trim($matches[1]);
			}
			$Status .= "<!-- RC=$RC, bytes=" . strlen($html) . " -->\n";
		  }
	   }
    }
		if(preg_match('!datetimeEpoch!is',$html)) {
      $fp = fopen($cacheName, "w"); 
			if (!$fp) { 
				$Status .= "<!-- unable to open $cacheName for writing. -->\n"; 
			} else {
        $write = fputs($fp, $html); 
        fclose($fp);  
			$Status .= "<!-- saved cache to $cacheName (". strlen($html) . " bytes) -->\n";
			} 
		} else {
			$Status .= "<!-- bad return from $APIfileName\n".print_r($html,true)."\n -->\n";
			if(file_exists($cacheName) and filesize($cacheName) > 3000) {
				$html = implode('', file($cacheName));
				$Status .= "<!-- reloaded stale cache $cacheName temporarily -->\n";
			} else {
				$Status .= "<!-- cache $cacheName missing or contains invalid contents -->\n";
				print $Status;
				print "<p>Sorry.. the Visualcrossing forecast is not available.</p>\n";
				return;
			}
		}
} 

 $charsetInput = 'UTF-8';
  
 $doIconv = ($charsetInput == $charsetOutput)?false:true; // only do iconv() if sets are different
 if($charsetOutput == 'UTF-8') {
	 $doIconv = false;
 }
 $Status .= "<!-- using charsetInput='$charsetInput' charsetOutput='$charsetOutput' doIconv='$doIconv' doRTL='$doRTL' -->\n";
 $tranTab = VC_loadTranslate($lang);
 
  $i = strpos($html,"\r\n\r\n");
  $headers = substr($html,0,$i-1);
  $content = substr($html,$i+4);
 //  process the file .. select out the 7-day forecast part of the page
  $UnSupported = false;

// --------------------------------------------------------------------------------------------------
  
 $Status .= "<!-- processing JSON entries for forecast -->\n";
  $i = strpos($html,"\r\n\r\n");
  $headers = substr($html,0,$i-1);
  $content = substr($html,$i+4);
 

  $rawJSON = $content;
  $Status .= "<!-- rawJSON size is ".strlen($rawJSON). " bytes -->\n";

  $rawJSON = VC_prepareJSON($rawJSON);
  $JSON = json_decode($rawJSON,true); // get as associative array
  $Status .= VC_decode_JSON_error();
  if(isset($_GET['debug'])) {$Status .= "<!-- JSON\n".print_r($JSON,true)." -->\n";}
	file_put_contents('VC-json.txt',var_export($JSON,true));
 
if(isset($JSON['days'][0]['datetime'])) { // got good JSON .. process it
   $UnSupported = false;

   $VCforecastcity = $FCSTlocation;
	 
   if($doIconv) {$VCforecastcity = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$VCforecastcity);}
   if($doDebug) {
     $Status .= "<!-- VCforecastcity='$VCforecastcity' -->\n";
   }
   //$VCtitle = langtransstr("Forecast");
	 $VCtitle = isset($tranTab['Visualcrossing Forecast for:'])?
	   $tranTab['Visualcrossing Forecast for:']:'Visualcrossing Forecast for:';
   if($doIconv) {$VCtitle = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$VCtitle);}
   if($doDebug) {
     $Status .= "<!-- VCtitle='$VCtitle' -->\n";
   }

/*
array (
  'queryCost' => 1,
  'latitude' => 37.27465,
  'longitude' => -122.02295,
  'resolvedAddress' => '37.27465,-122.02295',
  'address' => '37.27465,-122.02295',
  'timezone' => 'America/Los_Angeles',
  'tzoffset' => -8.0,
  'days' => 
  array (
    0 => 
    array (
      'datetime' => '2023-01-20',
      'datetimeEpoch' => 1674201600,
      'tempmax' => 12.5,
      'tempmin' => 3.6,
      'temp' => 6.5,
      'feelslikemax' => 12.5,
      'feelslikemin' => 1.0,
      'feelslike' => 5.3,
      'dew' => -0.3,
      'humidity' => 63.8,
      'precip' => 0.0,
      'precipprob' => 0.0,
      'precipcover' => 0.0,
      'preciptype' => NULL,
      'snow' => 0.0,
      'snowdepth' => 0.0,
      'windgust' => 33.5,
      'windspeed' => 17.9,
      'winddir' => 327.4,
      'pressure' => 1025.7,
      'cloudcover' => 2.0,
      'visibility' => 16.1,
      'solarradiation' => 140.9,
      'solarenergy' => 11.9,
      'uvindex' => 5.0,
      'severerisk' => 10.0,
      'sunrise' => '07:19:11',
      'sunriseEpoch' => 1674227951,
      'sunset' => '17:19:29',
      'sunsetEpoch' => 1674263969,
      'moonphase' => 0.99,
      'conditions' => 'Clear',
      'description' => 'Clear conditions throughout the day.',
      'icon' => 'clear-day',
      'stations' => 
      array (
        0 => 'KSJC',
        1 => 'C1792',
        2 => 'KNUQ',
        3 => 'KWVI',
      ),
      'source' => 'comb',
      'hours' => 
      array (
        0 => 

*/
  if(isset($JSON['timezone'])) {
		date_default_timezone_set($JSON['timezone']);
		$Status .= "<!-- using '".$JSON['timezone']."' for timezone -->\n";
	}
	if(isset($JSON['days'][0]['datetimeEpoch'])) {
		$VCupdated = $tranTab['Updated:'];
		if($doIconv) { 
		  $VCupdated = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$VCupdated). ' '; 
		}
	  $VCupdated .= date($timeFormat,$JSON['days'][0]['datetimeEpoch']);
	} else {
		$VCupdated = '';
	}
	
	if($doDebug) {
		$Status .= "\n<!-- JSON daily:data count=" . count( $JSON['daily']) . "-->\n";
	}
	if(isset($windUnits[$showUnitsAs])) {
		$windUnit = $windUnits[$showUnitsAs];
		$Status .= "<!-- wind unit for '$showUnitsAs' set to '$windUnit' -->\n";
		if(isset($tranTab[$windUnit])) {
			$windUnit = $tranTab[$windUnit];
			$Status .= "<!-- wind unit translation for '$showUnitsAs' set to '$windUnit' -->\n";
		}
	} else {
		$windUnit = '';
	}

  $n = 0;
  foreach ($JSON['days'] as $i => $FCpart) {
#   process each daily entry

		list($tDay,$tTime) = explode(" ",date('l H:i:s',$FCpart['datetimeEpoch']));
		if ($doDebug) {
				$Status .= "<!-- period $n ='$tDay $tTime' -->\n";
		}
		$VCforecastdayname[$n] = $tDay;	
		if(isset($tranTab[$tDay])) {
			$VCforecastday[$n] = $tranTab[$tDay];
		} else {
			$VCforecastday[$n] = $tDay;
		}
    if($doIconv) {
		  $VCforecastday[$n] = iconv("UTF-8",$charsetOutput.'//IGNORE',$VCforecastday[$n]);
	  }
		$VCforecasttitles[$n] = $VCforecastday[$n];
		if ($doDebug) {
				$Status .= "<!-- VCforecastday[$n]='" . $VCforecastday[$n] . "' -->\n";
		}	
		$VCforecastcloudcover[$n] = $FCpart['cloudcover']/100.0; # convert to fraction percent

#  extract the temperatures

	  $VCforecasttemp[$n] = "<span style=\"color: #ff0000;\">".VC_round($FCpart['tempmax'],0)."&deg;$showTempsAs</span>";
	  $VCforecasttemp[$n] .= "<br/><span style=\"color: #0000ff;\">".VC_round($FCpart['tempmin'],0)."&deg;$showTempsAs</span>";

#  extract the icon to use
	  $VCforecasticon[$n] = $FCpart['icon'];
	if ($doDebug) {
      $Status .= "<!-- VCforecasticon[$n]='" . $VCforecasticon[$n] . "' -->\n";
	}	

	if(isset($FCpart['precipprob'])) {
	  $VCforecastpop[$n] = round($FCpart['precipprob'],0);
	} else {
		$VCforecastpop[$n] = 0;
	}
	if ($doDebug) {
      $Status .= "<!-- VCforecastpop[$n]='" . $VCforecastpop[$n] . "' -->\n";
	}
	
	if(isset($FCpart['preciptype']) and !empty($FCpart['preciptype'])) {
		$VCforecastpreciptypeEN[$n] = join(',',$FCpart['preciptype']);
		$VCforecastpreciptype[$n] = $FCpart['preciptype'];
	} else {
		$VCforecastpreciptypeEN[$n] = '';
	}


	$VCforecasttext[$n] =  // replace problematic characters in forecast text
	   str_replace(
		 array('<',   '>',  'â€“','cm.','in.','.)'),
		 array('&lt;','&gt;','-', 'cm', 'in',')'),
	   trim($FCpart['description']));
		 
		 if(strpos($VCforecasttext[$n],'.') === false) {$VCforecasttext[$n] .= '.'; }

# Add info to the forecast text
	if($VCforecastpop[$n] > 0) {
		$tstr = '';
		if(!empty($VCforecastpreciptype[$n])) {
			$t = $VCforecastpreciptype[$n];
			foreach ($t as $k => $ptype) {
				if(!empty($ptype)) {$tstr .= $tranTab[$ptype].',';}
			}
			if(strlen($tstr)>0) {
				$tstr = '('.substr($tstr,0,strlen($tstr)-1) .') ';
			}
			$Status .= "<!-- VCforecastpreciptype[$n]='".var_export($VCforecastpreciptype[$n],true)." -->\n";
		}
		$VCforecasttext[$n] .= " ".
		   $tranTab['Chance of precipitation']." $tstr".$VCforecastpop[$n]."%. ";
	}

  $VCforecasttext[$n] .= " ".$tranTab['High:']." ".VC_round($FCpart['tempmax'],0)."&deg;$showTempsAs. ";

  $VCforecasttext[$n] .= " ".$tranTab['Low:']." ".VC_round($FCpart['tempmin'],0)."&deg;$showTempsAs. ";

	$tWdir = VC_WindDir(round($FCpart['winddir'],0));
  $VCforecasttext[$n] .= " ".$tranTab['Wind']." ".VC_WindDirTrans($tWdir);
  $VCforecasttext[$n] .= " ".
	     round($FCpart['windspeed'],0)."-&gt;".round($FCpart['windgust'],0) .
	     " $windUnit.";

	if(isset($FCpart['uvindex']) and $FCpart['uvindex'] > 1) {
    $VCforecasttext[$n] .= " ".$tranTab['UV index']." ".round($FCpart['uvindex'],0).".";
	}

  if($doIconv) {
		$VCforecasttext[$n] = iconv("UTF-8",$charsetOutput.'//IGNORE',$VCforecasttext[$n]);
	}

	if ($doDebug) {
      $Status .= "<!-- VCforecasttext[$n]='" . $VCforecasttext[$n] . "' -->\n";
	}

	#$temp = explode('.',$VCforecasttext[$n]); // split as sentences (sort of).
	
	#$VCforecastcond[$n] = trim($temp[0]); // take first one as summary.
  $VCforecastcond[$n] = $FCpart['conditions'];
  if($doIconv) {
		$VCforecastcond[$n] = iconv("UTF-8",$charsetOutput.'//IGNORE',$VCforecastcond[$n]);
	}
	if ($doDebug) {
      $Status .= "<!-- forecastcond[$n]='" . $VCforecastcond[$n] . "' -->\n";
	}
  $VCpreciptype[$n] = $FCpart['preciptype'];
	
	$VCforecasticons[$n] = $VCforecastday[$n] . "<br/>" .
		 VC_img_replace(
			 $VCforecasticon[$n],
			 $VCforecastcond[$n],
			 (integer)round($VCforecastpop[$n],-1),
			 $VCforecastcloudcover[$n],
			 $VCpreciptype[$n]) . 
		 ' ' .
		 $VCforecastpreciptypeEN[$n] 
		 . "<br/>" .
	 $VCforecastcond[$n];
	$n++;
  } // end of process text forecasts

  if(isset($JSON['flags']['sources'])) {
		$dsSources = VC_sources($JSON['flags']['sources']);
		// $Status .= "<!-- sources\n".$dsSources." -->\n";
	} else {
		$dsSources = '';
	}
  // process alerts if any are available 
	$VCforecastwarnings = '';
  if (isset($JSON['alerts']) and is_array($JSON['alerts']) and count($JSON['alerts']) > 0) {
    $Status.= "<!-- preparing " . count($JSON['alerts']) . " warning links -->\n";
    foreach($JSON['alerts'] as $i => $ALERT) {
			$expireUTC = strtotime($ALERT['ends']);
      $expires = date('Y-m-d H:i T',$ALERT['endsEpoch']);
      $Status.= "<!-- alert expires $expires (" . $ALERT['ends'] . ") -->\n";
			$regions = '';
			if(isset($ALERT['regions']) and is_array($ALERT['regions'])) {
				foreach ($ALERT['regions'] as $i => $reg) {
					$regions .= $reg . ', ';
				}
				$regions = substr($regions,0,strlen($regions)-2);
			}
					
      if (time() < $expireUTC) {
        $VCforecastwarnings .= '<a href="' . $ALERT['link'] . '"' . ' title="' . $ALERT['event'] . " expires $expires\n$regions\n---\n" . $ALERT['description'] . '" target="_blank">' . '<strong><span style="color: red">' . $ALERT['event'] . "</span></strong></a><br/>\n";
      }
      else {
        $Status.= "<!-- alert " . $ALERT['headline'] . " " . " expired - " . $ALERT['ends'] . " -->\n";
      }
    }
  }
  else {
    $Status.= "<!-- no current hazard alerts found-->\n";
  }

// make the Current conditions table from $currently array
$currently = $JSON['currentConditions'];
/*
  'currentConditions' => 
  array (
    'datetime' => '15:57:10',
    'datetimeEpoch' => 1674259030,
    'temp' => 11.2,
    'feelslike' => 11.2,
    'humidity' => 46.0,
    'dew' => -0.1,
    'precip' => 0.0,
    'precipprob' => 0.0,
    'snow' => 0.0,
    'snowdepth' => 0.0,
    'preciptype' => NULL,
    'windgust' => NULL,
    'windspeed' => 11.9,
    'winddir' => 316.0,
    'pressure' => 1026.0,
    'visibility' => 16.0,
    'cloudcover' => 0.0,
    'solarradiation' => 248.0,
    'solarenergy' => 0.9,
    'uvindex' => 2.0,
    'conditions' => 'Clear',
    'icon' => 'clear-day',
    'stations' => 
    array (
      0 => 'KSJC',
      1 => 'C1792',
      2 => 'KNUQ',
    ),
    'source' => 'obs',
    'sunrise' => '07:19:11',
    'sunriseEpoch' => 1674227951,
    'sunset' => '17:19:29',
    'sunsetEpoch' => 1674263969,
    'moonphase' => 0.99,
  ),
*/
$nCols = 3; // number of columns in the conditions table
	
if (isset($currently['datetimeEpoch']) ) { // only generate if we have the data
	if (isset($currently['icon']) and ! $currently['icon'] ) { $nCols = 2; };
	
	
	$VCcurrentConditions = '<table class="VCforecast" cellpadding="3" cellspacing="3" style="border: 1px solid #909090;">' . "\n";
	
	$VCcurrentConditions .= '
  <tr><td colspan="' . $nCols . '" align="center" '.$RTLopt.'><small>' . 
  $tranTab['Currently'].': '. date($timeFormat,$currently['datetimeEpoch']) . "<br/>\n";
	$tS = $currently['stations'][0]; // Name of first station in list
	$tD = isset($JSON['stations'][$tS]['distance'])?$JSON['stations'][$tS]['distance']:0.0;
	switch ($showUnitsAs) {
		case 'metric': $tD = round($tD/1000,1); break;
		case 'us'    : $tD = $tD; break;
		case 'uk'    : $tD = $tD; break;
		default      : $tD = $tD;
	}
	$t = $tranTab['Weather conditions at 999 from forecast point.'];
	$t = str_replace('999',$tD.' '.$Units['D'],$t);
	$VCcurrentConditions .= $t .
  '</small></td></tr>' . "\n<tr$RTLopt>\n";
  if (isset($currently['icon'])) {
    $VCcurrentConditions .= '
    <td align="center" valign="middle">' . 
       VC_img_replace(
			 $currently['icon'],
			 $currently['conditions'],
			 round($currently['precipprob'],-1),
			 $currently['cloudcover'],
			 $currently['preciptype']) . "<br/>\n" .
			 $currently['conditions'];
	$VCcurrentConditions .= '    </td>
';  
    } // end of icon
    $VCcurrentConditions .= "
    <td valign=\"middle\">\n";

	if (isset($currently['temp'])) {
	  $VCcurrentConditions .= $tranTab['Temperature'].": <b>".
	  VC_round($currently['temp'],0) . $Units['T'] . "</b><br/>\n";
	}
	if (isset($currently['windchill'])) {
	  $VCcurrentConditions .= $tranTab['Wind chill'].": <b>".
	  VC_round($currently['windchill'],0) . $Units['T']. "</b><br/>\n";
	}
	if (isset($currently['heatindex'])) {
	  $VCcurrentConditions .= $tranTab['Heat index'].": <b>" .
	  VC_round($currently['heatindex']) . $Units['T']. "</b><br/>\n";
	}
	if (isset($currently['windspeed'])) {
		$tWdir = VC_WindDir(round($currently['winddir'],0));
  $VCcurrentConditions .= $tranTab['Wind'].": <b>".VC_WindDirTrans($tWdir);
  $VCcurrentConditions .= " ".round($currently['windspeed'],0).
	   "-&gt;".round($currently['windgust'],0) . " $windUnit." .
		"</b><br/>\n";
	}
	if (isset($currently['humidity'])) {
	  $VCcurrentConditions .= $tranTab['Humidity'].": <b>".
	  round($currently['humidity'],0) . "%</b><br/>\n";
	}
	if (isset($currently['dew'])) {
	  $VCcurrentConditions .= $tranTab['Dew Point'].": <b>".
	  VC_round($currently['dew'],0) . $Units['T'] . "</b><br/>\n";
	}
	
	$VCcurrentConditions .= $tranTab['Barometer'].": <b>".
	VC_conv_baro($currently['pressure']) . " " . $Units['P'] . "</b><br/>\n";
	
	if (isset($currently['visibility'])) {
	  $VCcurrentConditions .= $tranTab['Visibility'].": <b>".
	  round($currently['visibility'],1) . " " . $Units['D']. "</b>\n" ;
	}

	if (isset($currently['uvindex'])) {
	  $VCcurrentConditions .= '<br/>'.$tranTab['UV index'].": <b>".
	  round($currently['uvindex'],0) .  "</b>\n" ;
	}
	
	$VCcurrentConditions .= '	   </td>
';
	$VCcurrentConditions .= '    <td valign="middle">
';
	if(isset($currently['sunriseEpoch']) and 
	   isset($currently['sunsetEpoch']) ) {
	  $VCcurrentConditions .= 
	  $tranTab['Sunrise'].': <b>'. 
		   date('H:i',$currently['sunriseEpoch']) . 
			 "</b><br/>\n" .
		$tranTab['Sunset'].': <b>'.
	     date('H:i',$currently['sunsetEpoch']) . 
			 "</b><br/>\n" ;
	}
	$VCcurrentConditions .= '
	</td>
  </tr>
';
  if(isset($JSON['daily']['summary'])) {
		if($doRTL) {
  $VCcurrentConditions .= '
	<tr><td colspan="' . $nCols . '" align="center" style="width: 350px;direction: rtl;"><small>' .
	$JSON['daily']['summary'] . 
	'</small></td>
	</tr>
'; } else {
  $VCcurrentConditions .= '
	<tr><td colspan="' . $nCols . '" align="center" style="width: 350px;"><small>' .
	$JSON['daily']['summary'] . 
	'</small></td>
	</tr>
';	
}
	}
  $VCcurrentConditions .= '
</table>
';
  if($doIconv) {
		$VCcurrentConditions = 
		  iconv('UTF-8',$charsetOutput.'//TRANSLIT',$VCcurrentConditions);
	}
		
} // end of if isset($currently['cityobserved'])
// end of current conditions mods
/* icon meanings
clear-day	Cloud cover is less than 20% cover during day time
clear-night	Cloud cover is less than 20% cover during night time 
cloudy	Cloud cover is greater than 90% cover
fog	Visibility is low (lower than one kilometer or mile)
partly-cloudy-day	Cloud cover is greater than 20% cover during day time.
partly-cloudy-night	Cloud cover is greater than 20% cover during night time.
rain	Amount of rainfall is greater than zero
showers-day	Rain showers during the day
showers-night	Rain showers during the night
snow	Amount of snow is greater than zero
snow-showers-day	Periods of snow during the day
snow-showers-night	Periods of snow during the night
thunder-rain	Thunderstorms throughout the day or night
thunder-showers-day	Possible thunderstorms throughout the day
thunder-showers-night	Possible thunderstorms throughout the night
wind	Wind speed is high (greater than 30 kph or mph)*/
$timelineColors = array(
// timeline Colors to use for each condition
   "clear-day" => "#eeeef5",
   "clear-night" => "#eeeef5",
   "partly-cloudy-day" => "#d5dae2",
   "partly-cloudy-night" => "#d5dae2",
   "cloudy"=> "#b6bfcb",
   "rain"  => "#4a80c7",
   "showers-day" => "#80a5d6",
   "showers-night" => "#80a5d6",
   "thunder-rain" => "#ffdead",
   "thunder-showers-day" => "#ffdead",
   "thunder-showers-night" => "#ffdead",
   "snow" => "#8c82ce",
   "snow-showers-day" => "#aba4db",
   "snow-showers-night" => "#aba4db",
   "Flurries"=> "#b7b2db",
   "Sleet"=> "#6264a7",
   "fog" => "#d5dae2",
   "wind" => "#ffdead",
   "Windy" => "#ffdead",

);

if(isset($JSON['days'][0]['hours'][0]['datetimeEpoch'])) { // process Hourly forecast data
/*
     'hours' => 
      array (
        0 => 
        array (
          'datetime' => '00:00:00',
          'datetimeEpoch' => 1674201600,
          'temp' => 5.2,
          'feelslike' => 5.2,
          'humidity' => 80.74,
          'dew' => 2.2,
          'precip' => 0.0,
          'precipprob' => 0.0,
          'snow' => 0.0,
          'snowdepth' => 0.0,
          'preciptype' => NULL,
          'windgust' => 11.2,
          'windspeed' => 0.0,
          'winddir' => 0.0,
          'pressure' => 1024.1,
          'visibility' => 16.0,
          'cloudcover' => 0.0,
          'solarradiation' => 0.0,
          'solarenergy' => NULL,
          'uvindex' => 0.0,
          'severerisk' => 10.0,
          'conditions' => 'Clear',
          'icon' => 'clear-night',
          'stations' => 
          array (
            0 => 'KSJC',
            1 => 'KNUQ',
            2 => 'KWVI',
          ),
          'source' => 'obs',
        ),
*/
  $newJSON = array(); // storage for the merry-timeline JSON
  foreach ($JSON['days'] as $dayNumber => $dayData) {
/*
  {
    "color": "#b7b2db",
    "text": "Flurries",
    "annotation": "0°",
    "time": 1672257600
  },
*/
    $tempUOM = str_replace('&deg;'," ",$UnitsTab[$showUnitsAs]['T']);	
    foreach($dayData['hours'] as $i => $H) {
     $dayname = date('l',$H['datetimeEpoch']);
		 if(isset($tranTab[$dayname])) {$dayname = $tranTab[$dayname];
		 if($doIconv) {iconv($charsetInput,$charsetOutput.'//TRANSLIT',$dayname); }
		 $dayname .= ', '.date('d',$H['datetimeEpoch']);
		 $tColor = isset($timelineColors[$H['icon']])?$timelineColors[$H['icon']]:'#fefefe';
		 $text = $H['conditions'];
		 if($doIconv) {iconv($charsetInput,$charsetOutput.'//TRANSLIT',$text);}
     $newJSON[$dayname][] = array(
		   'color' => $tColor,
			 'text'  => $text,
			 'annotation' => round($H['temp'],0).$tempUOM,
			 'time'  => $H['datetimeEpoch']
			 );
	} // end each hourly forecast parsing

 } // end process hourly forecast data

} // end loop over day/hours

  $utfdata = json_encode($newJSON,JSON_UNESCAPED_UNICODE);
  $merrytimelineJSON = ($doIconv)?iconv('UTF-8',$charsetOutput.'//TRANSLIT//IGNORE',$utfdata):$utfdata;
	$hourlyData =  "<script type=\"text/javascript\">\n";
	$hourlyData .= "var showAMPMtime = ";
	$hourlyData .= ($showAMPMtime)?'true;':'false;';
	$hourlyData .= " // =true use AMPM, =false use 24h time on timeline\n";
	$hourlyData .= "// hourly data \n// <![CDATA[\n";
	$hourlyData .= "var weatherData = ".$merrytimelineJSON.";\n// ]]>\n";
	$hourlyData .= "</script>\n";
	if(isset($_GET['debug'])) {
		$hourlyData .= "<!-- newJSON array\n".var_export($newJSON,true)." -->\n";
	}
  if(isset($_GET['snapshot'])) {
		file_put_contents('./raw-weatherData-json.txt',$merrytimelineJSON);
		file_put_contents('./raw-newJSON-array.txt',var_export($newJSON,true));
		$Status .= "<!-- snapshots taken -->\n";
	}

} // end setup for merry-timeline

/*  // old version
if(isset($JSON['hourly']['data'][0]['time'])) { // process Hourly forecast data
/*
	"hourly": {
		"summary": "Mostly cloudy throughout the day.",
		"icon": "partly-cloudy-night",
		"data": [{
				"time": 1548018000,
				"summary": "Mostly Cloudy",
				"icon": "partly-cloudy-day",
				"precipIntensity": 0.1422,
				"precipProbability": 0.29,
				"precipType": "rain",
				"temperature": 14.91,
				"apparentTemperature": 14.91,
				"dewPoint": 11.49,
				"humidity": 0.8,
				"pressure": 1017.89,
				"windSpeed": 10.8,
				"windGust": 24.54,
				"windBearing": 226,
				"cloudCover": 0.88,
				"uvIndex": 2,
				"visibility": 14.11,
				"ozone": 289.95
			}, {
//*/
/*
  foreach($JSON['hourly']['data'] as $i => $FCpart) {
    $VCforecasticonHR[$i] = VC_gen_hourforecast($FCpart);
		
		if($doIconv) { 
		  $VCforecasticonHR[$i]['icon'] = 
			  iconv($charsetInput,$charsetOutput.'//TRANSLIT',$VCforecasticonHR[$i]['icon']). ' '; 
		  $VCforecasticonHR[$i]['temp'] = 
			  iconv($charsetInput,$charsetOutput.'//TRANSLIT',$VCforecasticonHR[$i]['temp']). ' '; 
		  $VCforecasticonHR[$i]['wind'] = 
			  iconv($charsetInput,$charsetOutput.'//TRANSLIT',$VCforecasticonHR[$i]['wind']). ' '; 
		  $VCforecasticonHR[$i]['precip'] = 
			  iconv($charsetInput,$charsetOutput.'//TRANSLIT',$VCforecasticonHR[$i]['precip']). ' '; 
		}
		if($doDebug) {
		  $Status .= "<!-- hour $i ".$VCforecasticonHR[$i]." -->\n";
		}
		

	} // end each hourly forecast parsing
} // end process hourly forecast data
  
*/
  
 
} // end got good JSON decode/process

// end process JSON style --------------------------------------------------------------------

// All finished with parsing, now prepare to print
  if(!isset($VCforecasticons[0]) ) {print "<p>Forecast not available.</p>\n"; print $Status; exit(0); }
  $wdth = intval(100/count($VCforecasticons));
  $ndays = intval(count($VCforecasticon)/2);
  
  $doNumIcons = $maxIcons;
  if(count($VCforecasticons) < $maxIcons) { $doNumIcons = count($VCforecasticons); }

  $IncludeMode = false;
  $PrintMode = true;

  if (isset($doPrintVC) && ! $doPrintVC ) {
      print $Status;
      return;
  }
  if (isset($_REQUEST['inc']) && 
      strtolower($_REQUEST['inc']) == 'noprint' ) {
      print $Status;
	  return;
  }

if (isset($_REQUEST['inc']) && strtolower($_REQUEST['inc']) == 'y') {
  $IncludeMode = true;
}
if (isset($doIncludeVC)) {
  $IncludeMode = $doIncludeVC;
}

$printHeading = true;
$printIcons = true;
$printText = true;

if (isset($doPrintHeadingVC)) {
  $printHeading = $doPrintHeadingVC;
}
if (isset($_REQUEST['heading']) ) {
  $printHeading = substr(strtolower($_REQUEST['heading']),0,1) == 'y';
}

if (isset($doPrintIconsVC)) {
  $printIcons = $doPrintIconsVC;
}
if (isset($_REQUEST['icons']) ) {
  $printIcons = substr(strtolower($_REQUEST['icons']),0,1) == 'y';
}
if (isset($doPrintTextVC)) {
  $printText = $doPrintTextVC;
}
if (isset($_REQUEST['text']) ) {
  $printText = substr(strtolower($_REQUEST['text']),0,1) == 'y';
}


if (! $IncludeMode and $PrintMode) { ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title><?php echo $VCtitle . ' - ' . $VCforecastcity; ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $charsetOutput; ?>" />
<style type="text/css">
/*--------------------------------------------------
  tabbertab 
  --------------------------------------------------*/
/* $Id: example.css,v 1.5 2006/03/27 02:44:36 pat Exp $ */

/*--------------------------------------------------
  REQUIRED to hide the non-active tab content.
  But do not hide them in the print stylesheet!
  --------------------------------------------------*/
.tabberlive .tabbertabhide {
 display:none;
}

/*--------------------------------------------------
  .tabber = before the tabber interface is set up
  .tabberlive = after the tabber interface is set up
  --------------------------------------------------*/
.tabber {
}
.tabberlive {
 margin-top:1em;
}

/*--------------------------------------------------
  ul.tabbernav = the tab navigation list
  li.tabberactive = the active tab
  --------------------------------------------------*/
ul.tabbernav
{
 margin:0 0 3px 0;
 padding: 0 3px ;
 border-bottom: 0px solid #778;
 font: bold 12px Verdana, sans-serif;
}

ul.tabbernav li
{
 list-style: none;
 margin: 0;
 min-height:40px;
 display: inline;
}

ul.tabbernav li a
{
 padding: 3px 0.5em;
	min-height: 40px;
	border-top-left-radius: 5px;
	border-top-right-radius: 5px;
 margin-left: 3px;
 border: 1px solid #778;
 border-bottom: none;
 background: #DDE  !important;
 text-decoration: none !important;
}

ul.tabbernav li a:link { color: #448  !important;}
ul.tabbernav li a:visited { color: #667 !important; }

ul.tabbernav li a:hover
{
 color: #000;
 background: #AAE !important;
 border-color: #227;
}

ul.tabbernav li.tabberactive a
{
 background-color: #fff !important;
 border-bottom: none;
}

ul.tabbernav li.tabberactive a:hover
{
 color: #000;
 background: white !important;
 border-bottom: 1px solid white;
}

/*--------------------------------------------------
  .tabbertab = the tab content
  Add style only after the tabber interface is set up (.tabberlive)
  --------------------------------------------------*/
.tabberlive .tabbertab {
 padding:5px;
 border:0px solid #aaa;
 border-top:0;
	overflow:auto;

}

/* If desired, hide the heading since a heading is provided by the tab */
.tabberlive .tabbertab h2 {
 display:none;
}
.tabberlive .tabbertab h3 {
 display:none;
}
</style>	
</head>
<body style="font-family:Verdana, Arial, Helvetica, sans-serif; font-size:12px; background-color:#FFFFFF">

<?php
} // end printmode and not includemode
print $Status;
// if the forecast text is blank, prompt the visitor to force an update
setup_tabber(); // print the tabber JavaScript so it is available

if($UnSupported) {

  print <<< EONAG
<h1>Sorry.. this <a href="https://visualcrossing.com/forecast/$VC_LATLONG/{$showUnitsAs}12/$VCLANG">forecast</a> can not be processed at this time.</h1>


EONAG
;
}

if (strlen($VCforecasttext[0])<2 and $PrintMode and ! $UnSupported ) {

  echo '<br/><br/>'.langtransstr('Forecast blank?').' <a href="' . $PHP_SELF . '?force=1">' .
	 langtransstr('Force Update').'</a><br/><br/>';

} 
if ($PrintMode and ($printHeading or $printIcons)) { 

?>
  <table width="<?php print $maxWidth; ?>" style="border: none;" class="VCforecast">
  <?php echo $ddMenu ?>
<?php
  if ($showConditions) {
	  print "<tr><td align=\"center\">\n";
    print $VCcurrentConditions;
	  print "</td></tr>\n";
  }

?>
    <?php if($printHeading) { ?>
    <tr align="center" style="background-color: #FFFFFF;<?php 
		if($doRTL) { echo 'direction: rtl;'; } ?>">
      <td><b><?php echo $VCtitle; ?></b> <span style="color: green;">
	   <?php echo $VCforecastcity; ?></span>
     <?php if(strlen($VCupdated) > 0) {
			 echo "<br/>$VCupdated\n";
		 }
		 ?>
      </td>
    </tr>
  </table>
  <p>&nbsp;</p>
<div class="tabber" style="width: 99%; margin: 0 auto;"><!-- Day Forecast tab begin -->
  <div class="tabbertab" style="padding: 0;">
    <h2><?php 
$t = $tranTab['Daily Forecast'];
if($doIconv) { 
	$t = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$t). ' '; 
}
echo $t; ?></h2>
    <div style="width: 99%;">

  <table width="<?php print $maxWidth; ?>" style="border: none;" class="VCforecast">
	<?php } // end print heading
	
	if ($printIcons) {
	?>
    <tr>
      <td align="center">
	    <table width="100%" border="0" cellpadding="0" cellspacing="0">  
	<?php
	  // see if we need to fold the icon rows due to long text length
	  $doFoldRow = false; // don't assume we have to fold the row..
	  if($foldIconRow) {
		  $iTitleLen =0;
		  $iTempLen = 0;
		  $iCondLen = 0;
		  for($i=0;$i<$doNumIcons;$i++) {
			$iTitleLen += strlen(strip_tags($VCforecasttitles[$i]));
			$iCondLen += strlen(strip_tags($VCforecastcond[$i]));
			$iTempLen += strlen(strip_tags($VCforecasttemp[$i]));  
		  }
		  print "<!-- lengths title=$iTitleLen cond=$iCondLen temps=$iTempLen -->\n";
		  $maxChars = 135;
		  if($iTitleLen >= $maxChars or 
		     $iCondLen >= $maxChars or
			 $iTempLen >= $maxChars ) {
				 print "<!-- folding icon row -->\n";
				 $doFoldRow = true;
			 } 
			 
	  }
	  $startIcon = 0;
	  $finIcon = $doNumIcons;
	  $incr = $doNumIcons;
		$doFoldRow = false;
	  if ($doFoldRow) { $wdth = $wdth*2; $incr = $numIconsInFoldedRow; }
  print "<!-- numIconsInFoldedRow=$numIconsInFoldedRow startIcon=$startIcon doNumIcons=$doNumIcons incr=$incr -->\n";
	for ($k=$startIcon;$k<$doNumIcons-1;$k+=$incr) { // loop over icon rows, 5 at a time until done
	  $startIcon = $k;
	  if ($doFoldRow) { 
		  $finIcon = $startIcon+$numIconsInFoldedRow; 
		} else { 
		  $finIcon = $doNumIcons; 
		}
	  $finIcon = min($finIcon,$doNumIcons);
	  print "<!-- start=$startIcon fin=$finIcon num=$doNumIcons -->\n";
    print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
	  
	  for ($i=$startIcon;$i<$finIcon;$i++) {
		$ni = $doRTL?$numIconsInFoldedRow-1-$i+$startIcon+$k:$i; 
		print "<!-- doRTL:$doRTL i=$i k=$k -->\n"; 
	    print "<td style=\"width: $wdth%; text-align: center;\"><span style=\"font-size: 8pt;\">$VCforecasttitles[$ni]</span><!-- $ni '".$VCforecastdayname[$ni]."' --></td>\n";
		
	  }
	
print "          </tr>\n";	
    print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
	
	  for ($i=$startIcon;$i<$finIcon;$i++) {
		$ni = $doRTL?$numIconsInFoldedRow-1-$i+$startIcon+$k:$i;  
	    print "<td style=\"width: $wdth%;\">" . 
			VC_img_replace($VCforecasticon[$ni],
			$VCforecastcond[$ni],
			round($VCforecastpop[$ni],-1),
			$VCforecastcloudcover[$ni],
			$VCpreciptype[$ni])
			 . "<!-- $ni --></td>\n";
			
	  }
	?>
          </tr>	
	      <tr valign ="top" align="center">
	<?php
	  for ($i=$startIcon;$i<$finIcon;$i++) {
		$ni = $doRTL?$numIconsInFoldedRow-1-$i+$startIcon+$k:$i;  

	    print "<td style=\"width: $wdth%; text-align: center;\"><span style=\"font-size: 8pt;\">$VCforecastcond[$ni]</span><!-- $ni '".$VCforecastdayname[$ni]."' --></td>\n";
	  }
	
      print "	      </tr>\n";	
      print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
	  
	  for ($i=$startIcon;$i<$finIcon;$i++) {
		$ni = $doRTL?$numIconsInFoldedRow-1-$i+$startIcon+$k:$i;  
	    print "<td style=\"width: $wdth%; text-align: center;\">$VCforecasttemp[$ni]</td>\n";
	  }
	  ?>
          </tr>
	<?php if(! $iconDir) { // print a PoP row since they aren't using icons 
    print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
	
	  for ($i=$startIcon;$i<$finIcon;$i++) {
		$ni = $doRTL?$numIconsInFoldedRow-1-$i+$startIcon+$k:$i;  
	    print "<td style=\"width: $wdth%; text-align: center;\">";
	    if($VCforecastpop[$ni] > 0) {
  		  print "<span style=\"font-size: 8pt; color: #009900;\">PoP: $VCforecastpop[$ni]%</span>";
		} else {
		  print "&nbsp;";
		}
		print "</td>\n";
		
	  }
	?>
          </tr>	
	  <?php } // end if iconDir ?>
      <?php if ($doFoldRow) { 
    print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
	  
	  for ($i=$startIcon;$i<$finIcon;$i++) {
	    print "<td style=\"width: $wdth%; text-align: center;\">&nbsp;<!-- $i --></td>\n";
      
	  }
		print "</tr>\n";
      } // end doFoldRow ?>
  <?php } // end of foldIcon loop ?>
        </table><!-- end icon table -->
     </td>
   </tr><!-- end print icons -->
   	<?php } // end print icons ?>
</table>
<br/>
<?php } // end print header or icons

if ($PrintMode and $printText) { ?>
<?php
  if ($VCforecastwarnings <> '') {
		if($doIconv) { 
		  $VCforecastwarnings = 
			  iconv($charsetInput,$charsetOutput.'//IGNORE',$VCforecastwarnings); 
		}
		$tW = 'width: 640px;';
		if($doRTL) {$tW .= 'direction: rtl;';}
    print "<p class=\"VCforecast\"$tW>$VCforecastwarnings</p>\n";
  }
?>
<br/>
<table style="border: 0" width="<?php print $maxWidth; ?>" class="VCforecast">
	<?php
	  for ($i=0;$i<count($VCforecasttitles);$i++) {
        print "<tr valign =\"top\"$RTLopt>\n";
		if(!$doRTL) { // normal Left-to-right
	      print "<td style=\"width: 20%;\"><b>$VCforecasttitles[$i]</b><br />&nbsp;<br /></td>\n";
	      print "<td style=\"width: 80%;\">$VCforecasttext[$i]</td>\n";
		} else { // print RTL format
	      print "<td style=\"width: 80%; text-align: right;\">$VCforecasttext[$i]</td>\n";
	      print "<td style=\"width: 20%; text-align: right;\"><b>$VCforecasttitles[$i]</b><br />&nbsp;<br /></td>\n";
		}
		print "</tr>\n";
	  }
	?>
   </table>
<?php } // end print text ?>
<?php if ($PrintMode) { ?>
   </div>
 </div> <!-- end first tab --> 

  <div class="tabbertab" style="padding: 0;"><!-- begin second tab -->
    <h2><?php $t = $tranTab['Hourly Forecast'];
if($doIconv) { 
	$t = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$t). ' '; 
}
echo $t; ?></h2>
    <div id="timelines" style="width: 99%;">
    <?php print $hourlyData; ?>
    <script type="text/javascript">
// <![CDATA[
  const timelineReinit = async () => {
  const domExamples = document.getElementById("timelines");
  domExamples.innerHTML = "";
  Object.keys(weatherData).forEach(async (key) => {
    const data = weatherData[key];
    const hourly = data;

    const exampleHeader = document.createElement("p");
    exampleHeader.innerText = key;

    domExamples.append(exampleHeader);
    const exampleDiv = document.createElement("div");
    domExamples.append(exampleDiv);
    timeline(exampleDiv, hourly, { timezone: "<?php echo $JSON['timezone'] ?>" });
  });
};

timelineReinit();
// ]]>		
		</script>
	 <?php /*
     for ($row=0;$row<4;$row++) {
       print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
			 for ($n=$row*8;$n<$row*8+8;$n++) {
		     $ni = $doRTL?($row+1)*8-$n-1+($row*8):$n;  
				 if(isset($VCforecasticonHR[$ni]['icon'])) {
					 print '<td>'.$VCforecasticonHR[$ni]['icon']."<!-- n=$n ni=$ni --></td>";;
				 } else {
					 print "<td>&nbsp;</td>";
				 }
			 }
		   print "</tr>\n";
       print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
			 for ($n=$row*8;$n<$row*8+8;$n++) {
		     $ni = $doRTL?($row+1)*8-$n-1+($row*8):$n;  
				 if(isset($VCforecasticonHR[$ni]['temp'])) {
					 print '<td>'.$VCforecasticonHR[$ni]['temp']."</td>";;
				 } else {
					 print "<td>&nbsp;</td>";
				 }
			 }
		   print "</tr>\n";
       print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
			 for ($n=$row*8;$n<$row*8+8;$n++) {
		     $ni = $doRTL?($row+1)*8-$n-1+($row*8):$n;  
				 if(isset($VCforecasticonHR[$ni]['UV'])) {
					 print '<td>'.$VCforecasticonHR[$ni]['UV']."</td>";;
				 } else {
					 print "<td>&nbsp;</td>";
				 }
			 }
		   print "</tr>\n";
       print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
			 for ($n=$row*8;$n<$row*8+8;$n++) {
		     $ni = $doRTL?($row+1)*8-$n-1+($row*8):$n;  
				 if(isset($VCforecasticonHR[$ni]['wind'])) {
					 print '<td>'.$VCforecasticonHR[$ni]['wind']."</td>";;
				 } else {
					 print "<td>&nbsp;</td>";
				 }
			 }
		   print "</tr>\n";
       print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
			 for ($n=$row*8;$n<$row*8+8;$n++) {
		     $ni = $doRTL?($row+1)*8-$n-1+($row*8):$n;  
				 if(isset($VCforecasticonHR[$ni]['precip'])) {
					 print '<td>'.$VCforecasticonHR[$ni]['precip']."</td>";;
				 } else {
					 print "<td>&nbsp;</td>";
				 }
			 }
		   print "</tr>\n";
			 print "<tr><td colspan=\"8\"><hr/></td></tr>\n";
		 } // end rows
*/ ?>
    <p>Timeline display based on <a href="https://github.com/guillaume/merry-timeline">Merry-Timeline</a> by Guillaume Carbonneau</p>
    </div>
</div>
</div>
<p>&nbsp;</p>
<p><?php echo $VCforecastcity.' '; print langtransstr('forecast by');?> <a href="https://visualcrossing.com/">Visualcrossing.com</a>.<br/>
<?php if($iconType <> '.jpg') {
	print "<br/>".langtransstr('Animated forecast icons courtesy of')." <a href=\"http://www.meteotreviglio.com/\">www.meteotreviglio.com</a>.";
}
if(strlen($dsSources) > 1) {
	print "<br/><small>".langtransstr('Sources for this forecast').": $dsSources</small>\n";
}

print "</p>\n";
 
?>
<?php
} // end printmode

 if (! $IncludeMode and $PrintMode ) { ?>
</body>
</html>
<?php 
}  

 
// Functions --------------------------------------------------------------------------------

// get contents from one URL and return as string 
function VC_fetchUrlWithoutHanging($url,$useFopen) {
  global $Status, $needCookie;
  
  $overall_start = time();
  if (! $useFopen) {
   // Set maximum number of seconds (can have floating-point) to wait for feed before displaying page without feed
   $numberOfSeconds=4;   

// Thanks to Curly from ricksturf.com for the cURL fetch functions

  $data = '';
  $domain = parse_url($url,PHP_URL_HOST);
  $theURL = str_replace('nocache','?'.$overall_start,$url);        // add cache-buster to URL if needed
  $Status .= "<!-- curl fetching '$theURL' -->\n";
  $ch = curl_init();                                           // initialize a cURL session
  curl_setopt($ch, CURLOPT_URL, $theURL);                         // connect to provided URL
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);                 // don't verify peer certificate
  curl_setopt($ch, CURLOPT_USERAGENT, 
    'Mozilla/5.0 (VC-forecast.php - saratoga-weather.org)');
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $numberOfSeconds);  //  connection timeout
  curl_setopt($ch, CURLOPT_TIMEOUT, $numberOfSeconds);         //  data timeout
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);              // return the data transfer
  curl_setopt($ch, CURLOPT_NOBODY, false);                     // set nobody
  curl_setopt($ch, CURLOPT_HEADER, true);                      // include header information
  if (isset($needCookie[$domain])) {
    curl_setopt($ch, $needCookie[$domain]);                    // set the cookie for this request
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);             // and ignore prior cookies
    $Status .=  "<!-- cookie used '" . $needCookie[$domain] . "' for GET to $domain -->\n";
  }

  $data = curl_exec($ch);                                      // execute session

  if(curl_error($ch) <> '') {                                  // IF there is an error
   $Status .= "<!-- Error: ". curl_error($ch) ." -->\n";        //  display error notice
  }
  $cinfo = curl_getinfo($ch);                                  // get info on curl exec.
/*
curl info sample
Array
(
[url] => http://saratoga-weather.net/clientraw.txt
[content_type] => text/plain
[http_code] => 200
[header_size] => 266
[request_size] => 141
[filetime] => -1
[ssl_verify_result] => 0
[redirect_count] => 0
  [total_time] => 0.125
  [namelookup_time] => 0.016
  [connect_time] => 0.063
[pretransfer_time] => 0.063
[size_upload] => 0
[size_download] => 758
[speed_download] => 6064
[speed_upload] => 0
[download_content_length] => 758
[upload_content_length] => -1
  [starttransfer_time] => 0.125
[redirect_time] => 0
[redirect_url] =>
[primary_ip] => 74.208.149.102
[certinfo] => Array
(
)

[primary_port] => 80
[local_ip] => 192.168.1.104
[local_port] => 54156
)
*/
  $Status .= "<!-- HTTP stats: " .
    " RC=".$cinfo['http_code'] .
    " dest=".$cinfo['primary_ip'] ;
	if(isset($cinfo['primary_port'])) { 
	  $Status .= " port=".$cinfo['primary_port'] ;
	}
	if(isset($cinfo['local_ip'])) {
	  $Status .= " (from sce=" . $cinfo['local_ip'] . ")";
	}
	$Status .= 
	"\n      Times:" .
    " dns=".sprintf("%01.3f",round($cinfo['namelookup_time'],3)).
    " conn=".sprintf("%01.3f",round($cinfo['connect_time'],3)).
    " pxfer=".sprintf("%01.3f",round($cinfo['pretransfer_time'],3));
	if($cinfo['total_time'] - $cinfo['pretransfer_time'] > 0.0000) {
	  $Status .=
	  " get=". sprintf("%01.3f",round($cinfo['total_time'] - $cinfo['pretransfer_time'],3));
	}
    $Status .= " total=".sprintf("%01.3f",round($cinfo['total_time'],3)) .
    " secs -->\n";

  //$Status .= "<!-- curl info\n".print_r($cinfo,true)." -->\n";
  curl_close($ch);                                              // close the cURL session
  //$Status .= "<!-- raw data\n".$data."\n -->\n"; 
  $i = strpos($data,"\r\n\r\n");
  $headers = substr($data,0,$i);
  $content = substr($data,$i+4);
  if($cinfo['http_code'] <> 200) {
    $Status .= "<!-- headers:\n".$headers."\n -->\n"; 
  }
  return $data;                                                 // return headers+contents

 } else {
//   print "<!-- using file_get_contents function -->\n";
   $STRopts = array(
	  'http'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (VC-forecast.php - saratoga-weather.org)\r\n" .
				"Accept: text/plain,text/html\r\n"
	  ),
	  'https'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (VC-forecast.php - saratoga-weather.org)\r\n" .
				"Accept: text/plain,text/html\r\n"
	  )
	);
	
   $STRcontext = stream_context_create($STRopts);

   $T_start = VC_fetch_microtime();
   $xml = file_get_contents($url,false,$STRcontext);
   $T_close = VC_fetch_microtime();
   $headerarray = get_headers($url,0);
   $theaders = join("\r\n",$headerarray);
   $xml = $theaders . "\r\n\r\n" . $xml;

   $ms_total = sprintf("%01.3f",round($T_close - $T_start,3)); 
   $Status .= "<!-- file_get_contents() stats: total=$ms_total secs -->\n";
   $Status .= "<-- get_headers returns\n".$theaders."\n -->\n";
//   print " file() stats: total=$ms_total secs.\n";
   $overall_end = time();
   $overall_elapsed =   $overall_end - $overall_start;
   $Status .= "<!-- fetch function elapsed= $overall_elapsed secs. -->\n"; 
//   print "fetch function elapsed= $overall_elapsed secs.\n"; 
   return($xml);
 }

}    // end VC_fetch_URL

// ------------------------------------------------------------------

function VC_fetch_microtime()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}


// -------------------------------------------------------------------------------------------
   
 function VC_img_replace ( $VCimage, $VCcondtext,$VCpop,$VCcloudcover,$VCpreciptype) {
//
// optionally replace the WeatherUnderground icon with an NWS icon instead.
// 
 global $NWSiconlist,$iconDir,$iconType,$Status;
 
 $curicon = isset($NWSiconlist[$VCimage])?$NWSiconlist[$VCimage]:''; // translated icon (if any)
 	$tCCicon = VC_octets($VCcloudcover);

 if (!$curicon) { // no change.. use NA icon
   return("<img src=\"{$iconDir}na.jpg\" width=\"55\" height=\"55\" 
  alt=\"$VCcondtext\" title=\"$VCcondtext\"/>"); 
 }
 // override icon with cloud coverage octets for Images of partly-cloudy-* and clear-*
 if(preg_match('/^(partly|clear)/i',$VCimage)) {
	 $curicon = $tCCicon.'.jpg';
	 if(strpos($VCimage,'-night') !==false) {
		 $curicon = 'n'.$curicon;
	 }
	 $Status .= "<!-- using curicon=$curicon instead based on cloud coverage -->\n";
 }
 if(preg_match('/^wind/i',$VCimage) and $iconType !== '.gif') {
	 // note: Meteotriviglio icons do not have the wind_{sky}.gif icons, only wind.gif
	 $curicon = 'wind_'.$tCCicon.'.jpg';
	 if(strpos($VCimage,'-night') !==false) {
		 $curicon = 'n'.$curicon;
	 }
	 $Status .= "<!-- using curicon=$curicon instead based on cloud coverage -->\n";
 }
  if(isset($VCpreciptype) and is_array($VCpreciptype)) {
		$pTypes = array('junk','ice','freezingrain','snow','rain');
		$pIcons = array('n/a','mix','fzra','sn','ra');
		foreach($pTypes as $k => $type) {
			if(in_array($type,$VCpreciptype) !== false) {
				$curicon = $pIcons[$k];
				$Status .= "<! using $type icon '$pIcons[$k].jpg' based on preciptype ->\n";
				break;
			}
		}
		if(strpos($VCimage,'-night') !== false) {
			$curicon = 'n'.$curicon;
		}
		$curicon .= '.jpg';
	}
 
  if($iconType <> '.jpg') {
	  $curicon = preg_replace('|\.jpg|',$iconType,$curicon);
  }
  $Status .= "<!-- replace icon '$VCimage' with ";
  if ($VCpop > 0) {
	$testicon = preg_replace('|'.$iconType.'|',$VCpop.$iconType,$curicon);
		if (file_exists("$iconDir$testicon")) {
			$newicon = $testicon;
		} else {
			$newicon = $curicon;
		}
  } else {
		$newicon = $curicon;
  }
  $Status .= "'$newicon' pop=$VCpop -->\n";

  return("<img src=\"$iconDir$newicon\" width=\"55\" height=\"55\" 
  alt=\"$VCcondtext\" title=\"$VCcondtext\"/>"); 
 
 
 }

// -------------------------------------------------------------------------------------------
 
function VC_prepareJSON($input) {
	global $Status;
   
   //This will convert ASCII/ISO-8859-1 to UTF-8.
   //Be careful with the third parameter (encoding detect list), because
   //if set wrong, some input encodings will get garbled (including UTF-8!)

   list($isUTF8,$offset,$msg) = VC_check_utf8($input);
   
   if(!$isUTF8) {
	   $Status .= "<!-- VC_prepareJSON: Oops, non UTF-8 char detected at $offset. $msg. Doing utf8_encode() -->\n";
	   $str = utf8_encode($input);
       list($isUTF8,$offset,$msg) = VC_check_utf8($str);
	   $Status .= "<!-- VC_prepareJSON: after utf8_encode, i=$offset. $msg. -->\n";   
   } else {
	   $Status .= "<!-- VC_prepareJSON: $msg. -->\n";
	   $str = $input;
   }
  
   //Remove UTF-8 BOM if present, json_decode() does not like it.
   if(substr($str, 0, 3) == pack("CCC", 0xEF, 0xBB, 0xBF)) $str = substr($str, 3);
   
   return $str;
}

// -------------------------------------------------------------------------------------------

function VC_check_utf8($str) {
// check all the characters for UTF-8 compliance so json_decode() won't choke
// Sometimes, an ISO international character slips in the VC text string.	  
     $len = strlen($str); 
     for($i = 0; $i < $len; $i++){ 
         $c = ord($str[$i]); 
         if ($c > 128) { 
             if (($c > 247)) return array(false,$i,"c>247 c='$c'"); 
             elseif ($c > 239) $bytes = 4; 
             elseif ($c > 223) $bytes = 3; 
             elseif ($c > 191) $bytes = 2; 
             else return false; 
             if (($i + $bytes) > $len) return array(false,$i,"i+bytes>len bytes=$bytes,len=$len"); 
             while ($bytes > 1) { 
                 $i++; 
                 $b = ord($str[$i]); 
                 if ($b < 128 || $b > 191) return array(false,$i,"128<b or b>191 b=$b"); 
                 $bytes--; 
             } 
         } 
     } 
     return array(true,$i,"Success. Valid UTF-8"); 
 } // end of check_utf8

// -------------------------------------------------------------------------------------------
 
function VC_decode_JSON_error() {
	
  $Status = '';
  $Status .= "<!-- json_decode returns ";
  switch (json_last_error()) {
	case JSON_ERROR_NONE:
		$Status .= ' - No errors';
	break;
	case JSON_ERROR_DEPTH:
		$Status .= ' - Maximum stack depth exceeded';
	break;
	case JSON_ERROR_STATE_MISMATCH:
		$Status .= ' - Underflow or the modes mismatch';
	break;
	case JSON_ERROR_CTRL_CHAR:
		$Status .= ' - Unexpected control character found';
	break;
	case JSON_ERROR_SYNTAX:
		$Status .= ' - Syntax error, malformed JSON';
	break;
	case JSON_ERROR_UTF8:
		$Status .= ' - Malformed UTF-8 characters, possibly incorrectly encoded';
	break;
	default:
		$Status .= ' - Unknown error, json_last_error() returns \''.json_last_error(). "'";
	break;
   } 
   $Status .= " -->\n";
   return($Status);
}

// -------------------------------------------------------------------------------------------

function VC_fixup_text($text) {
	global $Status;
	// attempt to convert Imperial forecast temperatures to Metric in the text forecast
	
	if(preg_match_all('!([-|\d]+)([Â Âº]*F)!s',$text,$m)) {
		//$newtext = str_replace('ºF','F',$text);
		$newtext = $text;
		foreach ($m[1] as $i => $tF) {
			$tI = $m[2][$i];
			$tC = (float)(($tF - 32) / 1.8 );
			$tC = round($tC,0);
//			$newtext = str_replace("{$tF}F","{$tC}C({$tF}F)",$newtext);
			$newtext = str_replace("{$tF}{$tI}","{$tC}C",$newtext);
			$Status .= "<!-- replaced {$tF}F with {$tC}C in text forecast. -->\n";
		}
		return($newtext);
	} else {
		return($text);  // no changes
	}
	
	
}

function VC_loadLangDefaults () {
	global $VClanguages, $VClangCharsets;
/*
    en - [DEFAULT] English
    ar - Arabic
    az - Azerbaijani
    be - Belarusian
    bg - Bulgarian
    bs - Bosnian
    ca - Catalan
    cz - Czech
    da - Danish
    de - German
    fi - Finnish
    fr - French
    el - Greek
    et - Estonian
    hr - Croation
    hu - Hungarian
    id - Indonesian
    it - Italian
    is - Icelandic
    kw - Cornish
    lt - Lithuanian
    nb - Norwegian Bokmål
    nl - Dutch
    pl - Polish
    pt - Portuguese
    ro - Romanian
    ru - Russian
    sk - Slovak
    sl - Slovenian
    sr - Serbian
    sv - Swedish
    tr - Turkish
    uk - Ukrainian

*/
 
 $VClanguages = array(  // our template language codes v.s. lang:LL codes for JSON
	'af' => 'en',
	'bg' => 'bg',
	'cs' => 'cs',
	'ct' => 'ca',
	'dk' => 'da',
	'nl' => 'nl',
	'en' => 'en',
	'fi' => 'fi',
	'fr' => 'fr',
	'de' => 'de',
	'el' => 'el',
	'ga' => 'en',
	'it' => 'it',
	'he' => 'he',
	'hu' => 'hu',
	'no' => 'nb',
	'pl' => 'pl',
	'pt' => 'pt',
	'ro' => 'ro',
	'es' => 'es',
	'se' => 'sv',
	'si' => 'sl',
	'sk' => 'sk',
	'sr' => 'sr',
  );

  $VClangCharsets = array(
	'bg' => 'ISO-8859-5',
	'cs' => 'ISO-8859-2',
	'el' => 'ISO-8859-7',
	'he' => 'UTF-8', 
	'hu' => 'ISO-8859-2',
	'ro' => 'ISO-8859-2',
	'pl' => 'ISO-8859-2',
	'si' => 'ISO-8859-2',
	'sk' => 'Windows-1250',
	'sr' => 'Windows-1250',
	'ru' => 'ISO-8859-5',
  );

} // end loadLangDefaults

function VC_loadTranslate ($lang) {
	global $Status;
	
/*
Note: We packed up the translation array as it is a mix of various character set
types and editing the raw text can easily change the character presentation.
The TRANTABLE was created by using

	$transSerial = serialize($transArray);
	$b64 = base64_encode($transSerial);
	print "\n";
	$tArr = str_split($b64,72);
	print "define('TRANTABLE',\n'";
	$tStr = '';
	foreach($tArr as $rec) {
		$tStr .= $rec."\n";
	}
	$tStr = trim($tStr);
	print $tStr;
	print "'); // end of TRANTABLE encoded\n";
	
and that result included here.

It will reconstitute with unserialize(base64_decode(TRANTABLE)) to look like:
 ... 
 
 'dk' => array ( 
    'charset' => 'ISO-8859-1',
    'Sunday' => 'Søndag',
    'Monday' => 'Mandag',
    'Tuesday' => 'Tirsdag',
    'Wednesday' => 'Onsdag',
    'Thursday' => 'Torsdag',
    'Friday' => 'Fredag',
    'Saturday' => 'Lørdag',
    'Sunday night' => 'Søndag nat',
    'Monday night' => 'Mandag nat',
    'Tuesday night' => 'Tirsdag nat',
    'Wednesday night' => 'Onsdag nat',
    'Thursday night' => 'Torsdag nat',
    'Friday night' => 'Fredag nat',
    'Saturday night' => 'Lørdag nat',
    'Today' => 'I dag',
    'Tonight' => 'I nat',
    'This afternoon' => 'I eftermiddag',
    'Rest of tonight' => 'Resten af natten',
  ), // end dk 
...

and the array for the chosen language will be returned, or the English version if the 
language is not in the array.

*/
if(!file_exists("VC-forecast-lang.php")) {
	print "<p>Warning: VC-forecast-lang.php translation file was not found.  It is required";
	print " to be in the same directory as VC-forecast.php.</p>\n";
	exit;
	}
include_once("VC-forecast-lang.php");

$default = array(
    'charset' => 'ISO-8859-1',
    'Sunday' => 'Sunday',
    'Monday' => 'Monday',
    'Tuesday' => 'Tuesday',
    'Wednesday' => 'Wednesday',
    'Thursday' => 'Thursday',
    'Friday' => 'Friday',
    'Saturday' => 'Saturday',
    'Sunday night' => 'Sunday night',
    'Monday night' => 'Monday night',
    'Tuesday night' => 'Tuesday night',
    'Wednesday night' => 'Wednesday night',
    'Thursday night' => 'Thursday night',
    'Friday night' => 'Friday night',
    'Saturday night' => 'Saturday night',
    'Today' => 'Today',
    'Tonight' => 'Tonight',
    'This afternoon' => 'This afternoon',
    'Rest of tonight' => 'Rest of tonight',
		'High:' => 'High:',
    'Low:' =>  'Low:',
		'Updated:' => 'Updated:',
		'Visualcrossing Forecast for:' => 'Visualcrossing Forecast for:',
    'NESW' =>  'NESW', // cardinal wind directions
		'Wind' => 'Wind',
    'UV index' => 'UV Index',
    'Chance of precipitation' =>  'Chance of precipitation',
		 'mph' => 'mph',
     'kph' => 'km/h',
     'mps' => 'm/s',
		 'Temperature' => 'Temperature',
		 'Barometer' => 'Barometer',
		 'Dew Point' => 'Dew Point',
		 'Humidity' => 'Humidity',
		 'Visibility' => 'Visibility',
		 'Wind chill' => 'Wind chill',
		 'Heat index' => 'Heat index',
		 'Humidex' => 'Humidex',
		 'Sunrise' => 'Sunrise',
		 'Sunset' => 'Sunset',
		 'Currently' => 'Currently',
		 'rain' => 'rain',
		 'snow' => 'snow',
		 'sleet' => 'sleet',
		 'Weather conditions at 999 from forecast point.' => 
		   'Weather conditions at 999 from forecast point.',
		 'Daily Forecast' => 'Daily Forecast',
		 'Hourly Forecast' => 'Hourly Forecast',
		 'Meteogram' => 'Meteogram',



);

 $t = unserialize(base64_decode(TRANTABLE));
 
 if(isset($t[$lang])) {
	 $Status .= "<!-- loaded translations for lang='$lang' for period names -->\n";
	 return($t[$lang]);
 } else {
	 $Status .= "<!-- loading English period names -->\n";
	 return($default);
 }
 
}
// ------------------------------------------------------------------

//  convert degrees into wind direction abbreviation   
function VC_WindDir ($degrees) {
   // figure out a text value for compass direction
// Given the wind direction, return the text label
// for that value.  16 point compass
   $winddir = $degrees;
   if ($winddir == "n/a") { return($winddir); }

  if (!isset($winddir)) {
    return "---";
  }
  if (!is_numeric($winddir)) {
	return($winddir);
  }
  $windlabel = array ("N","NNE", "NE", "ENE", "E", "ESE", "SE", "SSE", "S",
	 "SSW","SW", "WSW", "W", "WNW", "NW", "NNW");
  $dir = $windlabel[ (integer)fmod((($winddir + 11) / 22.5),16) ];
  return($dir);

} // end function VC_WindDir
// ------------------------------------------------------------------

function VC_WindDirTrans($inwdir) {
	global $tranTab, $Status;
	$wdirs = $tranTab['NESW'];  // default directions
	$tstr = $inwdir;
	$Status .= "<!-- VC_WindDirTrans in=$inwdir using ";
	if(strlen($wdirs) == 4) {
		$tstr = strtr($inwdir,'NESW',$wdirs); // do translation
		$Status .= " strtr for ";
	} elseif (preg_match('|,|',$wdirs)) { //multichar translation
		$wdirsmc = explode(',',$wdirs);
		$wdirs = array('N','E','S','W');
		$wdirlook = array();
		foreach ($wdirs as $n => $d) {
			$wdirlook[$d] = $wdirsmc[$n];
		} 
		$tstr = ''; // get ready to pass once through the string
		for ($n=0;$n<strlen($inwdir);$n++) {
			$c = substr($inwdir,$n,1);
			if(isset($wdirlook[$c])) {
				$tstr .= $wdirlook[$c]; // use translation
			} else {
				$tstr .= $c; // use regular
			}
		}
		$Status .= " array substitute for ";
	}
	$Status .= "NESW=>'".$tranTab['NESW']."' output='$tstr' -->\n";

  return($tstr);
}

function VC_round($item,$dp) {
	$t = round($item,$dp);
	if ($t == '-0') {
		$t = 0;
	}
	return ($t);
}

function VC_sources ($sArray) {
	
	$lookupSources = array(
	'cmc'        => 'The USA NCEP&rsquo;s Canadian Meteorological Center ensemble model|http://nomads.ncep.noaa.gov/txt_descriptions/CMCENS_doc.shtml',
	'darksky'    => 'Dark Sky&rsquo;s own hyperlocal precipitation forecasting system, backed by radar data from the USA NOAA&rsquo;s NEXRAD system, available in the USA, and the UK Met Office&rsquo;s NIMROD system, available in the UK and Ireland.|https://darksky.net/',
	'ecpa'       => 'Environment and Climate Change Canada&rsquo;s Public Alert system|https://weather.gc.ca/warnings/index_e.html',
	'gfs'        => 'The USA NOAA&rsquo;s Global Forecast System|http://en.wikipedia.org/wiki/Global_Forecast_System',
	'hrrr'       => 'The USA NOAA&rsquo;s High-Resolution Rapid Refresh Model|https://rapidrefresh.noaa.gov/hrrr/',
	'icon'       => 'The German Meteorological Office&rsquo;s icosahedral nonhydrostatic|https://www.dwd.de/EN/research/weatherforecasting/num_modelling/01_num_weather_prediction_modells/icon_description.html',
	'isd'        => 'The USA NOAA&rsquo;s Integrated Surface Database|https://www.ncdc.noaa.gov/isd',
	'madis'      => 'The USA NOAA/ESRL&rsquo;s Meteorological Assimilation Data Ingest System|https://madis.noaa.gov/',
	'meteoalarm' => 'EUMETNET&rsquo;s Meteoalarm weather alerting system|https://meteoalarm.eu/',
	'nam'        => 'The USA NOAA&rsquo;s North American Mesoscale Model|http://en.wikipedia.org/wiki/North_American_Mesoscale_Model',
	'nwspa'      => 'The USA NOAA&rsquo;s Public Alert system|https://alerts.weather.gov/',
	'sref'       => 'The USA NOAA/NCEP&rsquo;s Short-Range Ensemble Forecast|https://www.emc.ncep.noaa.gov/mmb/SREF/SREF.html',
);

	
	$outStr = '';
	foreach ($sArray as $source) {
		if(isset($lookupSources[$source])) {
			list($title,$url) = explode('|',$lookupSources[$source]);
			if(strlen($outStr) > 1) {$outStr .= ', ';}
			$outStr .= "<a href=\"$url\" title=\"$title\">".strtoupper($source)."</a>\n";
		}
	}
	return ($outStr);
}

function VC_octets ($coverage) {
	global $Status;
	
	$octets = round($coverage*100 / 12.5,1);
	$Status .= "<!-- VC_octets in=$coverage octets=$octets ";
	if($octets < 1.0) {
		$Status .= " clouds=skc -->\n";
		return('skc');
	} 
	elseif ($octets < 3.0) {
		$Status .= " clouds=few -->\n";
		return('few');
	}
	elseif ($octets < 5.0) {
		$Status .= " clouds=sct -->\n";
		return('sct');
	}
	elseif ($octets < 8.0) {
		$Status .= " clouds=bkn -->\n";
		return('bkn');
	} else {
		$Status .= " clouds=ovc -->\n";
		return('ovc');
	}
	
}

function VC_conv_baro($hPa) {
	# even 'us' imperial returns pressure in hPa so we need to convert
	global $showUnitsAs;
	
	if($showUnitsAs == 'us') {
		$t = (float)$hPa * 0.02952998751;
		return(sprintf("%01.2f",$t));
	} else {
		return( sprintf("%01.1f",$hPa) );
	}
}

function VC_gen_hourforecast($FCpart) {
	global $doDebug,$Status,$showTempsAs,$tranTab,$windUnit,$Units,$showUnitsAs;
	/* $FCpart =
	{
				"time": 1548018000,
				"summary": "Mostly Cloudy",
				"icon": "partly-cloudy-day",
				"precipIntensity": 0.1422,
				"precipProbability": 0.29,
				"precipType": "rain",
				"temperature": 14.91,
				"apparentTemperature": 14.91,
				"dewPoint": 11.49,
				"humidity": 0.8,
				"pressure": 1017.89,
				"windSpeed": 10.8,
				"windGust": 24.54,
				"windBearing": 226,
				"cloudCover": 0.88,
				"uvIndex": 2,
				"visibility": 14.11,
				"ozone": 289.95
			}
*/
  $VCH = array();
	
  //$newIcon = '<td>';
  if($showUnitsAs == 'us') {
	  $t = explode(' ',date('g:ia n/j l',$FCpart['time']));
	} else {
	  $t = explode(' ',date('H:i j/n l',$FCpart['time']));
	}
	
	$newIcon = '<b>'.$t[0].'<br/>'.$tranTab[$t[2]]."</b><br/>\n";
	
  $cloudcover = $FCpart['cloudCover'];
	if(isset($FCpart['precipProbability'])) {
	  $pop = round($FCpart['precipProbability'],1)*100;
	} else {
		$pop = 0;
	}
	$temp = explode('.',$FCpart['summary'].'.'); // split as sentences (sort of).
	
	$condition = trim($temp[0]); // take first one as summary.

	$icon = $FCpart['icon'];

	$newIcon .= "<br/>" .
	     VC_img_replace(
			   $icon,$condition,$pop,$cloudcover) . 
				  "<br/>" .
		 $condition;
	$VCH['icon'] = $newIcon;

	$VCH['temp'] = '<b>'.VC_round($FCpart['temperature'],0)."</b>&deg;$showTempsAs";
	$VCH['UV'] = 'UV: <b>'.$FCpart['uvIndex']."</b>";

	$tWdir = VC_WindDir(round($FCpart['windBearing'],0));
  $VCH['wind'] = $tranTab['Wind']." <b>".VC_WindDirTrans($tWdir);
  $VCH['wind'] .= " ".
	     round($FCpart['windSpeed'],0)."-&gt;".round($FCpart['windGust'],0) .
	     "</b> $windUnit\n";


	if(isset($FCpart['precipType'])) {
		$preciptype = $FCpart['precipType'];
	} else {
		$preciptype = '';
	}

	$tstr = '';
	if($pop > 0) {
		if(!empty($preciptype)) {
			$t = explode(',',$preciptype.',');
			foreach ($t as $k => $ptype) {
				if(!empty($ptype)) {$tstr .= $tranTab[$ptype].',';}
			}

			if(isset($FCpart['precipAccumulation'])) {
				$amt = $FCpart['precipAccumulation'];
				if($showUnitsAs == 'us') {
					$U   = 'in';
				} else {
					$U   = 'cm';
				}
				$accum = ' <b>' . sprintf("%01.2f",$amt)."</b>$U";
			} else {
				$accum = '';
			}
			if(strlen($tstr)>0) {
				$tstr = substr($tstr,0,strlen($tstr)-1). $accum;
			} else {
				$tstr = '&nbsp;';
			}
		}
	}
  $VCH['precip'] = "$tstr";
	
	
	

	//$newIcon .= "</td>\n";
	return($VCH);
}

function setup_tabber() {
?>	
<script type="text/javascript">
// <![CDATA[
/*==================================================
  $Id: tabber.js,v 1.9 2006/04/27 20:51:51 pat Exp $
  tabber.js by Patrick Fitzgerald pat@barelyfitz.com

  Documentation can be found at the following URL:
  http://www.barelyfitz.com/projects/tabber/

  License (http://www.opensource.org/licenses/mit-license.php)

  Copyright (c) 2006 Patrick Fitzgerald

  Permission is hereby granted, free of charge, to any person
  obtaining a copy of this software and associated documentation files
  (the "Software"), to deal in the Software without restriction,
  including without limitation the rights to use, copy, modify, merge,
  publish, distribute, sublicense, and/or sell copies of the Software,
  and to permit persons to whom the Software is furnished to do so,
  subject to the following conditions:

  The above copyright notice and this permission notice shall be
  included in all copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
  EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
  MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
  NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS
  BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
  ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
  CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
  SOFTWARE.
  ==================================================*/

function tabberObj(argsObj)
{
  var arg; /* name of an argument to override */

  /* Element for the main tabber div. If you supply this in argsObj,
     then the init() method will be called.
  */
  this.div = null;

  /* Class of the main tabber div */
  this.classMain = "tabber";

  /* Rename classMain to classMainLive after tabifying
     (so a different style can be applied)
  */
  this.classMainLive = "tabberlive";

  /* Class of each DIV that contains a tab */
  this.classTab = "tabbertab";

  /* Class to indicate which tab should be active on startup */
  this.classTabDefault = "tabbertabdefault";

  /* Class for the navigation UL */
  this.classNav = "tabbernav";

  /* When a tab is to be hidden, instead of setting display='none', we
     set the class of the div to classTabHide. In your screen
     stylesheet you should set classTabHide to display:none.  In your
     print stylesheet you should set display:block to ensure that all
     the information is printed.
  */
  this.classTabHide = "tabbertabhide";

  /* Class to set the navigation LI when the tab is active, so you can
     use a different style on the active tab.
  */
  this.classNavActive = "tabberactive";

  /* Elements that might contain the title for the tab, only used if a
     title is not specified in the TITLE attribute of DIV classTab.
  */
  this.titleElements = ['h2','h3','h4','h5','h6'];

  /* Should we strip out the HTML from the innerHTML of the title elements?
     This should usually be true.
  */
  this.titleElementsStripHTML = true;

  /* If the user specified the tab names using a TITLE attribute on
     the DIV, then the browser will display a tooltip whenever the
     mouse is over the DIV. To prevent this tooltip, we can remove the
     TITLE attribute after getting the tab name.
  */
  this.removeTitle = true;

  /* If you want to add an id to each link set this to true */
  this.addLinkId = false;

  /* If addIds==true, then you can set a format for the ids.
     <tabberid> will be replaced with the id of the main tabber div.
     <tabnumberzero> will be replaced with the tab number
       (tab numbers starting at zero)
     <tabnumberone> will be replaced with the tab number
       (tab numbers starting at one)
     <tabtitle> will be replaced by the tab title
       (with all non-alphanumeric characters removed)
   */
  this.linkIdFormat = '<tabberid>nav<tabnumberone>';

  /* You can override the defaults listed above by passing in an object:
     var mytab = new tabber({property:value,property:value});
  */
  for (arg in argsObj) { this[arg] = argsObj[arg]; }

  /* Create regular expressions for the class names; Note: if you
     change the class names after a new object is created you must
     also change these regular expressions.
  */
  this.REclassMain = new RegExp('\\b' + this.classMain + '\\b', 'gi');
  this.REclassMainLive = new RegExp('\\b' + this.classMainLive + '\\b', 'gi');
  this.REclassTab = new RegExp('\\b' + this.classTab + '\\b', 'gi');
  this.REclassTabDefault = new RegExp('\\b' + this.classTabDefault + '\\b', 'gi');
  this.REclassTabHide = new RegExp('\\b' + this.classTabHide + '\\b', 'gi');

  /* Array of objects holding info about each tab */
  this.tabs = new Array();

  /* If the main tabber div was specified, call init() now */
  if (this.div) {

    this.init(this.div);

    /* We don't need the main div anymore, and to prevent a memory leak
       in IE, we must remove the circular reference between the div
       and the tabber object. */
    this.div = null;
  }
}


/*--------------------------------------------------
  Methods for tabberObj
  --------------------------------------------------*/


tabberObj.prototype.init = function(e)
{
  /* Set up the tabber interface.

     e = element (the main containing div)

     Example:
     init(document.getElementById('mytabberdiv'))
   */

  var
  childNodes, /* child nodes of the tabber div */
  i, i2, /* loop indices */
  t, /* object to store info about a single tab */
  defaultTab=0, /* which tab to select by default */
  DOM_ul, /* tabbernav list */
  DOM_li, /* tabbernav list item */
  DOM_a, /* tabbernav link */
  aId, /* A unique id for DOM_a */
  headingElement; /* searching for text to use in the tab */

  /* Verify that the browser supports DOM scripting */
  if (!document.getElementsByTagName) { return false; }

  /* If the main DIV has an ID then save it. */
  if (e.id) {
    this.id = e.id;
  }

  /* Clear the tabs array (but it should normally be empty) */
  this.tabs.length = 0;

  /* Loop through an array of all the child nodes within our tabber element. */
  childNodes = e.childNodes;
  for(i=0; i < childNodes.length; i++) {

    /* Find the nodes where class="tabbertab" */
    if(childNodes[i].className &&
       childNodes[i].className.match(this.REclassTab)) {
      
      /* Create a new object to save info about this tab */
      t = new Object();
      
      /* Save a pointer to the div for this tab */
      t.div = childNodes[i];
      
      /* Add the new object to the array of tabs */
      this.tabs[this.tabs.length] = t;

      /* If the class name contains classTabDefault,
	 then select this tab by default.
      */
      if (childNodes[i].className.match(this.REclassTabDefault)) {
	defaultTab = this.tabs.length-1;
      }
    }
  }

  /* Create a new UL list to hold the tab headings */
  DOM_ul = document.createElement("ul");
  DOM_ul.className = this.classNav;
  
  /* Loop through each tab we found */
  for (i=0; i < this.tabs.length; i++) {

    t = this.tabs[i];

    /* Get the label to use for this tab:
       From the title attribute on the DIV,
       Or from one of the this.titleElements[] elements,
       Or use an automatically generated number.
     */
    t.headingText = t.div.title;

    /* Remove the title attribute to prevent a tooltip from appearing */
    if (this.removeTitle) { t.div.title = ''; }

    if (!t.headingText) {

      /* Title was not defined in the title of the DIV,
	 So try to get the title from an element within the DIV.
	 Go through the list of elements in this.titleElements
	 (typically heading elements ['h2','h3','h4'])
      */
      for (i2=0; i2<this.titleElements.length; i2++) {
	headingElement = t.div.getElementsByTagName(this.titleElements[i2])[0];
	if (headingElement) {
	  t.headingText = headingElement.innerHTML;
	  if (this.titleElementsStripHTML) {
	    t.headingText.replace(/<br>/gi," ");
	    t.headingText = t.headingText.replace(/<[^>]+>/g,"");
	  }
	  break;
	}
      }
    }

    if (!t.headingText) {
      /* Title was not found (or is blank) so automatically generate a
         number for the tab.
      */
      t.headingText = i + 1;
    }

    /* Create a list element for the tab */
    DOM_li = document.createElement("li");

    /* Save a reference to this list item so we can later change it to
       the "active" class */
    t.li = DOM_li;

    /* Create a link to activate the tab */
    DOM_a = document.createElement("a");
    DOM_a.appendChild(document.createTextNode(t.headingText));
    DOM_a.href = "javascript:void(null);";
    DOM_a.title = t.headingText;
    DOM_a.onclick = this.navClick;

    /* Add some properties to the link so we can identify which tab
       was clicked. Later the navClick method will need this.
    */
    DOM_a.tabber = this;
    DOM_a.tabberIndex = i;

    /* Do we need to add an id to DOM_a? */
    if (this.addLinkId && this.linkIdFormat) {

      /* Determine the id name */
      aId = this.linkIdFormat;
      aId = aId.replace(/<tabberid>/gi, this.id);
      aId = aId.replace(/<tabnumberzero>/gi, i);
      aId = aId.replace(/<tabnumberone>/gi, i+1);
      aId = aId.replace(/<tabtitle>/gi, t.headingText.replace(/[^a-zA-Z0-9\-]/gi, ''));

      DOM_a.id = aId;
    }

    /* Add the link to the list element */
    DOM_li.appendChild(DOM_a);

    /* Add the list element to the list */
    DOM_ul.appendChild(DOM_li);
  }

  /* Add the UL list to the beginning of the tabber div */
  e.insertBefore(DOM_ul, e.firstChild);

  /* Make the tabber div "live" so different CSS can be applied */
  e.className = e.className.replace(this.REclassMain, this.classMainLive);

  /* Activate the default tab, and do not call the onclick handler */
  this.tabShow(defaultTab);

  /* If the user specified an onLoad function, call it now. */
  if (typeof this.onLoad == 'function') {
    this.onLoad({tabber:this});
  }

  return this;
};


tabberObj.prototype.navClick = function(event)
{
  /* This method should only be called by the onClick event of an <A>
     element, in which case we will determine which tab was clicked by
     examining a property that we previously attached to the <A>
     element.

     Since this was triggered from an onClick event, the variable
     "this" refers to the <A> element that triggered the onClick
     event (and not to the tabberObj).

     When tabberObj was initialized, we added some extra properties
     to the <A> element, for the purpose of retrieving them now. Get
     the tabberObj object, plus the tab number that was clicked.
  */

  var
  rVal, /* Return value from the user onclick function */
  a, /* element that triggered the onclick event */
  self, /* the tabber object */
  tabberIndex, /* index of the tab that triggered the event */
  onClickArgs; /* args to send the onclick function */

  a = this;
  if (!a.tabber) { return false; }

  self = a.tabber;
  tabberIndex = a.tabberIndex;

  /* Remove focus from the link because it looks ugly.
     I don't know if this is a good idea...
  */
  a.blur();

  /* If the user specified an onClick function, call it now.
     If the function returns false then do not continue.
  */
  if (typeof self.onClick == 'function') {

    onClickArgs = {'tabber':self, 'index':tabberIndex, 'event':event};

    /* IE uses a different way to access the event object */
    if (!event) { onClickArgs.event = window.event; }

    rVal = self.onClick(onClickArgs);
    if (rVal === false) { return false; }
  }

  self.tabShow(tabberIndex);

  return false;
};


tabberObj.prototype.tabHideAll = function()
{
  var i; /* counter */

  /* Hide all tabs and make all navigation links inactive */
  for (i = 0; i < this.tabs.length; i++) {
    this.tabHide(i);
  }
};


tabberObj.prototype.tabHide = function(tabberIndex)
{
  var div;

  if (!this.tabs[tabberIndex]) { return false; }

  /* Hide a single tab and make its navigation link inactive */
  div = this.tabs[tabberIndex].div;

  /* Hide the tab contents by adding classTabHide to the div */
  if (!div.className.match(this.REclassTabHide)) {
    div.className += ' ' + this.classTabHide;
  }
  this.navClearActive(tabberIndex);

  return this;
};


tabberObj.prototype.tabShow = function(tabberIndex)
{
  /* Show the tabberIndex tab and hide all the other tabs */

  var div;

  if (!this.tabs[tabberIndex]) { return false; }

  /* Hide all the tabs first */
  this.tabHideAll();

  /* Get the div that holds this tab */
  div = this.tabs[tabberIndex].div;

  /* Remove classTabHide from the div */
  div.className = div.className.replace(this.REclassTabHide, '');

  /* Mark this tab navigation link as "active" */
  this.navSetActive(tabberIndex);

  /* If the user specified an onTabDisplay function, call it now. */
  if (typeof this.onTabDisplay == 'function') {
    this.onTabDisplay({'tabber':this, 'index':tabberIndex});
  }

  return this;
};

tabberObj.prototype.navSetActive = function(tabberIndex)
{
  /* Note: this method does *not* enforce the rule
     that only one nav item can be active at a time.
  */

  /* Set classNavActive for the navigation list item */
  this.tabs[tabberIndex].li.className = this.classNavActive;

  return this;
};


tabberObj.prototype.navClearActive = function(tabberIndex)
{
  /* Note: this method does *not* enforce the rule
     that one nav should always be active.
  */

  /* Remove classNavActive from the navigation list item */
  this.tabs[tabberIndex].li.className = '';

  return this;
};


/*==================================================*/


function tabberAutomatic(tabberArgs)
{
  /* This function finds all DIV elements in the document where
     class=tabber.classMain, then converts them to use the tabber
     interface.

     tabberArgs = an object to send to "new tabber()"
  */
  var
    tempObj, /* Temporary tabber object */
    divs, /* Array of all divs on the page */
    i; /* Loop index */

  if (!tabberArgs) { tabberArgs = {}; }

  /* Create a tabber object so we can get the value of classMain */
  tempObj = new tabberObj(tabberArgs);

  /* Find all DIV elements in the document that have class=tabber */

  /* First get an array of all DIV elements and loop through them */
  divs = document.getElementsByTagName("div");
  for (i=0; i < divs.length; i++) {
    
    /* Is this DIV the correct class? */
    if (divs[i].className &&
	divs[i].className.match(tempObj.REclassMain)) {
      
      /* Now tabify the DIV */
      tabberArgs.div = divs[i];
      divs[i].tabber = new tabberObj(tabberArgs);
    }
  }
  
  return this;
}


/*==================================================*/


function tabberAutomaticOnLoad(tabberArgs)
{
  /* This function adds tabberAutomatic to the window.onload event,
     so it will run after the document has finished loading.
  */
  var oldOnLoad;

  if (!tabberArgs) { tabberArgs = {}; }

  /* Taken from: http://simon.incutio.com/archive/2004/05/26/addLoadEvent */

  oldOnLoad = window.onload;
  if (typeof window.onload != 'function') {
    window.onload = function() {
      tabberAutomatic(tabberArgs);
    };
  } else {
    window.onload = function() {
      oldOnLoad();
      tabberAutomatic(tabberArgs);
    };
  }
}

/*==================================================*/

/* Run tabberAutomaticOnload() unless the "manualStartup" option was specified */

if (typeof tabberOptions == 'undefined') {

    tabberAutomaticOnLoad();

} else {

  if (!tabberOptions['manualStartup']) {
    tabberAutomaticOnLoad(tabberOptions);
  }

}

// merry-timeline 
const last = (list) => {
  return list.length > 0 ? list[list.length - 1] : null;
};

const formatHour = (time, timezone) => {
  const options = { timeStyle: "short" };
  if (timezone) {
    options.timeZone = timezone;
  }
	if(showAMPMtime) {
    const formattedHour = new Date(time * 1000).toLocaleString("en-US", options);
    const [hmm, ampm] = formattedHour.split(" ");
    const [h /*, _mm*/] = hmm.split(":");
    return h + ampm.toLowerCase();
	} else {
    const formattedHour = new Date(time * 1000).toLocaleString("en-GB", options);
    const [h, m, s] = formattedHour.split(":");
    return h;
	}
};

const isDarkText = (bgColor) => {
  var color = bgColor.charAt(0) === "#" ? bgColor.substring(1, 7) : bgColor;
  var r = parseInt(color.substring(0, 2), 16);
  var g = parseInt(color.substring(2, 4), 16);
  var b = parseInt(color.substring(4, 6), 16);
  return r * 0.299 + g * 0.587 + b * 0.114 > 186;
};

const getTimelineRange = (merryData) => {
  const { min: start, max: end } = merryData.reduce(
    ({ max, min }, h) => {
      return { max: Math.max(max, h.time), min: Math.min(min, h.time) };
    },
    { min: Infinity, max: 0 }
  );

  return { start, end: end + 3600 };
};

const init = (domElement, merryData, options) => {
  const timelineWidth = options.width || domElement.offsetWidth;

  const timelineDom = document.createElement("div");
  timelineDom.className = "timeline";
  timelineDom.style.width = timelineWidth + "px";
  timelineDom.style.height = "95px";
  timelineDom.style.position = "relative";

  const stripesDom = document.createElement("div");
  stripesDom.className = "stripes";
  stripesDom.style.borderRadius = "5px";
  stripesDom.style.height = "44px";
  stripesDom.style.width = "100%";
  stripesDom.style.position = "absolute";
  stripesDom.style.overflow = "hidden";

  let currentCategory = null;
  const stripes = [];

  merryData.forEach((h, i) => {
    const category = [h.color, h.text].join("__");
    if (currentCategory === null || currentCategory !== category) {
      currentCategory = category;
      stripes.push([]);
    }
    const lastStrip = last(stripes);
    h.category = category;
    lastStrip.push(h);
  });

  let prevWidth = 0;
  stripes.forEach((stripe) => {
    const text = options?.text ? options.text(stripe) : stripe[0].text;
    const color = options?.color ? options.color(stripe) : stripe[0].color;
    const width = (stripe.length / merryData.length) * timelineWidth;
    const stripeDom = document.createElement("span");

    stripeDom.className = "stripe";
    stripeDom.style.height = "100%";
    stripeDom.style.position = "absolute";
    stripeDom.style.lineHeight = "40px";
    stripeDom.style.textAlign = "center";
    stripeDom.style.fontSize = "13px";
    stripeDom.style.fontWeight = "400";

    stripeDom.style.width = width + "px";
    stripeDom.style.left = prevWidth + "px";
    stripeDom.style.backgroundColor = color;
    const textColorBlack = isDarkText(color);
    stripeDom.style.color = textColorBlack ? "#333" : "#fff";
    stripeDom.style.textShadow = textColorBlack
      ? "1px 1px 0 rgb(255 255 255 / 50%)"
      : "1px 1px 0 rgb(0 0 0 / 50%)";

    stripeDom.style.opacity = options?.opacity ? options.opacity(stripe) : 1;
    stripeDom.title = text;

    if (width > 40) {
      stripeDom.innerText = text;
    }
    stripesDom.appendChild(stripeDom);
    prevWidth += width;
  });

  const now = options.tracker || new Date().valueOf() / 1000;
  const { start, end } = getTimelineRange(merryData);
  if (now > start && now < end) {
    const currentTimeDom = document.createElement("div");
    currentTimeDom.style.background = "rgba(255,0,0,0.5)";
    currentTimeDom.style.width = "1px";
    currentTimeDom.style.height = "100%";
    currentTimeDom.style.position = "absolute";
    const ratio = (now - start) / (end - start);
    currentTimeDom.style.left = ratio * timelineWidth + "px";

    stripesDom.appendChild(currentTimeDom);
  }
  timelineDom.appendChild(stripesDom);

  const ticksDom = document.createElement("div");
  ticksDom.className = "ticks";

  ticksDom.style.position = "absolute";
  ticksDom.style.top = "44px";
  ticksDom.style.left = 0;
  ticksDom.style.width = "100%";
  ticksDom.style.height = "10px";

  const tickSpacing = timelineWidth / merryData.length;
  let modulo = 2;
  if (tickSpacing < 12) {
    modulo = 6;
  } else if (tickSpacing < 20) {
    modulo = 4;
  } else if (tickSpacing < 25) {
    modulo = 3;
  }

  for (let i = 0; i < merryData.length; i++) {
    const tickDom = document.createElement("span");

    tickDom.className = "tick " + (i % modulo ? "odd" : "even");

    tickDom.style.position = "absolute";
    tickDom.style.borderLeft = "1px solid #999";

    tickDom.style.left = i * tickSpacing + "px";
    tickDom.style.height = i % modulo ? "8px" : "5px";
    ticksDom.appendChild(tickDom);
  }
  timelineDom.appendChild(ticksDom);

  const hoursDom = document.createElement("div");
  hoursDom.className = "hours";
  hoursDom.style.position = "absolute";
  hoursDom.style.top = "54px";
  hoursDom.style.left = "0px";
  hoursDom.style.width = "100%";
  hoursDom.style.height = "5px";
  hoursDom.style.fontWeight = 500;
  hoursDom.style.fontSize = "14px";

  for (let i = 0; i < merryData.length; i += modulo) {
    const h = merryData[i];
    const hourDom = document.createElement("span");
    hourDom.className = "hour" + (i === 0 ? " first" : "");
    hourDom.style.left = i * tickSpacing + "px";

    if (i !== 0) {
      hourDom.style.position = "absolute";
      hourDom.style.display = "inline-block";
      hourDom.style.width = "40px";
      hourDom.style.marginLeft = "-20px";
      hourDom.style.height = "5px";
      hourDom.style.textAlign = "center";
    }

    hourDom.innerText = formatHour(h.time, options.timezone);
    hoursDom.appendChild(hourDom);
  }
  timelineDom.appendChild(hoursDom);
  if (merryData.filter((m) => m.annotation).length > 0) {
    const annotationsDom = document.createElement("div");
    annotationsDom.className = "annotations";
    annotationsDom.style.position = "absolute";
    annotationsDom.style.left = "0px";
    annotationsDom.style.top = "70px";
    annotationsDom.style.width = "100%";
    annotationsDom.style.fontWeight = 300;

    for (let i = 0; i < merryData.length; i += modulo) {
      const h = merryData[i];
      if (h.annotation) {
        const annotationDom = document.createElement("span");
        annotationDom.className = "annotation" + (i === 0 ? " first" : "");
        if (i !== 0) {
          annotationDom.style.position = "absolute";
          annotationDom.style.display = "inline-block";
          annotationDom.style.width = "40px";
          annotationDom.style.marginLeft = "-20px";
          annotationDom.style.height = "5px";
          annotationDom.style.textAlign = "center";
        }
        annotationDom.style.left = i * tickSpacing + "px";
        annotationDom.innerText = h.annotation;
        annotationsDom.appendChild(annotationDom);
      }
    }
    timelineDom.appendChild(annotationsDom);
  }
  return timelineDom;
};
const timeline = (domElement, merryData, options = {}) => {
  domElement.replaceChildren(init(domElement, merryData, options));

  window.addEventListener("resize", function (_event) {
    domElement.replaceChildren(init(domElement, merryData, options));
  });
};

// ]]>
</script>
<?php // end tabber JS
} 
// End of functions --------------------------------------------------------------------------
