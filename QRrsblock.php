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

/** 
 * Data block with raw data and it's Error Correction Code data.
 */
class QRrsblock {
    public $dataLength;
    public $data = array();
    public $eccLength;
    public $ecc = array();
    
    /** Data block Constructor
    @param Integer $dl length of data stream
    @param Array $data data stream
    @param Integer $el ECC length
    @param Array $el ECC stream (modified, by reference)
    @param QRrsItem $rs RS encoding item
    */
    public function __construct($dl, $data, $el, &$ecc, QRrsItem $rs)
    {
        $rs->encode_rs_char($data, $ecc);
    
        $this->dataLength = $dl;
        $this->data = $data;
        $this->eccLength = $el;
        $this->ecc = $ecc;
    }
}