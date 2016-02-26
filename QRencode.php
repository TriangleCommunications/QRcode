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

/** QR Code encoder.
Encoder is used by QRCode to create simple static code generators. */
class QRencode {

    public $casesensitive = true; ///< __Boolean__ does input stream id case sensitive, if not encoder may use more optimal charsets
    public $eightbit = false;     ///< __Boolean__ does input stream is 8 bit
    
    public $version = 0;          ///< __Integer__ code version (total size) if __0__ - will be auto-detected
    public $size = 3;             ///< __Integer__ pixel zoom factor, multiplier to map virtual code pixels to image output pixels
    public $margin = 4;           ///< __Integer__ margin (silent zone) size, in code pixels
    
    public $structured = 0;       ///< Structured QR codes. Not supported.
    
    public $level = QR_ECLEVEL_L; ///< __Integer__ error correction level __QR_ECLEVEL_L__, __QR_ECLEVEL_M__, __QR_ECLEVEL_Q__ or __QR_ECLEVEL_H__
    public $hint = QR_MODE_8;     ///< __Integer__ encoding hint, __QR_MODE_8__ or __QR_MODE_KANJI__, Because Kanji encoding is kind of 8 bit encoding we need to hint encoder to use Kanji mode explicite. (otherwise it may try to encode it as plain 8 bit stream)
    
    //----------------------------------------------------------------------
    /** Encoder instances factory.
    @param Integer $level error correction level __QR_ECLEVEL_L__, __QR_ECLEVEL_M__, __QR_ECLEVEL_Q__ or __QR_ECLEVEL_H__
    @param Integer $size pixel zoom factor, multiplier to map virtual code pixels to image output pixels
    @param Integer $margin margin (silent zone) size, in code pixels
    @return builded QRencode instance
    */
    public static function factory($level = QR_ECLEVEL_L, $size = 3, $margin = 4)
    {
        $enc = new QRencode();
        $enc->size = $size;
        $enc->margin = $margin;
        
        switch ($level.'') {
            case '0':
            case '1':
            case '2':
            case '3':
                    $enc->level = $level;
                break;
            case 'l':
            case 'L':
                    $enc->level = QR_ECLEVEL_L;
                break;
            case 'm':
            case 'M':
                    $enc->level = QR_ECLEVEL_M;
                break;
            case 'q':
            case 'Q':
                    $enc->level = QR_ECLEVEL_Q;
                break;
            case 'h':
            case 'H':
                    $enc->level = QR_ECLEVEL_H;
                break;
        }
        
        return $enc;
    }
    
    //----------------------------------------------------------------------
    /** Encodes input into Raw code table.
    @param String $intext input text
    @param Boolean $notused (optional, not used) placeholder for similar outfile parameter
    @return __Array__ Raw code frame
    */
    public function encodeRAW($intext, $notused = false) 
    {
        $code = new QRcode();

        if($this->eightbit) {
            $code->encodeString8bit($intext, $this->version, $this->level);
        } else {
            $code->encodeString($intext, $this->version, $this->level, $this->hint, $this->casesensitive);
        }
        
        return $code->data;
    }

    //----------------------------------------------------------------------
    /** Encodes input into binary code table.
    @param String $intext input text
    @param String $outfile (optional) output file to save code table, if __false__ file will be not saved
    @return __Array__ binary code frame
    */
    public function encode($intext, $outfile = false) 
    {
        $code = new QRcode();

        if($this->eightbit) {
            $code->encodeString8bit($intext, $this->version, $this->level);
        } else {
            $code->encodeString($intext, $this->version, $this->level, $this->hint, $this->casesensitive);
        }
        
        QRtools::markTime('after_encode');
        
        $binarized = QRtools::binarize($code->data);
        if ($outfile!== false) {
            file_put_contents($outfile, join("\n", $binarized));
        }
        
        return $binarized;
    }
    
    //----------------------------------------------------------------------
    /** Encodes input into PNG image.
    @param String $intext input text
    @param String $outfile (optional) output file name, if __false__ outputs to browser with required headers
    @param Boolean $saveandprint (optional) if __true__ code is outputed to browser and saved to file, otherwise only saved to file. It is effective only if $outfile is specified.
    */
    public function encodePNG($intext, $outfile = false, $saveandprint=false) 
    {
        try {
        
            ob_start();
            $tab = $this->encode($intext);
            $err = ob_get_contents();
            ob_end_clean();
            
            if ($err != '')
                QRtools::log($outfile, $err);
            
            $maxSize = (int)(QR_PNG_MAXIMUM_SIZE / (count($tab)+2*$this->margin));
            
            QRimage::png($tab, $outfile, min(max(1, $this->size), $maxSize), $this->margin,$saveandprint);
        
        } catch (Exception $e) {
        
            QRtools::log($outfile, $e->getMessage());
        
        }
    }
}