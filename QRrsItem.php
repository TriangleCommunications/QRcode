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

class QRrsItem {

    /** Bits per symbol */
    public $mm;           
    /** Symbols per block (= (1<<mm)-1) */
    public $nn;      
    /** Log lookup table */
    public $alpha_to = array();
    /** Antilog lookup table */
    public $index_of = array();
    /** Generator polynomial */
    public $genpoly = array();
    /** Number of generator roots = number of parity symbols */
    public $nroots;              
    /** First consecutive root, index form */
    public $fcr;                 
    /** Primitive element, index form */
    public $prim;                
    /** Prim-th root of 1, index form */
    public $iprim;               
    /** Padding bytes in shortened block */
    public $pad;                 
    /** Galois Field Polynomial */
    public $gfpoly;              

    //----------------------------------------------------------------------
    /** Modulo function in defined Field
    @param Integer $x number to be modulo-mapped
    */ 
    public function modnn($x)
    {
        while ($x >= $this->nn) {
            $x -= $this->nn;
            $x = ($x >> $this->mm) + ($x & $this->nn);
        }
        
        return $x;
    }
    
    //----------------------------------------------------------------------
    /** Encoder initialisation
        @param Integer $symsize symbol size, bit count (1..8)
        @param Integer $gfpoly Galois Field Polynomial
        @param Integer $fcr First consecutive root
        @param Integer $prim Primitive element
        @param Integer $nroots Number of generator roots = number of parity symbols
        @param Integer $pad Padding bytes in shortened block
    */
    public static function init_rs_char($symsize, $gfpoly, $fcr, $prim, $nroots, $pad)
    {
        // Common code for intializing a Reed-Solomon control block (char or int symbols)
        // Copyright 2004 Phil Karn, KA9Q
        // May be used under the terms of the GNU Lesser General Public License (LGPL)

        $rs = null;
        
        // Check parameter ranges
        if($symsize < 0 || $symsize > 8)                     return $rs;
        if($fcr < 0 || $fcr >= (1<<$symsize))                return $rs;
        if($prim <= 0 || $prim >= (1<<$symsize))             return $rs;
        if($nroots < 0 || $nroots >= (1<<$symsize))          return $rs; // Can't have more roots than symbol values!
        if($pad < 0 || $pad >= ((1<<$symsize) -1 - $nroots)) return $rs; // Too much padding

        $rs = new QRrsItem();
        $rs->mm = $symsize;
        $rs->nn = (1<<$symsize)-1;
        $rs->pad = $pad;

        $rs->alpha_to = array_fill(0, $rs->nn+1, 0);
        $rs->index_of = array_fill(0, $rs->nn+1, 0);
      
        // PHP style macro replacement ;)
        $NN =& $rs->nn;
        $A0 =& $NN;
        
        // Generate Galois field lookup tables
        $rs->index_of[0] = $A0; // log(zero) = -inf
        $rs->alpha_to[$A0] = 0; // alpha**-inf = 0
        $sr = 1;
      
        for($i=0; $i<$rs->nn; $i++) {
            $rs->index_of[$sr] = $i;
            $rs->alpha_to[$i] = $sr;
            $sr <<= 1;
            if($sr & (1<<$symsize)) {
                $sr ^= $gfpoly;
            }
            $sr &= $rs->nn;
        }
        
        if($sr != 1){
            // field generator polynomial is not primitive!
            $rs = NULL;
            return $rs;
        }

        /* Form RS code generator polynomial from its roots */
        $rs->genpoly = array_fill(0, $nroots+1, 0);
    
        $rs->fcr = $fcr;
        $rs->prim = $prim;
        $rs->nroots = $nroots;
        $rs->gfpoly = $gfpoly;

        /* Find prim-th root of 1, used in decoding */
        for($iprim=1;($iprim % $prim) != 0;$iprim += $rs->nn)
        ; // intentional empty-body loop!
        
        $rs->iprim = (int)($iprim / $prim);
        $rs->genpoly[0] = 1;
        
        for ($i = 0,$root=$fcr*$prim; $i < $nroots; $i++, $root += $prim) {
            $rs->genpoly[$i+1] = 1;

            // Multiply rs->genpoly[] by  @**(root + x)
            for ($j = $i; $j > 0; $j--) {
                if ($rs->genpoly[$j] != 0) {
                    $rs->genpoly[$j] = $rs->genpoly[$j-1] ^ $rs->alpha_to[$rs->modnn($rs->index_of[$rs->genpoly[$j]] + $root)];
                } else {
                    $rs->genpoly[$j] = $rs->genpoly[$j-1];
                }
            }
            // rs->genpoly[0] can never be zero
            $rs->genpoly[0] = $rs->alpha_to[$rs->modnn($rs->index_of[$rs->genpoly[0]] + $root)];
        }
        
        // convert rs->genpoly[] to index form for quicker encoding
        for ($i = 0; $i <= $nroots; $i++)
            $rs->genpoly[$i] = $rs->index_of[$rs->genpoly[$i]];

        return $rs;
    }
    
    //----------------------------------------------------------------------
    /** Appends char into encoder
        @param String input
        @param Array parity table
    */
    public function encode_rs_char($data, &$parity)
    {
        $MM       =& $this->mm;
        $NN       =& $this->nn;
        $ALPHA_TO =& $this->alpha_to;
        $INDEX_OF =& $this->index_of;
        $GENPOLY  =& $this->genpoly;
        $NROOTS   =& $this->nroots;
        $FCR      =& $this->fcr;
        $PRIM     =& $this->prim;
        $IPRIM    =& $this->iprim;
        $PAD      =& $this->pad;
        $A0       =& $NN;

        $parity = array_fill(0, $NROOTS, 0);

        for($i=0; $i< ($NN-$NROOTS-$PAD); $i++) {
            
            $feedback = $INDEX_OF[$data[$i] ^ $parity[0]];
            if($feedback != $A0) {      
                // feedback term is non-zero
        
                // This line is unnecessary when GENPOLY[NROOTS] is unity, as it must
                // always be for the polynomials constructed by init_rs()
                $feedback = $this->modnn($NN - $GENPOLY[$NROOTS] + $feedback);
        
                for($j=1;$j<$NROOTS;$j++) {
                    $parity[$j] ^= $ALPHA_TO[$this->modnn($feedback + $GENPOLY[$NROOTS-$j])];
                }
            }
            
            // Shift 
            array_shift($parity);
            if($feedback != $A0) {
                array_push($parity, $ALPHA_TO[$this->modnn($feedback + $GENPOLY[0])]);
            } else {
                array_push($parity, 0);
            }
        }
    }
}