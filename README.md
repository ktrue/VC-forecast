# VisualCrossing.com International forecast formatting script - multilingual

This script is based on the DarkSky DS-forecast.php script and uses the API kindly provided by [https://visualcrossing.com](https://visualcrossing.com).  A companion script **VC-forecast-lang.php** contains English->language lookups for use by **VC-forecast.php**.
The current languages supported are:

Afrikaans | български език | český jazyk | Català | Dansk | Nederlands | English | Suomi | Français | Deutsch | Ελληνικά | Magyar | Italiano | עִבְרִית | Norsk | Polski | Português | limba română | Español | Svenska | Slovenščina | Slovenčina | Srpski

Note: Version 2.5+ now includes a 7-day timeline display based on [Merry-Timeline](https://github.com/guillaume/merry-timeline) by Guillaume Carbonneau to replace the former 48 hourly forecasts with icons.

In order to use this script you need to:

1.  Register for and acquire a free VisualCrossing.com API key.
    1.  Browse to [https://visualcrossing.com](https://visualcrossing.com) and sign up/in to acquire an API key.
    2.  insert the API key in **$VCAPIkey** in the VC-forecast.php script or as **$SITE['VCAPIkey']** in _Settings.php_ for Saratoga template users.
    3.  Customize the **$VCforecasts** array (or **$SITE['VCforecasts']** in _Settings.php_) with the location names, latitude/longitude for your forecasts. The first entry will be the default one for forecasts.
2.  Use this script ONLY on your personal, non-commercial weather station website.
3.  Leave attribution (and hotlink) to VisualCrossing.com as the source of the data in the output of the script.

Adhere to these three requirements, and you should have fair use of this data from visualcrossing.com.

## Settings in the VC-forecast.php script

```
// Settings ---------------------------------------------------------------
// REQUIRED: a visualcrossing.com API KEY.. sign up at https://visualcrossing.com
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
'Saratoga|37.27465,-122.02295',
'Auckland|-36.910,174.771', // Awhitu, Waiuku New Zealand
);

//
$maxWidth = '640px';                      // max width of tables (could be '100%')
$maxIcons = 10;                           // max number of icons to display
$maxForecasts = 14;                       // max number of Text forecast periods to display
$maxForecastLegendWords = 4;              // more words in forecast legend than this number will use our forecast words
$numIconsInFoldedRow = 5;                 // if words cause overflow of $maxWidth pixels, then put this num of icons in rows
$autoSetTemplate = true;                  // =true set icons based on wide/narrow template design
$cacheFileDir = './';                     // default cache file directory
$cacheName = "VC-forecast-json.txt";      // locally cached page from VC
$refetchSeconds = 3600;                   // cache lifetime (3600sec = 60 minutes)
//
// Units: 
// base: SI units (K,m/s,hPa,mm,km)
// metric: same as base, except that temp in C and windSpeed and windGust are in kilometers per hour
// uk: same as metric, except that nearestStormDistance and visibility are in miles, and windSpeed and windGust in miles per hour
// us: Imperial units (F,mph,inHg,in,miles)
// 
$showUnitsAs  = 'metric'; // ='us' for imperial, , ='metric' for metric, ='uk' for UK
//
$showUnitsAs  = 'ca'; // ='us' for imperial, , ='si' for metric, ='ca' for canada, ='uk2' for UK
//
$charsetOutput = 'ISO-8859-1';        // default character encoding of output
//$charsetOutput = 'UTF-8';            // for standalone use if desired
$lang = 'en';	// default language
$foldIconRow = false;  // =true to display icons in rows of 5 if long texts are found
// ---- end of settings ---------------------------------------------------
```

For Saratoga template users, you normally do not have to customize the script itself as the most common configurable settings are maintained in your _Settings.php_ file. This allows you to just replace the _VC-forecast.php_ on your site when new versions are released.  
You DO have to add a **$SITE['VCAPIkey'] = '_your-key-here_';** and a **$SITE['VCforecasts] = array( ...);** entries to your _Settings.php_ file to support this and future releases of the script.

<dl>

<dt>$VCAPIkey = 'specify-for-standalone-use-here';</dt>

<dd>This setting is for **standalone** use (do not change this for Saratoga templates).  
Register for a Pirateweather API Key at https://visualcrossing.com and replace _specify-for-standalone-use-here_ with the registered API key. The script will nag you if this has not been done.  

**For Saratoga template users**, do the registration at the Pirateweather API site above, then put your API key in your _Settings.php_ as:  

$SITE['VCAPIkey'] = '_your-key-here_';  

to allow easy future updates of the VC-forecast.php script by simple replacement.</dd>

<dt>$iconDir</dt>

<dd>This setting controls whether to display the NOAA-styled icons on the forecast display.  
Set $iconDir to the relative file path to the Saratoga Icon set (same set as used with the WXSIM plaintext-parser.php script).  
Be sure to include the trailing slash in the directory specification as shown in the example above.  
**Saratoga template users:** Use the _Settings.php_ entry for **$SITE['fcsticonsdir']** to specify this value.</dd>

<dt>$iconType<dt>

<dd>This setting controls the extension (type) for the icon to be displayed.  
**='.jpg';** for the default Saratoga JPG icon set.  
**='.gif';** for the Meteotriviglio animated GIF icon set.  
**Saratoga template users:** Use the _Settings.php_ entry for **$SITE['fcsticonstype']** to specify this value.</dd>

<dt>$VCforecasts = array(  <br>
// Location|forecast-URL (separated by | characters) <br> 
'Saratoga|37.27465,-122.02295', <br> 
'Auckland|-36.910,174.771', // Awhitu, Waiuku New Zealand <br> 
<br>
... <br> 
);</dt>

<dd>This setting is the primary method of specifying the locations for forecasts. It allows the viewer to choose between forecasts for different areas based on a drop-down list box selection.  
**Saratoga template users**: Use the _Settings.php_ entry for **$SITE['VCforecasts'] = array(...);** to specify the list of sites and URLs.</dd>

<dt>$maxWidth</dt>

<dd>This variable controls the maximum width of the tables for the icons and text display. It may be in pixels (as shown), or '100%'. The Saratoga/NOAA icons are 55px wide and there are up to 8 icons, so beware setting this width too small as the display may be quite strange.</dd>

<dt>$maxIcons<dt>

<dd>This variable specifies the maximum number of icons to display in the graphical part of the forecast. Some forecast locations may have up to 8 days of forecast (8 icons) so be careful how wide the forecast may become on the page.</dd>

<dt>$cacheFileDir</dt>

<dd>This setting specifies the directory to store the cache files. The default is the same directory in which the script is located.  
Include the trailing slash in the directory specification.  
**Saratoga template users:** Use the _Settings.php_ entry for **$SITE['cacheFileDir']** to specify this value.</dd>

<dt>$cacheName</dt>

<dd>This variable specifies the name of the cache file for the VC forecast page.</dd>

<dt>$refetchSeconds</dt>

<dd>This variable specifies the cache lifetime, or how long to use the cache before reloading a copy from Pirateweather. The default is 3600 seconds (60 minutes). Forecasts don't change very often, so please don't reduce it below 60 minutes to minimize your API access count and keep it to the free Developer API range.</dd>

<dt>$showUnitsAs</dt>

<dd>This setting controls the units of measure for the forecasts. <br> 
 ='base' SI units (K,m/s,hPa,mm,km)<br>  
 ='metric' same as si, except that temperature in C and windSpeed and windGust are in kilometers per hour <br> 
 ='uk' same as metric, except that nearestStormDistance and visibility are in miles, and windSpeed and windGust in miles per hour <br> 
 ='us' Imperial units (F,mph,inHg,in,miles)<br>
<br>
**Saratoga template users:** This setting will be overridden by the **$SITE['VCshowUnitsAs']** specified in your _Settings.php_.  
</dd>

<dt>$foldIconRow</dt>

<dd>This setting controls 'folding' of the icons into two rows if the aggregate width of characters exceeds the $maxSize dimension in pixels.  
**= true;** is the default (fold the row)  
**= false;** to select not to fold the row.  
**Saratoga template users:** Use the _Settings.php_ entry for **$SITE['foldIconRow']** to specify this value.</dd>

</dl>

More documentation is contained in the script itself about variable names/arrays made available, and the contents. The samples below serve to illustrate some of the possible usages on your weather website.

## Usage samples

```
<?php  
$doIncludeVC = true;  
include("VC-forecast.php"); ?>
```

You can also include it 'silently' and print just a few (or all) the contents where you'd like it on the page

```
<?php  
$doPrintVC = false;  
require("VC-forecast.php"); ?>  
```

then on your page, the following code would display just the current and next time period forecast:

```
<table>  
<tr align="center" valign="top">  
<?php print "<td>$VCforecasticons[0]</td><td>$VCforecasticons[1]</td>\n"; ?>  
</tr>  
<tr align="center" valign="top">  
<?php print "<td>$VCforecasttemp[0]</td><td>$VCforecasttemp[1]</td>\n"; ?>  
</tr>  
</table>
```

Or if you'd like to include the immediate forecast with text for the next two cycles:

```
<table>  
<tr valign="top">  
<?php print "<td align=\"center\">$VCforecasticons[0]<br />$VCforecasttemp[0]</td>\n"; ?>  
<?php print "<td align=\"left\" valign=\"middle\">$VCforecasttext[0]</td>\n"; ?>  
</tr>  
<tr valign="top">  
<?php print "<td align=\"center\">$VCforecasticons[1]<br />$VCforecasttemp[1]</td>\n"; ?>  
<?php print "<td align=\"left\" valign=\"middle\">$VCforecasttext[1]</td>\n"; ?>  
</tr>  
</table>
```

If you'd like to style the output, you can easily do so by setting a CSS for class **VCforecast** either in your CSS file or on the page including the VC-forecast.php (in include mode):


```
<style type="text/css">    
.VCforecast {    
    font-family: Verdana, Arial, Helvetica, sans-serif;    
    font-size: 9pt;    
}    
</style>
```

## Installation of VC-forecast.php

Download **VC-forecast.php** and **VC-forecast-lang.php** .

Download the [ **Icon Set** ](https://saratoga-weather.org/saratoga-icons2.zip) , and upload to ./forecast/images directory. This icon set is also used by advforecast2.php, WXSIM plaintext-parser.php, AW-forecast.php, DS-forecast.php, and WC-forecast.php scripts -- if you'd used any of them, you likely have the correct icon set installed already.

Change settings in **VC-forecast.php** for the **$VCforecast** address(s) and the address of the icons if necessary and upload the modified VC-forecast.php to your website.

Ensure the permission on "VC-forecast-json-{n}-{units}.txt" cache file(s) are at least 666 or 766 so the file is writable by the VC-forecst.php script

**Demo:**[ **VC-forecast-demo.php** ](https://saratoga-weather.org/VC-forecast-demo.php)(note: uses UTF-8 only mode)  

**Download**:[ **Icon Set** ](https://saratoga-weather.org/saratoga-icons2.zip)(upload to your website in the **/forecast/images** directory)  

# Sample output
## English
![English Sample Output](https://user-images.githubusercontent.com/17507343/216792916-36c984da-109b-424e-aef5-8880bbe8fd6a.jpg)
## English (Hourly)
![English Sample Output](https://user-images.githubusercontent.com/17507343/216792917-1f562f65-8ddd-4690-b226-358c38397a86.jpg)
## Ελληνικά (Greek)
![Greek Sample Output](https://user-images.githubusercontent.com/17507343/216792918-5050733b-cb3f-46e9-95f4-689e21de04e3.jpg)
## Ελληνικά (Greek) Hourly
![Greek Sample Output](https://user-images.githubusercontent.com/17507343/216792921-b4afd504-0fff-4d4a-a9f8-a3066b9c1273.jpg)
