<?php

namespace App\Support;

/**
 * Pure-PHP ISO/IEC 18004 QR Code Generator (Version 2, 25x25, ECC Level L, Byte Mode)
 * Renders both Base64 PNG (via GD for DomPDF) and inline SVG without requiring external extensions.
 */
class QrCodeGenerator
{
    /**
     * Alias for toBase64Png().
     */
    public static function pngDataUri(string $text, int $scale = 4, int $margin = 2): ?string
    {
        return self::toBase64Png($text, $scale, $margin);
    }

    /**
     * Alias for toSvg().
     */
    public static function svg(string $text, int $size = 90, int $margin = 2): string
    {
        return self::toSvg($text, $size, $margin);
    }

    /**
     * Generate a data:image/png;base64,... URI suitable for DomPDF and <img> tags.
     */
    public static function toBase64Png(string $text, int $scale = 4, int $margin = 2): ?string
    {
        if (class_exists(\chillerlan\QRCode\QRCode::class)) {
            try {
                $qrcode = new \chillerlan\QRCode\QRCode(
                    new \chillerlan\QRCode\QROptions([
                        'version' => 5,
                        'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                        'eccLevel' => \chillerlan\QRCode\QRCode::ECC_L,
                        'scale' => $scale,
                        'imageBase64' => false,
                    ])
                );
                $png = $qrcode->render($text);
                return 'data:image/png;base64,' . base64_encode($png);
            } catch (\Throwable $e) {
                // Fall through to pure-PHP generator
            }
        }

        $matrix = self::encodeMatrix($text);
        $n = count($matrix);
        $totalModules = $n + ($margin * 2);
        $imgSize = $totalModules * $scale;

        if (function_exists('imagecreatetruecolor')) {
            $im = imagecreatetruecolor($imgSize, $imgSize);
            $white = imagecolorallocate($im, 255, 255, 255);
            $black = imagecolorallocate($im, 0, 0, 0);
            imagefilledrectangle($im, 0, 0, $imgSize - 1, $imgSize - 1, $white);

            for ($r = 0; $r < $n; $r++) {
                for ($c = 0; $c < $n; $c++) {
                    if ($matrix[$r][$c] === 1) {
                        $x1 = ($c + $margin) * $scale;
                        $y1 = ($r + $margin) * $scale;
                        imagefilledrectangle($im, $x1, $y1, $x1 + $scale - 1, $y1 + $scale - 1, $black);
                    }
                }
            }

            ob_start();
            imagepng($im);
            $pngData = ob_get_clean();
            imagedestroy($im);

            if ($pngData) {
                return 'data:image/png;base64,' . base64_encode($pngData);
            }
        }

        // Fallback to base64 SVG if GD is unavailable
        $svg = self::toSvg($text, $imgSize, $margin);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Generate an inline SVG string for web display.
     */
    public static function toSvg(string $text, int $size = 90, int $margin = 2): string
    {
        $matrix = self::encodeMatrix($text);
        $n = count($matrix);
        $total = $n + ($margin * 2);

        $paths = [];
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($matrix[$r][$c] === 1) {
                    $x = $c + $margin;
                    $y = $r + $margin;
                    $paths[] = "M{$x},{$y}h1v1h-1z";
                }
            }
        }

        $pathData = implode('', $paths);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' ' . $total . '" width="' . $size . '" height="' . $size . '" shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="#ffffff"/>'
            . '<path d="' . $pathData . '" fill="#000000"/>'
            . '</svg>';
    }

    /**
     * Encode up to 32 bytes into an ISO/IEC 18004 Version 2-L (25x25) QR matrix.
     */
    public static function encodeMatrix(string $text): array
    {
        $text = substr($text, 0, 32);
        $len = strlen($text);

        // Version 2-L parameters: 25x25 modules, 34 data codewords, 10 EC codewords
        $size = 25;
        $dataCapacity = 34;
        $ecCount = 10;

        // 1. Build bit stream: Mode 0100 (Byte), 8-bit length, data bytes, terminator 0000
        $bits = '0100' . str_pad(decbin($len), 8, '0', STR_PAD_LEFT);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
        }
        $maxBits = $dataCapacity * 8;
        $bits .= substr('0000', 0, max(0, $maxBits - strlen($bits)));
        while (strlen($bits) % 8 !== 0) {
            $bits .= '0';
        }

        $dataCodewords = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $dataCodewords[] = bindec(substr($bits, $i, 8));
        }
        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($dataCodewords) < $dataCapacity) {
            $dataCodewords[] = $padBytes[$padIdx % 2];
            $padIdx++;
        }

        // 2. Compute Reed-Solomon Error Correction codewords (10 codewords for V2-L)
        $ecCodewords = self::reedSolomonEncode($dataCodewords, $ecCount);
        $allCodewords = array_merge($dataCodewords, $ecCodewords);

        // 3. Initialize 25x25 matrix and reserved mask
        $matrix = array_fill(0, $size, array_fill(0, $size, 0));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // Finder patterns at (0,0), (0,18), (18,0)
        self::placeFinderPattern($matrix, $reserved, 0, 0);
        self::placeFinderPattern($matrix, $reserved, 0, $size - 7);
        self::placeFinderPattern($matrix, $reserved, $size - 7, 0);

        // Alignment pattern for Version 2 at center (18, 18) -> top-left (16, 16)
        self::placeAlignmentPattern($matrix, $reserved, 16, 16);

        // Timing patterns (row 6 and col 6)
        for ($i = 8; $i < $size - 8; $i++) {
            $val = ($i % 2 === 0) ? 1 : 0;
            if (! $reserved[6][$i]) {
                $matrix[6][$i] = $val;
                $reserved[6][$i] = true;
            }
            if (! $reserved[$i][6]) {
                $matrix[$i][6] = $val;
                $reserved[$i][6] = true;
            }
        }

        // Dark module at (4 * V + 9, 8) = (17, 8)
        $matrix[$size - 8][8] = 1;
        $reserved[$size - 8][8] = true;

        // Reserve format information areas
        for ($i = 0; $i < 9; $i++) {
            if ($i < $size) {
                $reserved[8][$i] = true;
                $reserved[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 1 - $i] = true;
            $reserved[$size - 1 - $i][8] = true;
        }

        // 4. Place data + EC bits in upward/downward 2-column zig-zag
        $bitStream = '';
        foreach ($allCodewords as $cw) {
            $bitStream .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }
        // 7 remainder bits for Version 2
        $bitStream .= '0000000';

        $bitIdx = 0;
        $bitLen = strlen($bitStream);
        $up = true;

        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--; // Skip vertical timing column
            }
            for ($step = 0; $step < $size; $step++) {
                $row = $up ? ($size - 1 - $step) : $step;
                for ($c = 0; $c < 2; $c++) {
                    $cc = $col - $c;
                    if (! $reserved[$row][$cc]) {
                        $bit = ($bitIdx < $bitLen && $bitStream[$bitIdx] === '1') ? 1 : 0;
                        // Apply Mask 0: (row + col) % 2 == 0
                        if ((($row + $cc) % 2) === 0) {
                            $bit ^= 1;
                        }
                        $matrix[$row][$cc] = $bit;
                        $bitIdx++;
                    }
                }
            }
            $up = ! $up;
        }

        // 5. Place Format Information for ECC Level L (01) + Mask 0 (000) => 0x77C4 (111011111000100)
        $formatBits = '111011111000100';
        $fPos1 = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];
        for ($i = 0; $i < 15; $i++) {
            [$r, $c] = $fPos1[$i];
            $matrix[$r][$c] = (int) $formatBits[$i];
        }

        for ($i = 0; $i < 7; $i++) {
            $matrix[$size - 1 - $i][8] = (int) $formatBits[$i];
        }
        for ($i = 7; $i < 15; $i++) {
            $matrix[8][$size - 15 + $i] = (int) $formatBits[$i];
        }

        return $matrix;
    }

    private static function placeFinderPattern(array &$matrix, array &$reserved, int $row, int $col): void
    {
        $size = count($matrix);
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $row + $r;
                $cc = $col + $c;
                if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) {
                    continue;
                }
                if ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6) {
                    if ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)) {
                        $matrix[$rr][$cc] = 1;
                    } else {
                        $matrix[$rr][$cc] = 0;
                    }
                } else {
                    $matrix[$rr][$cc] = 0;
                }
                $reserved[$rr][$cc] = true;
            }
        }
    }

    private static function placeAlignmentPattern(array &$matrix, array &$reserved, int $row, int $col): void
    {
        for ($r = 0; $r < 5; $r++) {
            for ($c = 0; $c < 5; $c++) {
                $rr = $row + $r;
                $cc = $col + $c;
                if ($r === 0 || $r === 4 || $c === 0 || $c === 4 || ($r === 2 && $c === 2)) {
                    $matrix[$rr][$cc] = 1;
                } else {
                    $matrix[$rr][$cc] = 0;
                }
                $reserved[$rr][$cc] = true;
            }
        }
    }

    private static function reedSolomonEncode(array $data, int $ecCount): array
    {
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

        // Build generator polynomial for $ecCount
        $gen = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            for ($j = 0; $j < count($gen); $j++) {
                $next[$j] ^= $gen[$j];
                if ($gen[$j] !== 0) {
                    $next[$j + 1] ^= $exp[$log[$gen[$j]] + $i];
                }
            }
            $gen = $next;
        }

        $msg = array_merge($data, array_fill(0, $ecCount, 0));
        $dataLen = count($data);
        for ($i = 0; $i < $dataLen; $i++) {
            $coef = $msg[$i];
            if ($coef !== 0) {
                $logCoef = $log[$coef];
                for ($j = 0; $j < count($gen); $j++) {
                    if ($gen[$j] !== 0) {
                        $msg[$i + $j] ^= $exp[$log[$gen[$j]] + $logCoef];
                    }
                }
            }
        }

        return array_slice($msg, $dataLen);
    }
}
