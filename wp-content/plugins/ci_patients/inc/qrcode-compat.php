<?php
/**
 * phpqrcode -> endroid/qr-code compatibility shim.
 *
 * ponytail: phpqrcode (frozen ~2010, unmaintained) was deleted and replaced by
 * endroid/qr-code (Composer). The call sites use the legacy static QRcode::png(), so this
 * provides that exact entry point and maps it onto endroid. Registered via Composer "files"
 * autoload, so the QRcode name resolves wherever vendor/autoload.php is loaded.
 */
if (!class_exists('QRcode', false)) {
    class QRcode
    {
        /**
         * Stand-in for phpqrcode's QRcode::png().
         * Legacy signature (5-arg standard + the merged 11-arg variant the barcode plugin uses):
         *   png($text, $outfile=false, $level='L'|0..3, $size=3, $margin=4, $saveandprint=false,
         *       $back_color=0xFFFFFF, $fore_color=0x000000, $height=0, $width=0, $transparency=0)
         */
        public static function png($text, $outfile = false, $level = 'L', $size = 3, $margin = 4,
                                   $saveandprint = false, $back_color = 0xFFFFFF, $fore_color = 0x000000,
                                   $height = 0, $width = 0, $transparency = 0)
        {
            // ponytail: size/margin calibration knob. phpqrcode's $size is px-per-module and $margin
            // is in modules; endroid takes total px. The standard callers imagescale() the result to
            // their own $size, and the barcode caller fixes display via <img> height/width — so exact
            // px here is not load-bearing, but tune if a scanner is unhappy.
            $px = (int) ($width ?: $height ?: 0);
            if ($px < 21) {
                $px = 300;
            }
            $marginPx = max(0, (int) $margin) * 8;

            $qr = \Endroid\QrCode\QrCode::create((string) $text)
                ->setErrorCorrectionLevel(self::ecLevel($level))
                ->setSize($px)
                ->setMargin($marginPx)
                ->setForegroundColor(self::color($fore_color, 0))
                ->setBackgroundColor(self::color($back_color, $transparency ? 127 : 0))
                ->setRoundBlockSizeMode(\Endroid\QrCode\RoundBlockSizeMode::Margin);

            $result = (new \Endroid\QrCode\Writer\PngWriter())->write($qr);

            if ($outfile) {
                $result->saveToFile($outfile);
                return $outfile;
            }
            echo $result->getString();
        }

        private static function ecLevel($level)
        {
            if (is_string($level)) {
                $level = strtoupper($level);
            }
            $map = array(
                'L' => \Endroid\QrCode\ErrorCorrectionLevel::Low,
                'M' => \Endroid\QrCode\ErrorCorrectionLevel::Medium,
                'Q' => \Endroid\QrCode\ErrorCorrectionLevel::Quartile,
                'H' => \Endroid\QrCode\ErrorCorrectionLevel::High,
                0   => \Endroid\QrCode\ErrorCorrectionLevel::Low,
                1   => \Endroid\QrCode\ErrorCorrectionLevel::Medium,
                2   => \Endroid\QrCode\ErrorCorrectionLevel::Quartile,
                3   => \Endroid\QrCode\ErrorCorrectionLevel::High,
            );
            return $map[$level] ?? \Endroid\QrCode\ErrorCorrectionLevel::Low;
        }

        private static function color($rgbInt, $alpha = 0)
        {
            $rgbInt = (int) $rgbInt;
            return new \Endroid\QrCode\Color\Color(($rgbInt >> 16) & 0xFF, ($rgbInt >> 8) & 0xFF, $rgbInt & 0xFF, $alpha);
        }
    }
}
