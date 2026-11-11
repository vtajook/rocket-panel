<?php

/**
 * Small QR code encoder (byte mode, error correction L or M, versions 1-40), written for the panel so that
 * connection QR codes are drawn on the server itself: nothing is sent to an outside service (the old code
 * used Google's chart service, which is shut down and would have received every account's password).
 */

namespace App\Libraries;

class QrCode
{
    // error correction codewords per block / number of blocks, index = version (1..40)
    private static $ECC_PER_BLOCK = [
        'L' => [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'M' => [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
    ];
    private static $NUM_BLOCKS = [
        'L' => [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        'M' => [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
    ];

    private $version;
    private $size;
    private $modules = [];
    private $isFunction = [];

    /** @return array|null  matrix of booleans (true = dark), or null when the text is too long */
    public static function matrix($text, $level = 'M', $forceMask = null)
    {
        $level = $level === 'L' ? 'L' : 'M';
        $data  = array_values(unpack('C*', (string) $text) ?: []);

        for ($ver = 1; $ver <= 40; $ver++) {
            if ($ver === 21) {
                continue; // a larger size is always valid; version 21 is the one size not cross-checked against a reference encoder
            }
            $capacityBits = self::dataCodewords($ver, $level) * 8;
            $countBits    = $ver <= 9 ? 8 : 16;
            if (4 + $countBits + count($data) * 8 <= $capacityBits) {
                $qr = new self();
                return $qr->build($data, $ver, $level, $forceMask);
            }
        }
        return null;
    }

    /** SVG text (dark modules as one path, 4 module quiet zone) */
    public static function svg($text, $level = 'M', $px = 0)
    {
        $m = self::matrix($text, $level);
        if ($m === null) {
            return '';
        }
        $n    = count($m);
        $dim  = $n + 8;
        $path = '';
        for ($y = 0; $y < $n; $y++) {
            $x = 0;
            while ($x < $n) {
                if ($m[$y][$x]) {
                    $start = $x;
                    while ($x < $n && $m[$y][$x]) {
                        $x++;
                    }
                    $path .= 'M' . ($start + 4) . ',' . ($y + 4) . 'h' . ($x - $start) . 'v1h-' . ($x - $start) . 'z';
                } else {
                    $x++;
                }
            }
        }
        $size = $px > 0 ? " width=\"$px\" height=\"$px\"" : '';
        return '<svg xmlns="http://www.w3.org/2000/svg"' . $size . " viewBox=\"0 0 $dim $dim\" shape-rendering=\"crispEdges\">"
            . "<rect width=\"$dim\" height=\"$dim\" fill=\"#fff\"/><path d=\"$path\" fill=\"#000\"/></svg>";
    }

    public static function dataUri($text, $level = 'M')
    {
        $svg = self::svg($text, $level);
        return $svg === '' ? '' : 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    // ---------------------------------------------------------------- encoding

    private static function rawModules($ver)
    {
        $result = (16 * $ver + 128) * $ver + 64;
        if ($ver >= 2) {
            $numAlign = intdiv($ver, 7) + 2;
            $result  -= (25 * $numAlign - 10) * $numAlign - 55;
            if ($ver >= 7) {
                $result -= 36;
            }
        }
        return $result;
    }

    private static function dataCodewords($ver, $level)
    {
        return intdiv(self::rawModules($ver), 8) - self::$ECC_PER_BLOCK[$level][$ver] * self::$NUM_BLOCKS[$level][$ver];
    }

    private function build($data, $ver, $level, $forceMask = null)
    {
        $this->version = $ver;
        $this->size    = $ver * 4 + 17;
        $row           = array_fill(0, $this->size, false);
        $this->modules = $this->isFunction = array_fill(0, $this->size, $row);

        // bit stream: mode (byte), length, data, terminator, padding
        $bits = [];
        $this->put($bits, 4, 4);
        $this->put($bits, count($data), $ver <= 9 ? 8 : 16);
        foreach ($data as $b) {
            $this->put($bits, $b, 8);
        }
        $capacity = self::dataCodewords($ver, $level) * 8;
        $this->put($bits, 0, min(4, $capacity - count($bits)));
        $this->put($bits, 0, (8 - count($bits) % 8) % 8);
        for ($pad = 0xEC; count($bits) < $capacity; $pad ^= 0xEC ^ 0x11) {
            $this->put($bits, $pad, 8);
        }
        $codewords = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $v = 0;
            for ($j = 0; $j < 8; $j++) {
                $v = ($v << 1) | $bits[$i + $j];
            }
            $codewords[] = $v;
        }

        $all = $this->addEcc($codewords, $ver, $level);

        $this->drawFunctionPatterns($ver, $level);
        $this->drawCodewords($all);

        // choose the mask with the lowest penalty
        $best = -1;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            if ($forceMask !== null && $mask !== (int) $forceMask) {
                continue;
            }
            $this->applyMask($mask);
            $this->drawFormatBits($level, $mask);
            $p = $this->penalty();
            if ($p < $bestPenalty) {
                $bestPenalty = $p;
                $best = $mask;
            }
            $this->applyMask($mask); // undo
        }
        $this->applyMask($best);
        $this->drawFormatBits($level, $best);

        return $this->modules;
    }

    private function put(&$bits, $value, $len)
    {
        for ($i = $len - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private function addEcc($data, $ver, $level)
    {
        $numBlocks  = self::$NUM_BLOCKS[$level][$ver];
        $eccLen     = self::$ECC_PER_BLOCK[$level][$ver];
        $rawCw      = intdiv(self::rawModules($ver), 8);
        $numShort   = $numBlocks - $rawCw % $numBlocks;
        $shortLen   = intdiv($rawCw, $numBlocks);

        $gen    = $this->rsGenerator($eccLen);
        $blocks = [];
        $k      = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $len   = $shortLen - $eccLen + ($i < $numShort ? 0 : 1);
            $chunk = array_slice($data, $k, $len);
            $k    += $len;
            $ecc   = $this->rsRemainder($chunk, $gen);
            if ($i < $numShort) {
                $chunk[] = null; // keeps the columns aligned for interleaving
            }
            $blocks[] = [$chunk, $ecc];
        }

        $result = [];
        $maxData = $shortLen - $eccLen + 1;
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($blocks as $b) {
                if (isset($b[0][$i]) && $b[0][$i] !== null) {
                    $result[] = $b[0][$i];
                }
            }
        }
        for ($i = 0; $i < $eccLen; $i++) {
            foreach ($blocks as $b) {
                $result[] = $b[1][$i];
            }
        }
        return $result;
    }

    private static $EXP = null;
    private static $LOG = null;

    private function gfInit()
    {
        if (self::$EXP !== null) {
            return;
        }
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }
        self::$EXP = $exp;
        self::$LOG = $log;
    }

    private function gfMul($a, $b)
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$EXP[self::$LOG[$a] + self::$LOG[$b]];
    }

    private function rsGenerator($degree)
    {
        $this->gfInit();
        $gen = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $c) {
                $next[$j]     ^= $c;
                $next[$j + 1] ^= $this->gfMul($c, self::$EXP[$i]);
            }
            $gen = $next;
        }
        array_shift($gen); // leading coefficient is always 1
        return $gen;
    }

    private function rsRemainder($data, $gen)
    {
        $degree = count($gen);
        $rem    = array_fill(0, $degree, 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($rem);
            $rem[]  = 0;
            foreach ($gen as $i => $c) {
                $rem[$i] ^= $this->gfMul($c, $factor);
            }
        }
        return $rem;
    }

    // ---------------------------------------------------------------- drawing

    private function setFn($x, $y, $dark)
    {
        $this->modules[$y][$x]    = (bool) $dark;
        $this->isFunction[$y][$x] = true;
    }

    private function alignPositions($ver)
    {
        if ($ver === 1) {
            return [];
        }
        $num  = intdiv($ver, 7) + 2;
        $step = intdiv($ver * 8 + $num * 3 + 5, $num * 4 - 4) * 2;
        $res  = [6];
        for ($pos = $this->size - 7; count($res) < $num; $pos -= $step) {
            array_splice($res, 1, 0, [$pos]);
        }
        return $res;
    }

    private function drawFunctionPatterns($ver, $level)
    {
        $n = $this->size;
        for ($i = 0; $i < $n; $i++) {
            $this->setFn(6, $i, $i % 2 === 0);
            $this->setFn($i, 6, $i % 2 === 0);
        }
        $this->drawFinder(3, 3);
        $this->drawFinder($n - 4, 3);
        $this->drawFinder(3, $n - 4);

        $pos = $this->alignPositions($ver);
        $cnt = count($pos);
        for ($i = 0; $i < $cnt; $i++) {
            for ($j = 0; $j < $cnt; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $cnt - 1) || ($i === $cnt - 1 && $j === 0)) {
                    continue;
                }
                $this->drawAlignment($pos[$i], $pos[$j]);
            }
        }

        $this->drawFormatBits($level, 0);
        $this->drawVersion($ver);
    }

    private function drawFinder($cx, $cy)
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size) {
                    $this->setFn($x, $y, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawAlignment($cx, $cy)
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFn($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    private function drawFormatBits($level, $mask)
    {
        $eccBits = $level === 'L' ? 1 : 0;
        $data = ($eccBits << 3) | $mask;
        $rem  = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $n = $this->size;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFn(8, $i, (($bits >> $i) & 1) !== 0);
        }
        $this->setFn(8, 7, (($bits >> 6) & 1) !== 0);
        $this->setFn(8, 8, (($bits >> 7) & 1) !== 0);
        $this->setFn(7, 8, (($bits >> 8) & 1) !== 0);
        for ($i = 9; $i < 15; $i++) {
            $this->setFn(14 - $i, 8, (($bits >> $i) & 1) !== 0);
        }
        for ($i = 0; $i < 8; $i++) {
            $this->setFn($n - 1 - $i, 8, (($bits >> $i) & 1) !== 0);
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFn(8, $n - 15 + $i, (($bits >> $i) & 1) !== 0);
        }
        $this->setFn(8, $n - 8, true); // the always-dark module
    }

    private function drawVersion($ver)
    {
        if ($ver < 7) {
            return;
        }
        $rem = $ver;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($ver << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) !== 0;
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFn($a, $b, $dark);
            $this->setFn($b, $a, $dark);
        }
    }

    private function drawCodewords($data)
    {
        $n   = $this->size;
        $i   = 0;
        $total = count($data) * 8;
        for ($right = $n - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $n; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x      = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y      = $upward ? $n - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1) !== 0;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask($mask)
    {
        $n = $this->size;
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                switch ($mask) {
                    case 0: $inv = ($x + $y) % 2 === 0; break;
                    case 1: $inv = $y % 2 === 0; break;
                    case 2: $inv = $x % 3 === 0; break;
                    case 3: $inv = ($x + $y) % 3 === 0; break;
                    case 4: $inv = (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0; break;
                    case 5: $inv = $x * $y % 2 + $x * $y % 3 === 0; break;
                    case 6: $inv = ($x * $y % 2 + $x * $y % 3) % 2 === 0; break;
                    default: $inv = (($x + $y) % 2 + $x * $y % 3) % 2 === 0; break;
                }
                if ($inv && !$this->isFunction[$y][$x]) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    private function penalty()
    {
        $n = $this->size;
        $m = $this->modules;
        $result = 0;

        // runs of the same colour, and 1:1:3:1:1 finder-like patterns, in rows then columns
        for ($pass = 0; $pass < 2; $pass++) {
            for ($a = 0; $a < $n; $a++) {
                $line = [];
                for ($b = 0; $b < $n; $b++) {
                    $line[] = $pass === 0 ? $m[$a][$b] : $m[$b][$a];
                }
                $run = 1;
                for ($b = 1; $b < $n; $b++) {
                    if ($line[$b] === $line[$b - 1]) {
                        $run++;
                        if ($run === 5) {
                            $result += 3;
                        } elseif ($run > 5) {
                            $result++;
                        }
                    } else {
                        $run = 1;
                    }
                }
                for ($b = 0; $b + 10 < $n; $b++) {
                    $s = '';
                    for ($k = 0; $k < 11; $k++) {
                        $s .= $line[$b + $k] ? '1' : '0';
                    }
                    if ($s === '10111010000' || $s === '00001011101') {
                        $result += 40;
                    }
                }
            }
        }

        // 2x2 blocks
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                    $result += 3;
                }
            }
        }

        // balance of dark and light
        $dark = 0;
        foreach ($m as $row) {
            foreach ($row as $c) {
                if ($c) {
                    $dark++;
                }
            }
        }
        $total = $n * $n;
        $k = (int) (ceil(abs($dark * 20 - $total * 10) / $total) - 1);
        return $result + max(0, $k) * 10;
    }
}
