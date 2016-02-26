<?php

/*
 * Based on libqrencode C library distributed under LGPL 2.1
 * Copyright (C) 2006, 2007, 2008, 2009 Kentaro Fukuchi <fukuchi@megaui.net>
 *
 * PHP QR Code is distributed under LGPL 3
 * Copyright (C) 2010 Dominik Dzienia <deltalab at poczta dot fm>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */
     
/** \defgroup QR_DEFCONFIGS Global Config
Global config file (contains global configuration-releted constants).
Before version 2.0.0 only way to configure all calls. From version 2.0.0 values
used here are treated as __defaults__ but culd be overwriten by additional config. 
parrameters passed to functions.
* @{ 
*/

/** Mask cache switch. 
__Boolean__ Speciffies does mask ant template caching is enabled. 
- __true__ - disk cache is used, more disk reads are performed but less CPU power is required,
- __false__ - mask and format templates are calculated each time in memory */
define('QR_CACHEABLE', true);

/** Cache dir path.
__String__ Used when QR_CACHEABLE === true. Specifies absolute server path
for masks and format templates cache dir  */
define('QR_CACHE_DIR', dirname(__FILE__).DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR);

/** Default error logs dir.
__String__ Absolute server path for log directory. */
define('QR_LOG_DIR', dirname(__FILE__).DIRECTORY_SEPARATOR);

/** If best mask is found.
__Boolean__ Speciffies mask searching strategy:
- __true__ - estimates best mask (as QR-Code spec recomends by default) but may be extremally slow
- __false__ - check only one mask (specified by __QR_DEFAULT_MASK__), gives significant performance boost but (propably) results in worst quality codes
*/
define('QR_FIND_BEST_MASK', true);

/** Configure random mask checking.
Specifies algorithm for mask selection when __QR_FIND_BEST_MASK__ is set to __true__.
- if Boolean __false__ - checks all masks available
- if Integer __1..7__ - value tells count of randomly selected masks need to be checked
*/
define('QR_FIND_FROM_RANDOM', false);

/** Default an only mask to apply.
__Integer__ Specifies mask no (1..8) to be aplied every time, used when __QR_FIND_BEST_MASK__ is set to __false__.
*/
define('QR_DEFAULT_MASK', 2);

/** Maximum allowed png image width (in pixels). 
__Integer__ Maximal width/height of generated raster image.
Tune to make sure GD and PHP can handle such big images.
*/
define('QR_PNG_MAXIMUM_SIZE',  1024);                                                       

/** @}*/

/** \defgroup QR_CONST Global Constants
Constant used globally for function arguments.
Make PHP calls a little bit more clear, in place of missing (in dynamicaly typed language) enum types.
* @{ 
*/
 
/** @name QR-Code Encoding Modes */
/** @{ */

/** null encoding, used when no encoding was speciffied yet */
define('QR_MODE_NUL', -1);   
/** Numerical encoding, only numbers (0-9) */	
define('QR_MODE_NUM', 0);   
/** AlphaNumerical encoding, numbers (0-9) uppercase text (A-Z) and few special characters (space, $, %, *, +, -, ., /, :) */    
define('QR_MODE_AN', 1);  
/** 8-bit encoding, raw 8 bit encoding */
define('QR_MODE_8', 2);    
/** Kanji encoding */	
define('QR_MODE_KANJI', 3);    
/** Structure, internal encoding for structure-related data */	
define('QR_MODE_STRUCTURE', 4); 
/**@}*/

/** @name QR-Code Levels of Error Correction 
Constants speciffy ECC level from lowest __L__ to the highest __H__. 
Higher levels are recomended for Outdoor-presented codes, but generates bigger codes.
*/
/** @{*/
/** ~7% of codewords can be restored */
define('QR_ECLEVEL_L', 0); 
/** ~15% of codewords can be restored */
define('QR_ECLEVEL_M', 1); 
/** ~25% of codewords can be restored */
define('QR_ECLEVEL_Q', 2);
/** ~30% of codewords can be restored */
define('QR_ECLEVEL_H', 3);
/** @}*/

/** @name QR-Code Supported output formats */
/** @{*/
define('QR_FORMAT_TEXT', 0);
define('QR_FORMAT_PNG',  1);
/** @}*/

/** @}*/

/** Maximal Version no allowed by QR-Code spec */
define('QRSPEC_VERSION_MAX', 40);
/** Maximal Code size in pixels allowed by QR-Code spec */
define('QRSPEC_WIDTH_MAX',   177);

define('QRCAP_WIDTH',        0);
define('QRCAP_WORDS',        1);
define('QRCAP_REMINDER',     2);
define('QRCAP_EC',           3);
    

/** 
__Main class to create QR-code__.
QR Code symbol is a 2D barcode that can be scanned by handy terminals such as a mobile phone with CCD.
The capacity of QR Code is up to 7000 digits or 4000 characters, and has high robustness.
This class supports QR Code model 2, described in JIS (Japanese Industrial Standards) X0510:2004 or ISO/IEC 18004.

Currently the following features are not supported: ECI and FNC1 mode, Micro QR Code, QR Code model 1, Structured mode.

@abstract Class for generating QR-code images, SVG and HTML5 Canvas 
@author Dominik Dzienia
@copyright 2010-2013 Dominik Dzienia and others
@link http://phpqrcode.sourceforge.net
@license http://www.gnu.org/copyleft/lesser.html LGPL
*/

class QRcode {

    public $version;    ///< __Integer__ QR code version. Size of QRcode is defined as version. Version is from 1 to 40. Version 1 is 21*21 matrix. And 4 modules increases whenever 1 version increases. So version 40 is 177*177 matrix.
    public $width;      ///< __Integer__ Width of code table. Because code is square shaped - same as height.
    public $data;       ///< __Array__ Ready, masked code data.
    
    /** Canvas JS include flag.
    If canvas js support library was included, we remember it static in QRcode. 
    (because file should be included only once)
     */
    public static $jscanvasincluded = false;
    
    //----------------------------------------------------------------------
    /**
    Encode mask
    Main function responsible for creating code. 
    We get empty frame, then fill it with data from input, then select best mask and apply it.
    If $mask argument is greater than -1 we assume that user want's that specific mask number (ranging form 0-7) to be used.
    Otherwise (when $mask is -1) mask is detected using algorithm depending of global configuration,
    
    @param QRinput $input data object
    @param Integer $mask sugested masking mode
    @return QRcode $this (current instance)
    */
    public function encodeMask(QRinput $input, $mask)
    {
        if($input->getVersion() < 0 || $input->getVersion() > QRSPEC_VERSION_MAX) {
            throw new Exception('wrong version');
        }
        if($input->getErrorCorrectionLevel() > QR_ECLEVEL_H) {
            throw new Exception('wrong level');
        }

        $raw = new QRrawcode($input);
        
        QRtools::markTime('after_raw');
        
        $version = $raw->version;
        $width = QRspec::getWidth($version);
        $frame = QRspec::newFrame($version);
        
        $filler = new QRframeFiller($width, $frame);
        if(is_null($filler)) {
            return NULL;
        }

        // inteleaved data and ecc codes
        for($i=0; $i<$raw->dataLength + $raw->eccLength; $i++) {
            $code = $raw->getCode();
            $bit = 0x80;
            for($j=0; $j<8; $j++) {
                $addr = $filler->next();
                $filler->setFrameAt($addr, 0x02 | (($bit & $code) != 0));
                $bit = $bit >> 1;
            }
        }
        
        QRtools::markTime('after_filler');
        
        unset($raw);
        
        // remainder bits
        $j = QRspec::getRemainder($version);
        for($i=0; $i<$j; $i++) {
            $addr = $filler->next();
            $filler->setFrameAt($addr, 0x02);
        }
        
        $frame = $filler->frame;
        unset($filler);
        
        
        // masking
        $maskObj = new QRmask();
        if($mask < 0) {
        
            if (QR_FIND_BEST_MASK) {
                $masked = $maskObj->mask($width, $frame, $input->getErrorCorrectionLevel());
            } else {
                $masked = $maskObj->makeMask($width, $frame, (intval(QR_DEFAULT_MASK) % 8), $input->getErrorCorrectionLevel());
            }
        } else {
            $masked = $maskObj->makeMask($width, $frame, $mask, $input->getErrorCorrectionLevel());
        }
        
        if($masked == NULL) {
            return NULL;
        }
        
        QRtools::markTime('after_mask');
        
        $this->version  = $version;
        $this->width    = $width;
        $this->data     = $masked;
        
        return $this;
    }

    //----------------------------------------------------------------------
    /**
    Encode input with mask detection.
    Shorthand for encodeMask, without specifing particular, static mask number.
    
    @param QRinput $input data object to be encoded
    @return 
    */
    public function encodeInput(QRinput $input)
    {
        return $this->encodeMask($input, -1);
    }
    
    //----------------------------------------------------------------------
    /**
    Encode string, forcing 8-bit encoding
    @param String $string input string
    @param Integer $version code version (size of code area)
    @param Integer $level ECC level (see: Global Constants -> Levels of Error Correction)
    @return QRcode $this (current instance)
    */
    public function encodeString8bit($string, $version, $level)
    {
        if($string == NULL) {
            throw new Exception('empty string!');
            return NULL;
        }

        $input = new QRinput($version, $level);
        if($input == NULL) return NULL;

        $ret = $input->append(QR_MODE_8, strlen($string), str_split($string));
        if($ret < 0) {
            unset($input);
            return NULL;
        }
        return $this->encodeInput($input);
    }

    //----------------------------------------------------------------------
    /**
    Encode string, using optimal encodings.
    Encode string dynamically adjusting encoding for subsections of string to
    minimize resulting code size. For complex string it will split string into
    subsections: Numerical, Alphanumerical or 8-bit.
    @param String $string input string
    @param Integer $version code version (size of code area)
    @param String $level ECC level (see: Global Constants -> Levels of Error Correction)
    @param Integer $hint __QR_MODE_8__ or __QR_MODE_KANJI__, Because Kanji encoding
    is kind of 8 bit encoding we need to hint encoder to use Kanji mode explicite.
    (otherwise it may try to encode it as plain 8 bit stream)
    @param Boolean $casesensitive hint if given string is case-sensitive, because
    if not - encoder may use optimal QR_MODE_AN instead of QR_MODE_8
    @return QRcode $this (current instance)
    */
    public function encodeString($string, $version, $level, $hint, $casesensitive)
    {

        if($hint != QR_MODE_8 && $hint != QR_MODE_KANJI) {
            throw new Exception('bad hint');
            return NULL;
        }

        $input = new QRinput($version, $level);
        if($input == NULL) return NULL;

        $ret = QRsplit::splitStringToQRinput($string, $input, $hint, $casesensitive);
        if($ret < 0) {
            return NULL;
        }

        return $this->encodeInput($input);
    }
    
    //######################################################################
    /**
    Creates PNG image containing QR-Code.
    Simple helper function to create QR-Code Png image with one static call.
    @param String $text text string to encode 
    @param String $outfile (optional) output file name, if __false__ outputs to browser with required headers
    @param Integer $level (optional) error correction level __QR_ECLEVEL_L__, __QR_ECLEVEL_M__, __QR_ECLEVEL_Q__ or __QR_ECLEVEL_H__
    @param Integer $size (optional) pixel size, multiplier for each 'virtual' pixel
    @param Integer $margin (optional) code margin (silent zone) in 'virtual'  pixels
    @param Boolean $saveandprint (optional) if __true__ code is outputed to browser and saved to file, otherwise only saved to file. It is effective only if $outfile is specified.
    */
    
    public static function png($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint=false) 
    {
        $enc = QRencode::factory($level, $size, $margin);
        return $enc->encodePNG($text, $outfile, $saveandprint=false);
    }

    //----------------------------------------------------------------------
    /**
    Creates text (1's & 0's) containing QR-Code.
    Simple helper function to create QR-Code text with one static call.
    @param String $text text string to encode 
    @param String $outfile (optional) output file name, when __false__ file is not saved
    @param Integer $level (optional) error correction level __QR_ECLEVEL_L__, __QR_ECLEVEL_M__, __QR_ECLEVEL_Q__ or __QR_ECLEVEL_H__
    @param Integer $size (optional) pixel size, multiplier for each 'virtual' pixel
    @param Integer $margin (optional) code margin (silent zone) in 'virtual'  pixels
    @return Array containing line of code with 1 and 0 for every code line
    */
    
    public static function text($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4) 
    {
        $enc = QRencode::factory($level, $size, $margin);
        return $enc->encode($text, $outfile);
    }

    //----------------------------------------------------------------------
    /**
    Creates Raw Array containing QR-Code.
    Simple helper function to create QR-Code array with one static call.
    @param String $text text string to encode 
    @param Boolean $outfile (optional) not used, shuold be __false__
    @param Integer $level (optional) error correction level __QR_ECLEVEL_L__, __QR_ECLEVEL_M__, __QR_ECLEVEL_Q__ or __QR_ECLEVEL_H__
    @param Integer $size (optional) pixel size, multiplier for each 'virtual' pixel
    @param Integer $margin (optional) code margin (silent zone) in 'virtual'  pixels
    @return Array containing Raw QR code
    */
    
    public static function raw($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4) 
    {
        $enc = QRencode::factory($level, $size, $margin);
        return $enc->encodeRAW($text, $outfile);
    }
    
    //----------------------------------------------------------------------
    /**
    Creates Html+JS code to draw  QR-Code with HTML5 Canvas.
    Simple helper function to create QR-Code array with one static call.
    @param String $text text string to encode 
    @param String $elemId (optional) target Canvas tag id attribute, if __false__ Canvas tag with auto id will be created 
    @param Integer $level (optional) error correction level __QR_ECLEVEL_L__, __QR_ECLEVEL_M__, __QR_ECLEVEL_Q__ or __QR_ECLEVEL_H__
    @param Integer $width (optional) CANVAS element width (sam as height)
    @param Integer $size (optional) pixel size, multiplier for each 'virtual' pixel
    @param Integer $margin (optional) code margin (silent zone) in 'virtual'  pixels
    @param Boolean $autoInclude (optional) if __true__, required qrcanvas.js lib will be included (only once)
    @return String containing JavaScript creating the code, Canvas element (when $elemId is __false__) and script tag with required lib (when $autoInclude is __true__ and not yet included)
    */
    
    public static function canvas($text, $elemId = false, $level = QR_ECLEVEL_L, $width = false, $size = false, $margin = 4, $autoInclude = false) 
    {
        $html = '';
        $extra = '';
        
        if ($autoInclude) {
            if (!self::$jscanvasincluded) {
                self::$jscanvasincluded = true;
                echo '<script type="text/javascript" src="qrcanvas.js"></script>';
            }
        }
        
        $enc = QRencode::factory($level, 1, 0);
        $tab_src = $enc->encode($text, false);
        $area = new QRcanvasOutput($tab_src);
        $area->detectGroups();
        $area->detectAreas();
        
        if ($elemId === false) {
            $elemId = 'qrcode-'.md5(mt_rand(1000,1000000).'.'.mt_rand(1000,1000000).'.'.mt_rand(1000,1000000).'.'.mt_rand(1000,1000000));
            
            if ($width == false) {
                if (($size !== false) && ($size > 0))  {
                    $width = ($area->getWidth()+(2*$margin)) * $size;
                } else {
                    $width = ($area->getWidth()+(2*$margin)) * 4;
                }
            }
            
            $html .= '<canvas id="'.$elemId.'" width="'.$width.'" height="'.$width.'">Your browser does not support CANVAS tag! Please upgrade to modern version of FireFox, Opera, Chrome or Safari/Webkit based browser</canvas>';
        }
        
        if ($width !== false) {
            $extra .= ', '.$width.', '.$width;
        } 
            
        if ($margin !== false) {
            $extra .= ', '.$margin.', '.$margin;                
        }
        
        $html .= '<script>if(eval("typeof "+\'QRdrawCode\'+"==\'function\'")){QRdrawCode(QRdecompactOps(\''.$area->getCanvasOps().'\')'."\n".', \''.$elemId.'\', '.$area->getWidth().' '.$extra.');}else{alert(\'Please include qrcanvas.js!\');}</script>';
        
        return $html;
    }
    
    //----------------------------------------------------------------------
    /**
    Creates SVG with QR-Code.
    Simple helper function to create QR-Code SVG with one static call.
    @param String $text text string to encode 
    @param Boolean $elemId (optional) target SVG tag id attribute, if __false__ SVG tag with auto id will be created 
    @param String $outfile (optional) output file name, when __false__ file is not saved
    @param Integer $level (optional) error correction level __QR_ECLEVEL_L__, __QR_ECLEVEL_M__, __QR_ECLEVEL_Q__ or __QR_ECLEVEL_H__
    @param Integer $width (optional) SVG element width (sam as height)
    @param Integer $size (optional) pixel size, multiplier for each 'virtual' pixel
    @param Integer $margin (optional) code margin (silent zone) in 'virtual'  pixels
    @param Boolean $compress (optional) if __true__, compressed SVGZ (instead plaintext SVG) is saved to file
    @return String containing SVG tag
    */
    
    public static function svg($text, $elemId = false, $outFile = false, $level = QR_ECLEVEL_L, $width = false, $size = false, $margin = 4, $compress = false) 
    {
        $enc = QRencode::factory($level, 1, 0);
        $tab_src = $enc->encode($text, false);
        $area = new QRsvgOutput($tab_src);
        $area->detectGroups();
        $area->detectAreas();
        
        if ($elemId === false) {
            $elemId = 'qrcode-'.md5(mt_rand(1000,1000000).'.'.mt_rand(1000,1000000).'.'.mt_rand(1000,1000000).'.'.mt_rand(1000,1000000));
            
            if ($width == false) {
                if (($size !== false) && ($size > 0))  {
                    $width = ($area->getWidth()+(2*$margin)) * $size;
                } else {
                    $width = ($area->getWidth()+(2*$margin)) * 4;
                }
            }
        }
        
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"
        xmlns:xlink="http://www.w3.org/1999/xlink"
        version="1.1"
        baseProfile="full"
        viewBox="'.(-$margin).' '.(-$margin).' '.($area->getWidth()+($margin*2)).' '.($area->getWidth()+($margin*2)).'" 
        width="'.$width.'"
        height="'.$width.'"
        id="'.$elemId.'">'."\n";

        $svg .= $area->getRawSvg().'</svg>';

        if ($outFile !== false) {
            $xmlPreamble = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n";
            $svgContent = $xmlPreamble.$svg;
            
            if ($compress === true) {
                file_put_contents($outFile, gzencode($svgContent));
            } else {
                file_put_contents($outFile, $svgContent);
            }
        }
        
        return $svg;
    }
}