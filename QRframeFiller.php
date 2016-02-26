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

/** Fills frame with data.
 * Each empty frame consist of markers, timing symbols and format configuration.
 * Remaining place is place for data, and should be filled according to QR Code spec.
 */
class QRframeFiller {

    public $width; ///< __Integer__ Frame width
    public $frame; ///< __Array__ Frame itself
    public $x;     ///< __Integer__ current X position
    public $y;     ///< __Integer__ current Y position
    public $dir;   ///< __Integer__ direction
    public $bit;   ///< __Integer__ bit
    
    //----------------------------------------------------------------------
    /** Frame filler Constructor.
    @param Integer $width frame size
    @param Array $frame Frame array
    */
    public function __construct($width, &$frame)
    {
        $this->width = $width;
        $this->frame = $frame;
        $this->x = $width - 1;
        $this->y = $width - 1;
        $this->dir = -1;
        $this->bit = -1;
    }
    
    //----------------------------------------------------------------------
    /** Sets frame code at given position.
    @param Array $at position, map containing __x__ and __y__ coordinates
    @param Integer $val value to set
    */
    public function setFrameAt($at, $val)
    {
        $this->frame[$at['y']][$at['x']] = chr($val);
    }
    
    //----------------------------------------------------------------------
    /** Gets frame code from given position.
    @param Array $at position, map containing __x__ and __y__ coordinates
    @return Integer value at requested position
    */
    public function getFrameAt($at)
    {
        return ord($this->frame[$at['y']][$at['x']]);
    }
    
    //----------------------------------------------------------------------
    /** Proceed to next code point. */
    public function next()
    {
        do {
        
            if($this->bit == -1) {
                $this->bit = 0;
                return array('x'=>$this->x, 'y'=>$this->y);
            }

            $x = $this->x;
            $y = $this->y;
            $w = $this->width;

            if($this->bit == 0) {
                $x--;
                $this->bit++;
            } else {
                $x++;
                $y += $this->dir;
                $this->bit--;
            }

            if($this->dir < 0) {
                if($y < 0) {
                    $y = 0;
                    $x -= 2;
                    $this->dir = 1;
                    if($x == 6) {
                        $x--;
                        $y = 9;
                    }
                }
            } else {
                if($y == $w) {
                    $y = $w - 1;
                    $x -= 2;
                    $this->dir = -1;
                    if($x == 6) {
                        $x--;
                        $y -= 8;
                    }
                }
            }
            if($x < 0 || $y < 0) return null;

            $this->x = $x;
            $this->y = $y;

        } while(ord($this->frame[$y][$x]) & 0x80);
                    
        return array('x'=>$x, 'y'=>$y);
    }
    
}